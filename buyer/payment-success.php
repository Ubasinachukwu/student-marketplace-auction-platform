<?php
// marketplace/buyer/payment-success.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/flutterwave-helper.php';
require_once __DIR__ . '/../config/paystack-helper.php';

// Require login
Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// --- Read query params safely (do NOT htmlspecialchars gateway tokens) ---
$orderId          = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$paymentMethodRaw = isset($_GET['method']) ? trim((string)$_GET['method']) : '';

// Flutterwave v3 callback params
$flutterwaveStatus        = isset($_GET['status'])         ? trim((string)$_GET['status'])         : '';
$flutterwaveTxRef         = isset($_GET['tx_ref'])         ? trim((string)$_GET['tx_ref'])         : '';
$flutterwaveTransactionId = isset($_GET['transaction_id']) ? trim((string)$_GET['transaction_id']) : '';

// Paystack callback param
$paystackReference = isset($_GET['reference']) ? trim((string)$_GET['reference']) : '';

if (!$orderId) {
    setError('Invalid order');
    redirect(SITE_URL . '/buyer/orders.php');
}

// Load order
$order = $db->fetchOne(
    "SELECT o.*, l.title as listing_title, u.full_name as seller_name
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.seller_id = u.id
     WHERE o.id = ? AND o.buyer_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/buyer/orders.php');
}

// Prefer the order's stored payment method as the source of truth.
// Fall back to the URL param only if the order has none.
$paymentMethod = $order['payment_method'] ?: $paymentMethodRaw;

// =============================================
// STATE
// =============================================
$verificationMessage = '';
$verificationSuccess = false;

// Helper: reload $order with fresh data
$reloadOrder = function () use ($db, $orderId, $userId) {
    return $db->fetchOne(
        "SELECT o.*, l.title as listing_title, u.full_name as seller_name
         FROM orders o
         JOIN listings l ON o.listing_id = l.id
         JOIN users u ON o.seller_id = u.id
         WHERE o.id = ? AND o.buyer_id = ?",
        [$orderId, $userId]
    );
};

// Idempotency: if the order is already paid, don't re-verify with the gateway.
$alreadyPaid = ($order['payment_status'] === 'paid');

// =============================================
// HANDLE FLUTTERWAVE v3 VERIFICATION
// =============================================
if (!$alreadyPaid && $paymentMethod === 'flutterwave' && $flutterwaveTransactionId !== '') {
    $verification = verifyFlutterwaveTransaction($flutterwaveTransactionId);

    if (!empty($verification['success']) && in_array($verification['status'] ?? '', ['successful', 'success'])) {
        $paidAmount     = (float)($verification['amount'] ?? 0);
        $expectedAmount = (float)$order['total_amount'];

        if (abs($paidAmount - $expectedAmount) < 0.01) {
            try {
                $db->beginTransaction();

                $db->update(
                    "UPDATE orders SET 
                        payment_status = 'paid', 
                        order_status = 'confirmed',
                        payment_id = ?
                     WHERE id = ?",
                    [$verification['transaction_id'], $orderId]
                );

                $db->update(
                    "UPDATE transactions SET 
                        status = 'completed',
                        gateway_transaction_id = ?,
                        description = CONCAT(description, ' - Paid via Flutterwave')
                     WHERE order_id = ?",
                    [$verification['transaction_id'], $orderId]
                );

                // Only mark listing sold once
                $db->update(
                    "UPDATE listings SET status = 'sold' WHERE id = ? AND status != 'sold'",
                    [$order['listing_id']]
                );

                if (function_exists('logActivity')) {
                    logActivity($userId, 'payment_verified', 'Flutterwave payment verified for order #' . $order['order_number']);
                }

                $db->commit();

                $verificationSuccess = true;
                $verificationMessage = 'Payment verified successfully!';
                $order = $reloadOrder();

            } catch (Exception $e) {
                $db->rollback();
                error_log('Flutterwave verification DB error: ' . $e->getMessage());
                $verificationMessage = 'Payment received but failed to update order. Contact support.';
            }
        } else {
            $verificationMessage = 'Payment amount mismatch. Contact support.';
            error_log("Flutterwave amount mismatch: paid {$paidAmount}, expected {$expectedAmount}");
        }
    } else {
        $verificationMessage = 'Payment was not successful. Status: ' . ($verification['status'] ?? 'unknown');
    }
}

// =============================================
// HANDLE PAYSTACK VERIFICATION
// =============================================
if (!$alreadyPaid && $paymentMethod === 'paystack' && $paystackReference !== '') {
    $verification = verifyPaystackTransaction($paystackReference);

    if (!empty($verification['success']) && ($verification['status'] ?? '') === 'success') {
        $paidAmount     = (float)($verification['amount'] ?? 0);
        $expectedAmount = (float)$order['total_amount'];

        if (abs($paidAmount - $expectedAmount) < 0.01) {
            try {
                $db->beginTransaction();

                $db->update(
                    "UPDATE orders SET 
                        payment_status = 'paid', 
                        order_status = 'confirmed',
                        payment_id = ?
                     WHERE id = ?",
                    [$verification['transaction_id'], $orderId]
                );

                $db->update(
                    "UPDATE transactions SET 
                        status = 'completed',
                        gateway_transaction_id = ?,
                        description = CONCAT(description, ' - Paid via Paystack')
                     WHERE order_id = ?",
                    [$verification['transaction_id'], $orderId]
                );

                $db->update(
                    "UPDATE listings SET status = 'sold' WHERE id = ? AND status != 'sold'",
                    [$order['listing_id']]
                );

                if (function_exists('logActivity')) {
                    logActivity($userId, 'payment_verified', 'Paystack payment verified for order #' . $order['order_number']);
                }

                $db->commit();

                $verificationSuccess = true;
                $verificationMessage = 'Payment verified successfully!';
                $order = $reloadOrder();

            } catch (Exception $e) {
                $db->rollback();
                error_log('Paystack verification DB error: ' . $e->getMessage());
                $verificationMessage = 'Payment received but failed to update order. Contact support.';
            }
        } else {
            $verificationMessage = 'Payment amount mismatch. Contact support.';
            error_log("Paystack amount mismatch: paid {$paidAmount}, expected {$expectedAmount}");
        }
    } else {
        $verificationMessage = 'Payment was not successful. Status: ' . ($verification['status'] ?? 'unknown');
    }
}

// =============================================
// PAY ON DELIVERY HANDLING
// =============================================
if ($paymentMethod === 'pay_on_delivery' && $order['order_status'] === 'pending') {
    try {
        $db->beginTransaction();

        $db->update(
            "UPDATE orders SET order_status = 'confirmed' WHERE id = ?",
            [$orderId]
        );

        $db->update(
            "UPDATE listings SET status = 'sold' WHERE id = ? AND status != 'sold'",
            [$order['listing_id']]
        );

        $db->commit();

        if (function_exists('logActivity')) {
            logActivity($userId, 'cod_order_placed', 'COD order placed for #' . $order['order_number']);
        }

    } catch (Exception $e) {
        $db->rollback();
        error_log('COD order error: ' . $e->getMessage());
    }

    $order = $reloadOrder();
}

$pageJS = 'checkout.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    
                    <!-- Success / Warning Icon -->
                    <div class="card shadow-lg border-0">
                        <div class="card-body text-center p-5">
                            
                            <?php if ($paymentMethod === 'flutterwave' || $paymentMethod === 'paystack'): ?>
                                <?php if ($verificationSuccess): ?>
                                    <div class="mb-4">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4">
                                            <i class="bi bi-check-circle-fill text-success display-1"></i>
                                        </div>
                                    </div>
                                    <h1 class="display-5 fw-bold text-success">Payment Successful!</h1>
                                    <p class="lead text-muted">Your payment has been verified and confirmed.</p>

                                <?php elseif ($alreadyPaid): ?>
                                    <div class="mb-4">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4">
                                            <i class="bi bi-check-circle-fill text-success display-1"></i>
                                        </div>
                                    </div>
                                    <h1 class="display-5 fw-bold text-success">Payment Already Confirmed</h1>
                                    <p class="lead text-muted">This order has already been paid.</p>

                                <?php elseif (!empty($verificationMessage)): ?>
                                    <div class="mb-4">
                                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-4">
                                            <i class="bi bi-exclamation-triangle-fill text-warning display-1"></i>
                                        </div>
                                    </div>
                                    <h1 class="display-5 fw-bold text-warning">Payment Pending</h1>
                                    <p class="lead text-muted"><?php echo htmlspecialchars($verificationMessage); ?></p>

                                <?php else: ?>
                                    <div class="mb-4">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4">
                                            <i class="bi bi-check-circle-fill text-success display-1"></i>
                                        </div>
                                    </div>
                                    <h1 class="display-5 fw-bold text-success">Payment Received!</h1>
                                    <p class="lead text-muted">Processing your payment confirmation.</p>
                                <?php endif; ?>

                            <?php elseif ($paymentMethod === 'pay_on_delivery'): ?>
                                <div class="mb-4">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4">
                                        <i class="bi bi-check-circle-fill text-success display-1"></i>
                                    </div>
                                </div>
                                <h1 class="display-5 fw-bold text-success">Order Placed Successfully!</h1>
                                <p class="lead text-muted">Your order has been confirmed. Pay on delivery.</p>

                            <?php else: ?>
                                <div class="mb-4">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4">
                                        <i class="bi bi-check-circle-fill text-success display-1"></i>
                                    </div>
                                </div>
                                <h1 class="display-5 fw-bold text-success">Order Placed!</h1>
                                <p class="lead text-muted">Your order has been placed successfully.</p>
                            <?php endif; ?>

                            <!-- Order Details -->
                            <div class="card bg-light mt-4">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">Order Number</div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Total Amount</div>
                                            <div class="fw-bold text-success"><?php echo formatNaira($order['total_amount']); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Item</div>
                                            <div><?php echo htmlspecialchars($order['listing_title']); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Seller</div>
                                            <div><?php echo htmlspecialchars($order['seller_name']); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Payment Method</div>
                                            <div>
                                                <?php 
                                                    $methodLabels = [
                                                        'pay_on_delivery' => 'Pay on Delivery (Cash)',
                                                        'paystack'        => 'Bank Transfer (Paystack)',
                                                        'flutterwave'     => 'Flutterwave (Card/Bank/USSD)'
                                                        
                                                    ];
                                                    $methodLabel = $methodLabels[$paymentMethod] ?? ucfirst($paymentMethod ?: 'Not specified');
                                                    echo htmlspecialchars($methodLabel);
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Payment Status</div>
                                            <div>
                                                <?php if ($order['payment_status'] === 'paid'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Paid
                                                    </span>
                                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-clock"></i> Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($flutterwaveTransactionId !== ''): ?>
                                            <div class="col-12">
                                                <div class="text-muted small">Flutterwave Transaction ID</div>
                                                <div><code><?php echo htmlspecialchars($flutterwaveTransactionId); ?></code></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($paystackReference !== ''): ?>
                                            <div class="col-12">
                                                <div class="text-muted small">Paystack Reference</div>
                                                <div><code><?php echo htmlspecialchars($paystackReference); ?></code></div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <?php if ($paymentMethod === 'pay_on_delivery'): ?>
                                    <p class="text-muted">
                                        <i class="bi bi-truck text-success"></i> Pay when you receive your item.
                                    </p>
                                <?php elseif ($verificationSuccess || $alreadyPaid): ?>
                                    <p class="text-muted">
                                        <i class="bi bi-check-circle text-success"></i> Your payment has been confirmed. The seller will be notified.
                                    </p>
                                <?php elseif (!empty($verificationMessage)): ?>
                                    <p class="text-muted">
                                        <i class="bi bi-info-circle text-warning"></i> <?php echo htmlspecialchars($verificationMessage); ?>
                                    </p>
                                    <p class="text-muted small">
                                        If your money was deducted but the order shows pending, please contact support with your transaction ID.
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted">
                                        <i class="bi bi-envelope"></i> A confirmation email has been sent to your registered email address.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-3 justify-content-center mt-4 flex-wrap">
                                <a href="<?php echo SITE_URL; ?>/buyer/order-detail.php?id=<?php echo (int)$order['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-eye"></i> View Order
                                </a>
                                <a href="<?php echo SITE_URL; ?>/buyer/orders.php" class="btn btn-outline-primary">
                                    <i class="bi bi-box"></i> My Orders
                                </a>
                                <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i> Continue Shopping
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- What's Next -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> What's Next?</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4 text-center">
                                    <div class="display-6 text-primary">1</div>
                                    <h6>Order Confirmed</h6>
                                    <p class="text-muted small">Your order has been confirmed and sent to the seller.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="display-6 text-primary">2</div>
                                    <h6>Seller Ships</h6>
                                    <p class="text-muted small">The seller will prepare and ship your item soon.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="display-6 text-primary">3</div>
                                    <h6>Item Delivered</h6>
                                    <p class="text-muted small">Track your order and confirm delivery when you receive it.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Support Note -->
                    <?php if (!empty($verificationMessage) && !$verificationSuccess): ?>
                        <div class="alert alert-info mt-4">
                            <i class="bi bi-headset"></i> 
                            <strong>Need help?</strong> If you have any issues with your payment, contact us at 
                            <a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a>
                            with your order number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>