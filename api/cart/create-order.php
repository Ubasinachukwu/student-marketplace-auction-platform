<?php
// marketplace/api/cart/create-order.php
// Creates a multi-item order from the user's cart.
// Called via AJAX/fetch from buyer/checkout.php when cart mode is used.
// Returns JSON: { success, order_id, order_number, redirect }

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// --- Auth ---
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// --- CSRF ---
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId = Session::getUserId();

// --- Read POST fields ---
$shippingAddress  = trim((string)($_POST['shipping_address']  ?? ''));
$shippingState    = trim((string)($_POST['shipping_state']    ?? ''));
$shippingLga      = trim((string)($_POST['shipping_lga']      ?? ''));
$buyerMessage     = trim((string)($_POST['buyer_message']     ?? ''));
$paymentMethod    = trim((string)($_POST['payment_method']    ?? 'pay_on_delivery'));
$preferredCarrier = trim((string)($_POST['preferred_carrier'] ?? ''));

$shippingAddress  = strip_tags($shippingAddress);
$shippingState    = strip_tags($shippingState);
$shippingLga      = strip_tags($shippingLga);
$buyerMessage     = strip_tags($buyerMessage);
$preferredCarrier = strip_tags($preferredCarrier);

// --- Validation ---
$errors = [];

if ($shippingAddress === '') {
    $errors[] = 'Please enter a shipping address';
} elseif (strlen($shippingAddress) > 255) {
    $errors[] = 'Shipping address is too long';
}

if ($shippingState === '') {
    $errors[] = 'Please select a state';
} elseif (!isValidNigerianState($shippingState)) {
    $errors[] = 'Please select a valid Nigerian state';
}

if ($shippingLga === '') {
    $errors[] = 'Please select an LGA';
} elseif (!isValidLGA($shippingState, $shippingLga)) {
    $errors[] = 'Please select a valid LGA for the chosen state';
}

$allowedMethods = ['pay_on_delivery', 'flutterwave', 'paystack'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $errors[] = 'Please select a valid payment method';
}

if (strlen($buyerMessage) > 1000) {
    $errors[] = 'Message to seller is too long';
}

if ($preferredCarrier !== '') {
    if ($preferredCarrier === 'Other' || !array_key_exists($preferredCarrier, NG_DELIVERY_CARRIERS)) {
        $errors[] = 'Please select a valid delivery carrier';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

// --- Load cart ---
$cart = $db->fetchOne(
    "SELECT c.id as cart_id, c.seller_id, u.full_name as seller_name
     FROM carts c
     JOIN users u ON u.id = c.seller_id
     WHERE c.user_id = ?",
    [$userId]
);

if (!$cart) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit();
}

$cartId   = (int)$cart['cart_id'];
$sellerId = (int)$cart['seller_id'];

// --- Load cart items with full details ---
$cartItems = $db->fetchAll(
    "SELECT ci.listing_id, l.title, l.price, l.sale_type, l.status as listing_status,
            (SELECT image_url FROM listing_images 
             WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as image
     FROM cart_items ci
     JOIN listings l ON l.id = ci.listing_id
     WHERE ci.cart_id = ?
     ORDER BY ci.added_at ASC",
    [$cartId]
);

if (empty($cartItems)) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit();
}

// --- Validate: seller cannot buy own items ---
if ($sellerId === (int)$userId) {
    echo json_encode(['success' => false, 'message' => 'You cannot purchase your own items']);
    exit();
}

// --- Validate: every item must be active + fixed price ---
foreach ($cartItems as $item) {
    if ($item['listing_status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'Item "' . $item['title'] . '" is no longer available. Please remove it from your cart.'
        ]);
        exit();
    }
    if ($item['sale_type'] !== 'fixed') {
        echo json_encode([
            'success' => false,
            'message' => 'Item "' . $item['title'] . '" cannot be purchased via cart. Please buy it individually.'
        ]);
        exit();
    }
}

// --- Calculate totals ---
$subtotal = 0.0;
foreach ($cartItems as $item) {
    $subtotal += (float)$item['price'];
}

if ($subtotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart total']);
    exit();
}

$platformFee   = calculateFee($subtotal, PLATFORM_FEE_PERCENTAGE);
$totalAmount   = $subtotal;
$netAmount     = $totalAmount - $platformFee;
$preferredDb   = $preferredCarrier !== '' ? $preferredCarrier : null;

// --- Create the order ---
try {
    $db->beginTransaction();

    $orderNumber = generateOrderNumber();

    // Insert into orders with is_multi_item = 1 and listing_id = NULL
    $orderId = $db->insert(
        "INSERT INTO orders (
            listing_id, is_multi_item, buyer_id, seller_id, order_number, total_amount,
            platform_fee, net_amount, payment_method, payment_status, order_status,
            shipping_address, shipping_state, shipping_lga, preferred_carrier, buyer_message
        ) VALUES (NULL, 1, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?)",
        [
            $userId,
            $sellerId,
            $orderNumber,
            $totalAmount,
            $platformFee,
            $netAmount,
            $paymentMethod,
            $shippingAddress,
            $shippingState,
            $shippingLga,
            $preferredDb,
            $buyerMessage
        ]
    );

    // --- Insert order_items (snapshot of each listing at purchase time) ---
    foreach ($cartItems as $item) {
        $db->insert(
            "INSERT INTO order_items (order_id, listing_id, title, price, quantity, image_url)
             VALUES (?, ?, ?, ?, 1, ?)",
            [
                $orderId,
                $item['listing_id'],
                $item['title'],
                $item['price'],
                $item['image'] ?? null
            ]
        );
    }

    // --- Create a transaction record ---
    $db->insert(
        "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
         VALUES (?, ?, 'payment', ?, 'Cart order payment', 'pending')",
        [$orderId, $userId, $totalAmount]
    );

    // --- Notify seller ---
    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'order', 'New Order', 
                 CONCAT('You have a new order #', ?, ' with ', ?, ' items'), 
                 CONCAT('/seller/order-detail.php?id=', ?))",
        [
            $sellerId,
            $orderNumber,
            count($cartItems),
            $orderId
        ]
    );

    // --- Clear the cart now that the order exists ---
    $db->delete("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
    $db->delete("DELETE FROM carts WHERE id = ?", [$cartId]);

    // --- Log activity ---
    if (function_exists('logActivity')) {
        logActivity($userId, 'cart_order_created', 
            'Created multi-item order #' . $orderNumber . ' with ' . count($cartItems) . ' items');
    }

    $db->commit();

    // --- Return success with redirect hint ---
    // The caller (checkout.php or JS) decides which payment gateway to send the user to.
    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'item_count'   => count($cartItems),
        'total_amount' => $totalAmount,
        'message'      => 'Order created',
        'redirect'     => SITE_URL . '/buyer/payment-success.php?order_id=' . $orderId . '&method=' . urlencode($paymentMethod)
    ]);

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    error_log('create-order API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create order. Please try again.'
    ]);
}