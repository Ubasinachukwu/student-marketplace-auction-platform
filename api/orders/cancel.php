<?php
// marketplace/api/orders/cancel.php
// Cancels an order. Works for both buyer and seller.

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId   = Session::getUserId();
$userType = Session::getUserType();
$orderId  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Load order regardless of role, then decide based on who is asking
$order = $db->fetchOne(
    "SELECT * FROM orders WHERE id = ?",
    [$orderId]
);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

// Determine the caller's relationship to the order
$isBuyer  = ((int)$order['buyer_id']  === (int)$userId);
$isSeller = ((int)$order['seller_id'] === (int)$userId);

if (!$isBuyer && !$isSeller) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel this order']);
    exit();
}

// Status check: only pending or confirmed can be cancelled
if (!in_array($order['order_status'], ['pending', 'confirmed'], true)) {
    echo json_encode(['success' => false, 'message' => 'This order cannot be cancelled at this stage']);
    exit();
}

// Optional policy: buyers cannot cancel a "confirmed" order (seller already accepted)
// Uncomment the block below if you want that rule.
//
// if ($isBuyer && $order['order_status'] === 'confirmed') {
//     echo json_encode(['success' => false, 'message' => 'This order was already confirmed by the seller. Please contact the seller directly.']);
//     exit();
// }

try {
    $db->beginTransaction();

    // Update order status
    $db->update(
        "UPDATE orders SET order_status = 'cancelled' WHERE id = ?",
        [$orderId]
    );

    // Restore the listing to active ONLY if this order was the reason it became 'sold'.
    // (If some other order bought it, don't touch it.)
    if ($order['order_status'] !== 'cancelled') {
        $db->update(
            "UPDATE listings SET status = 'active' 
             WHERE id = ? AND status = 'sold'",
            [$order['listing_id']]
        );
    }

    // Notify the OTHER party
    if ($isBuyer) {
        // Buyer cancelled → notify seller
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'order', 'Order Cancelled', 
                     CONCAT('Order #', ?, ' was cancelled by the buyer.'), 
                     CONCAT('/seller/order-detail.php?id=', ?))",
            [
                $order['seller_id'],
                $order['order_number'],
                $orderId
            ]
        );
    } else {
        // Seller cancelled → notify buyer
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'order', 'Order Cancelled', 
                     CONCAT('Your order #', ?, ' was cancelled by the seller.'), 
                     CONCAT('/buyer/order-detail.php?id=', ?))",
            [
                $order['buyer_id'],
                $order['order_number'],
                $orderId
            ]
        );
    }

    // Log activity
    if (function_exists('logActivity')) {
        $actor = $isBuyer ? 'buyer' : 'seller';
        logActivity(
            $userId,
            'order_cancelled',
            'Order #' . $order['order_number'] . ' cancelled by ' . $actor
        );
    }

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log('Order cancel error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order. Please try again.']);
}