<?php
// marketplace/buyer/checkout.php
// Unified checkout — handles single-item AND cart mode

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/flutterwave-helper.php';
require_once __DIR__ . '/../config/paystack-helper.php';

Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// =============================================
// DETECT MODE
// =============================================
$mode = 'single';

if (isset($_GET['from_cart']) && $_GET['from_cart'] == '1') {
    $mode = 'cart';
}

// =============================================
// LOAD DATA BASED ON MODE
// =============================================
$cartItems    = [];
$cartId       = null;
$listing      = null;
$listingId    = 0;
$type         = 'buynow';
$isAuction    = false;

if ($mode === 'cart') {
    $cart = $db->fetchOne(
        "SELECT c.id as cart_id, c.seller_id, u.full_name as seller_name
         FROM carts c
         JOIN users u ON u.id = c.seller_id
         WHERE c.user_id = ?",
        [$userId]
    );

    if (!$cart) {
        setError('Your cart is empty');
        redirect(SITE_URL . '/buyer/cart.php');
    }

    $cartId = $cart['cart_id'];
    $sellerId = (int)$cart['seller_id'];
    $sellerName = $cart['seller_name'];

    $cartItems = $db->fetchAll(
        "SELECT ci.listing_id, ci.added_at,
                l.title, l.price, l.sale_type, l.status as listing_status,
                l.condition,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as image
         FROM cart_items ci
         JOIN listings l ON l.id = ci.listing_id
         WHERE ci.cart_id = ?
         ORDER BY ci.added_at ASC",
        [$cartId]
    );

    if (empty($cartItems)) {
        setError('Your cart is empty');
        redirect(SITE_URL . '/buyer/cart.php');
    }

    foreach ($cartItems as $it) {
        if ($it['listing_status'] !== 'active') {
            setError('One or more items in your cart are no longer available. Please review your cart.');
            redirect(SITE_URL . '/buyer/cart.php');
        }
        if ($it['sale_type'] !== 'fixed') {
            setError('Your cart contains items that cannot be purchased together. Please remove them.');
            redirect(SITE_URL . '/buyer/cart.php');
        }
    }

    $price = 0;
    foreach ($cartItems as $it) {
        $price += (float)$it['price'];
    }

} else {
    $listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;
    $type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'buynow';

    if (!in_array($type, ['buynow', 'bid'], true)) {
        $type = 'buynow';
    }

    if (!$listingId) {
        setError('Invalid listing');
        redirect(SITE_URL . '/buyer/browse.php');
    }

    $listing = $db->fetchOne(
        "SELECT l.*, u.full_name as seller_name, u.id as seller_id,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
         FROM listings l
         JOIN users u ON l.seller_id = u.id
         WHERE l.id = ? AND l.status = 'active'",
        [$listingId]
    );

    if (!$listing) {
        setError('Listing not found or not available');
        redirect(SITE_URL . '/buyer/browse.php');
    }

    if ((int)$listing['seller_id'] === (int)$userId) {
        setError('You cannot purchase your own item');
        redirect(SITE_URL . '/buyer/listing-detail.php?id=' . $listingId);
    }

    $sellerId = (int)$listing['seller_id'];
    $sellerName = $listing['seller_name'];

    $price = 0;
    $isAuction = in_array($listing['sale_type'], ['auction', 'hybrid'], true);

    if ($type === 'buynow' && ($listing['sale_type'] === 'fixed' || $listing['sale_type'] === 'hybrid')) {
        $price = $listing['sale_type'] === 'fixed' ? $listing['price'] : $listing['buy_it_now_price'];
    } elseif ($type === 'bid' && $isAuction) {
        $highestBid = $db->fetchOne(
            "SELECT * FROM bids WHERE listing_id = ? AND is_winning = 1 AND is_retracted = 0",
            [$listingId]
        );
        if (!$highestBid || $highestBid['buyer_id'] != $userId) {
            setError('You are not the winner of this auction');
            redirect(SITE_URL . '/buyer/listing-detail.php?id=' . $listingId);
        }
        $price = $highestBid['bid_amount'];
    } else {
        setError('Invalid purchase type');
        redirect(SITE_URL . '/buyer/listing-detail.php?id=' . $listingId);
    }
}

if ($price <= 0) {
    setError('Invalid price');
    redirect(SITE_URL . '/buyer/browse.php');
}

// =============================================
// SHARED: user, fees, pre-fill
// =============================================
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

$platformFee = calculateFee($price, PLATFORM_FEE_PERCENTAGE);
$totalAmount = $price + 0;

$shippingAddress = $user['address'] ?? '';
$shippingState   = $user['state']   ?? '';
$shippingLga     = $user['lga']     ?? '';
$preferredCarrier = '';
$buyerMessage    = '';

// =============================================
// HANDLE SUBMISSION
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/buyer/checkout.php' . ($mode === 'cart' ? '?from_cart=1' : '?listing_id=' . $listingId . '&type=' . $type));
    }

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

    // PayPal removed — Nigerian-only payment methods
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

    if ($mode === 'cart' && empty($errors)) {
        $freshItems = $db->fetchAll(
            "SELECT ci.listing_id, l.price, l.status
             FROM cart_items ci
             JOIN listings l ON l.id = ci.listing_id
             WHERE ci.cart_id = ?",
            [$cartId]
        );

        if (count($freshItems) !== count($cartItems)) {
            $errors[] = 'Your cart has changed. Please review and try again.';
        } else {
            foreach ($freshItems as $fi) {
                if ($fi['status'] !== 'active') {
                    $errors[] = 'One or more items are no longer available.';
                    break;
                }
            }
        }
    }

    if (!empty($errors)) {
        setError(implode('<br>', $errors));
        redirect(SITE_URL . '/buyer/checkout.php' . ($mode === 'cart' ? '?from_cart=1' : '?listing_id=' . $listingId . '&type=' . $type));
    }

    try {
        $db->beginTransaction();

        $orderNumber   = generateOrderNumber();
        $paymentStatus = 'pending';
        $orderStatus   = 'pending';
        $preferredCarrierDb = $preferredCarrier !== '' ? $preferredCarrier : null;

        $orderListingId = ($mode === 'cart') ? null : $listingId;
        $isMultiItem    = ($mode === 'cart') ? 1 : 0;

        $orderId = $db->insert(
            "INSERT INTO orders (
                listing_id, is_multi_item, buyer_id, seller_id, order_number, total_amount,
                platform_fee, net_amount, payment_method, payment_status, order_status,
                shipping_address, shipping_state, shipping_lga, preferred_carrier, buyer_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderListingId,
                $isMultiItem,
                $userId,
                $sellerId,
                $orderNumber,
                $totalAmount,
                $platformFee,
                $totalAmount - $platformFee,
                $paymentMethod,
                $paymentStatus,
                $orderStatus,
                $shippingAddress,
                $shippingState,
                $shippingLga,
                $preferredCarrierDb,
                $buyerMessage
            ]
        );

        if ($mode === 'cart') {
            foreach ($cartItems as $it) {
                $imgRow = $db->fetchOne(
                    "SELECT image_url FROM listing_images WHERE listing_id = ? AND is_primary = 1 LIMIT 1",
                    [$it['listing_id']]
                );

                $db->insert(
                    "INSERT INTO order_items (order_id, listing_id, title, price, quantity, image_url)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        $it['listing_id'],
                        $it['title'],
                        $it['price'],
                        1,
                        $imgRow['image_url'] ?? null
                    ]
                );
            }
        }

        $db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
             VALUES (?, ?, 'payment', ?, 'Order payment', 'pending')",
            [$orderId, $userId, $totalAmount]
        );

        if ($mode === 'single' && $isAuction) {
            $db->update(
                "UPDATE bids SET is_winning = 0 WHERE listing_id = ? AND buyer_id != ?",
                [$listingId, $userId]
            );
        }

        if ($mode === 'cart') {
            $db->delete("DELETE FROM carts WHERE id = ?", [$cartId]);
        }

        $db->commit();

        // =============================================
        // PAYMENT HANDLING
        // =============================================

        $checkoutReturnUrl = SITE_URL . '/buyer/checkout.php' . ($mode === 'cart' ? '?from_cart=1' : '?listing_id=' . $listingId . '&type=' . $type);

        if ($paymentMethod === 'pay_on_delivery') {
            setSuccess('Order placed! You will pay on delivery.');
            redirect(SITE_URL . '/buyer/payment-success.php?order_id=' . $orderId . '&method=pay_on_delivery');
        }

        if ($paymentMethod === 'flutterwave') {
            $redirectUrl = SITE_URL . '/buyer/payment-success.php?order_id=' . $orderId . '&method=flutterwave';
            $paymentData = [
                'amount'         => $totalAmount,
                'currency'       => 'NGN',
                'customer_email' => $user['email'],
                'customer_name'  => $user['full_name'],
                'customer_phone' => $user['phone'] ?? '',
                'description'    => 'Payment for Order #' . $orderNumber,
                'redirect_url'   => $redirectUrl,
                'order_id'       => $orderId,
                'user_id'        => $userId,
            ];
            $result = initializeFlutterwavePayment($paymentData);
            if (!empty($result['success'])) {
                $db->update("UPDATE orders SET payment_id = ? WHERE id = ?", [$result['tx_ref'], $orderId]);
                redirect($result['link']);
            }
            setError('Payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
            redirect($checkoutReturnUrl);
        }

        if ($paymentMethod === 'paystack') {
            $redirectUrl = SITE_URL . '/buyer/payment-success.php?order_id=' . $orderId . '&method=paystack';
            $paymentData = [
                'amount'         => $totalAmount,
                'currency'       => 'NGN',
                'customer_email' => $user['email'],
                'customer_name'  => $user['full_name'],
                'customer_phone' => $user['phone'] ?? '',
                'description'    => 'Payment for Order #' . $orderNumber,
                'redirect_url'   => $redirectUrl,
                'order_id'       => $orderId,
                'order_number'   => $orderNumber,
                'user_id'        => $userId,
            ];
            $result = initializePaystackPayment($paymentData);
            if (!empty($result['success'])) {
                $db->update("UPDATE orders SET payment_id = ? WHERE id = ?", [$result['reference'], $orderId]);
                redirect($result['link']);
            }
            setError('Paystack initialization failed: ' . ($result['message'] ?? 'Unknown error'));
            redirect($checkoutReturnUrl);
        }

        redirect(SITE_URL . '/buyer/payment-success.php?order_id=' . $orderId);

    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        error_log('Checkout Error: ' . $e->getMessage());
        setError('Checkout failed. Please try again or contact support.');
        redirect(SITE_URL . '/buyer/checkout.php' . ($mode === 'cart' ? '?from_cart=1' : '?listing_id=' . $listingId . '&type=' . $type));
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'checkout.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">
                                <i class="bi bi-cart-check"></i> Checkout
                                <?php if ($mode === 'cart'): ?>
                                    <span class="badge bg-light text-success ms-2"><?php echo count($cartItems); ?> items</span>
                                <?php endif; ?>
                            </h4>
                        </div>
                        <div class="card-body">

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6>
                                        <i class="bi bi-box"></i>
                                        <?php echo ($mode === 'cart') ? 'Items in Your Cart' : 'Item Summary'; ?>
                                    </h6>

                                    <?php if ($mode === 'cart'): ?>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($cartItems as $item): ?>
                                                <div class="d-flex align-items-start mb-3 pb-2 border-bottom">
                                                    <img src="<?php echo getImageUrl($item['image'] ?? null, 'listing'); ?>"
                                                         class="img-fluid rounded"
                                                         style="width: 60px; height: 60px; object-fit: cover; flex-shrink: 0;"
                                                         alt="">
                                                    <div class="ms-3 flex-grow-1">
                                                        <div class="fw-bold small"><?php echo htmlspecialchars($item['title']); ?></div>
                                                        <div class="text-primary fw-bold small"><?php echo formatNaira($item['price']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-start mb-3">
                                            <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>"
                                                 class="img-fluid rounded"
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                            <div class="ms-3">
                                                <div class="fw-bold"><?php echo htmlspecialchars($listing['title']); ?></div>
                                                <div class="text-muted small">Sold by: <?php echo htmlspecialchars($listing['seller_name']); ?></div>
                                                <div class="text-muted small">Condition: <?php echo htmlspecialchars(getConditionLabel($listing['condition'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <h6><i class="bi bi-credit-card"></i> Price Breakdown</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><?php echo ($mode === 'cart') ? 'Items Subtotal (' . count($cartItems) . ')' : 'Item Price'; ?></td>
                                                <td class="text-end"><?php echo formatNaira($price); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Platform Fee (<?php echo PLATFORM_FEE_PERCENTAGE; ?>%)</td>
                                                <td class="text-end"><?php echo formatNaira($platformFee); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Shipping</td>
                                                <td class="text-end"><?php echo formatNaira(0); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Tax</td>
                                                <td class="text-end"><?php echo formatNaira(0); ?></td>
                                            </tr>
                                            <tr class="fw-bold">
                                                <td>Total</td>
                                                <td class="text-end text-success"><?php echo formatNaira($totalAmount); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="alert alert-info small mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        All items ship to the same address. Sold by
                                        <strong><?php echo htmlspecialchars($sellerName); ?></strong>.
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <form method="POST" action="" id="checkoutForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <?php if ($mode === 'single'): ?>
                                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                                <?php else: ?>
                                    <input type="hidden" name="from_cart" value="1">
                                <?php endif; ?>

                                <h6 class="mb-3"><i class="bi bi-geo-alt"></i> Shipping Address</h6>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="shipping_state" class="form-label">State <span class="text-danger">*</span></label>
                                        <select class="form-select" id="shipping_state" name="shipping_state"
                                                onchange="loadShippingLGAs(this.value)" required>
                                            <?php echo getStateOptions($shippingState, 'Select State'); ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_lga" class="form-label">LGA <span class="text-danger">*</span></label>
                                        <select class="form-select" id="shipping_lga" name="shipping_lga" required>
                                            <?php echo getLGAOptions($shippingState, $shippingLga, 'Select LGA'); ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="shipping_address" class="form-label">Street Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="2" required
                                              placeholder="e.g., 12 Broad Street, Victoria Island"><?php echo htmlspecialchars($shippingAddress); ?></textarea>
                                    <small class="text-muted">Your street/building address for delivery</small>
                                </div>

                                <div class="mb-3">
                                    <label for="preferred_carrier" class="form-label">Preferred Delivery Carrier <span class="text-muted">(Optional)</span></label>
                                    <select class="form-select" id="preferred_carrier" name="preferred_carrier">
                                        <option value="">Let the seller choose</option>
                                        <?php foreach (NG_DELIVERY_CARRIERS as $value => $label): ?>
                                            <?php if ($value === 'Other') continue; ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo ($value === $preferredCarrier) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Seller may use a different carrier depending on your location.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="buyer_message" class="form-label">Message to Seller (Optional)</label>
                                    <textarea class="form-control" id="buyer_message" name="buyer_message" rows="2"
                                              placeholder="Any special instructions for the seller?"><?php echo htmlspecialchars($buyerMessage ?? ''); ?></textarea>
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <div class="row g-3">

                                        <div class="col-md-4">
                                            <div class="form-check card p-3 border-primary">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment_pay_on_delivery" value="pay_on_delivery" checked>
                                                <label class="form-check-label w-100" for="payment_pay_on_delivery">
                                                    <i class="bi bi-truck fs-3 text-success"></i>
                                                    <div><strong>Pay on Delivery</strong></div>
                                                    <small class="text-muted">Cash on delivery (COD)</small>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-check card p-3">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment_flutterwave" value="flutterwave">
                                                <label class="form-check-label w-100" for="payment_flutterwave">
                                                    <i class="bi bi-credit-card fs-3 text-info"></i>
                                                    <div><strong>Pay Online</strong></div>
                                                    <small class="text-muted">Card, Bank, USSD (Flutterwave)</small>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-check card p-3">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment_paystack" value="paystack">
                                                <label class="form-check-label w-100" for="payment_paystack">
                                                    <i class="bi bi-bank fs-3 text-primary"></i>
                                                    <div><strong>Bank Transfer</strong></div>
                                                    <small class="text-muted">Paystack (Card/Bank)</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                        <i class="bi bi-lock"></i> Place Order & Pay <?php echo formatNaira($totalAmount); ?>
                                    </button>
                                    <a href="<?php echo $mode === 'cart' ? SITE_URL . '/buyer/cart.php' : SITE_URL . '/buyer/listing-detail.php?id=' . $listingId; ?>"
                                       class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> <?php echo $mode === 'cart' ? 'Back to Cart' : 'Back to Listing'; ?>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const nigeriaStatesLGAs = <?php echo getStatesLGAsJson(); ?>;

function loadShippingLGAs(state) {
    const lgaSelect = document.getElementById('shipping_lga');
    lgaSelect.innerHTML = '<option value="">Select LGA</option>';
    if (!state || !nigeriaStatesLGAs[state]) return;
    nigeriaStatesLGAs[state].forEach(function(lga) {
        const option = document.createElement('option');
        option.value = lga;
        option.textContent = lga;
        lgaSelect.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.form-check.card').forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                document.querySelectorAll('.form-check.card').forEach(c => c.classList.remove('border-primary'));
                this.classList.add('border-primary');
            }
        });
    });

    const stateSelect = document.getElementById('shipping_state');
    if (stateSelect && stateSelect.value) {
        const currentLga = <?php echo json_encode($shippingLga); ?>;
        loadShippingLGAs(stateSelect.value);
        if (currentLga) {
            document.getElementById('shipping_lga').value = currentLga;
        }
    }

    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const checked = document.querySelector('input[name="payment_method"]:checked');
        if (!checked) return;
        const paymentMethod = checked.value;
        const btn = document.getElementById('submitBtn');
        if (paymentMethod === 'flutterwave') {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Redirecting to Flutterwave...';
        } else if (paymentMethod === 'paystack') {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Redirecting to Paystack...';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>