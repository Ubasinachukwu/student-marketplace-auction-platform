<?php
// marketplace/buyer/payment-cancel.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
Session::requireLogin();

$db = db();
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$listingId = 0;

// Get listing ID from order if order exists
if ($orderId) {
    $order = $db->fetchOne(
        "SELECT listing_id FROM orders WHERE id = ? AND buyer_id = ?",
        [$orderId, Session::getUserId()]
    );
    if ($order) {
        $listingId = $order['listing_id'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card shadow-lg border-0">
                        <div class="card-body text-center p-5">
                            <!-- Cancel Icon -->
                            <div class="mb-4">
                                <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex p-4">
                                    <i class="bi bi-x-circle-fill text-danger display-1"></i>
                                </div>
                            </div>

                            <h1 class="display-5 fw-bold text-danger">Payment Cancelled</h1>
                            <p class="lead text-muted">Your payment was cancelled or failed.</p>

                            <div class="card bg-light mt-4">
                                <div class="card-body">
                                    <p>Don't worry, you haven't been charged. You can try again or choose a different payment method.</p>
                                    <?php if ($orderId): ?>
                                        <p class="text-muted small">Order #<?php echo $orderId; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-3 justify-content-center mt-4 flex-wrap">
                                <?php if ($listingId): ?>
                                    <a href="<?php echo SITE_URL; ?>/buyer/checkout.php?listing_id=<?php echo $listingId; ?>&type=buynow" class="btn btn-primary">
                                        <i class="bi bi-arrow-counterclockwise"></i> Try Again
                                    </a>
                                <?php elseif ($orderId): ?>
                                    <a href="<?php echo SITE_URL; ?>/buyer/order-detail.php?id=<?php echo $orderId; ?>" class="btn btn-primary">
                                        <i class="bi bi-arrow-counterclockwise"></i> View Order
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/buyer/orders.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box"></i> My Orders
                                </a>
                                <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i> Browse Items
                                </a>
                            </div>

                            <!-- Help -->
                            <div class="mt-4">
                                <p class="text-muted small">
                                    <i class="bi bi-question-circle"></i> Need help? 
                                    <a href="<?php echo SITE_URL; ?>/contact.php">Contact Support</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>