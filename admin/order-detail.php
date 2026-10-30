<?php
// marketplace/admin/order-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    setError('Invalid order ID');
    redirect(SITE_URL . '/admin/orders.php');
}

// Get order details with shipping state and LGA
$order = $db->fetchOne(
    "SELECT o.*, 
            l.title as listing_title,
            l.description as listing_description,
            l.condition as listing_condition,
            l.sale_type,
            l.id as listing_id,
            u.full_name as buyer_name,
            u.email as buyer_email,
            u.phone as buyer_phone,
            u.address as buyer_address,
            u.state as buyer_state,
            u.lga as buyer_lga,
            u.profile_pic as buyer_profile_pic,
            u2.full_name as seller_name,
            u2.email as seller_email,
            u2.phone as seller_phone,
            u2.state as seller_state,
            u2.lga as seller_lga,
            u2.profile_pic as seller_profile_pic,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image,
            (SELECT COUNT(*) FROM disputes WHERE order_id = o.id AND status IN ('open', 'under_review')) as open_disputes
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.buyer_id = u.id
     JOIN users u2 ON o.seller_id = u2.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/admin/orders.php');
}

// Get order timeline
$timeline = [
    ['status' => 'Order Placed', 'date' => $order['created_at'], 'icon' => 'bi-cart'],
];

if ($order['order_status'] == 'confirmed' || $order['order_status'] == 'shipped' || 
    $order['order_status'] == 'delivered' || $order['order_status'] == 'completed') {
    $timeline[] = ['status' => 'Order Confirmed', 'date' => $order['updated_at'], 'icon' => 'bi-check-circle'];
}

if ($order['order_status'] == 'shipped' || $order['order_status'] == 'delivered' || 
    $order['order_status'] == 'completed') {
    $shippedDate = $order['shipped_date'] ?? $order['updated_at'];
    $timeline[] = ['status' => 'Shipped', 'date' => $shippedDate, 'icon' => 'bi-truck'];
}

if ($order['order_status'] == 'delivered' || $order['order_status'] == 'completed') {
    $deliveredDate = $order['delivered_date'] ?? $order['updated_at'];
    $timeline[] = ['status' => 'Delivered', 'date' => $deliveredDate, 'icon' => 'bi-box'];
}

if ($order['order_status'] == 'completed') {
    $timeline[] = ['status' => 'Completed', 'date' => $order['updated_at'], 'icon' => 'bi-check-all'];
}

if ($order['order_status'] == 'cancelled') {
    $timeline[] = ['status' => 'Cancelled', 'date' => $order['updated_at'], 'icon' => 'bi-x-circle'];
}

// Get disputes
$disputes = $db->fetchAll(
    "SELECT d.*, u.full_name as raised_by_name
     FROM disputes d
     JOIN users u ON d.raised_by = u.id
     WHERE d.order_id = ?
     ORDER BY d.created_at DESC",
    [$orderId]
);

// Get messages
$messages = $db->fetchAll(
    "SELECT m.*, u.full_name as sender_name, u.user_type as sender_type
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.order_id = ? OR m.listing_id = ?
     ORDER BY m.created_at ASC",
    [$orderId, $order['listing_id']]
);

// Payment method label
$methodLabels = [
    'pay_on_delivery' => 'Pay on Delivery (Cash)',
    'paystack' => 'Bank Transfer (Paystack)',
    'flutterwave' => 'Flutterwave'
   
];
$methodLabel = $methodLabels[$order['payment_method']] ?? ucfirst($order['payment_method'] ?: 'Not specified');

$csrfToken = Session::generateCSRFToken();
$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-box"></i> Order #<?php echo $order['order_number']; ?></h1>
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/orders.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>

            <!-- Status Banner -->
            <?php if ($order['open_disputes'] > 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> This order has <?php echo $order['open_disputes']; ?> open dispute(s).
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?order_id=<?php echo $orderId; ?>" class="alert-link">View Disputes</a>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Order Timeline -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Order Timeline</h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($timeline as $index => $event): ?>
                                    <div class="d-flex mb-3 <?php echo $index === count($timeline) - 1 ? '' : 'border-start border-primary ps-4'; ?>">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-<?php echo $index === count($timeline) - 1 ? 'primary' : 'light'; ?> rounded-circle p-2">
                                                <i class="bi <?php echo $event['icon']; ?> <?php echo $index === count($timeline) - 1 ? 'text-white' : 'text-secondary'; ?>"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo $event['status']; ?></div>
                                            <small class="text-muted"><?php echo formatDateTime($event['date']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Item Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-box"></i> Item Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-3">
                                    <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                         class="img-fluid rounded" 
                                         alt="<?php echo htmlspecialchars($order['listing_title']); ?>">
                                </div>
                                <div class="col-md-9">
                                    <h5><?php echo htmlspecialchars($order['listing_title']); ?></h5>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['listing_description'])); ?></p>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <small class="text-muted">Condition</small>
                                            <div><?php echo getConditionLabel($order['listing_condition']); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Sale Type</small>
                                            <div><?php echo ucfirst($order['sale_type']); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Price</small>
                                            <div class="fw-bold"><?php echo formatNaira($order['total_amount']); ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $order['listing_id']; ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Listing
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Disputes -->
                    <?php if (!empty($disputes)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Disputes (<?php echo count($disputes); ?>)</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($disputes as $dispute): ?>
                                    <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <span class="badge bg-<?php echo $dispute['status'] == 'open' ? 'danger' : ($dispute['status'] == 'under_review' ? 'warning' : 'success'); ?> fs-6">
                                                <?php echo ucfirst($dispute['status']); ?>
                                            </span>
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($dispute['raised_by_name']); ?></div>
                                            <div class="text-muted small"><?php echo str_replace('_', ' ', $dispute['dispute_type']); ?></div>
                                            <div><?php echo nl2br(htmlspecialchars($dispute['description'])); ?></div>
                                            <div class="text-muted small"><?php echo timeAgo($dispute['created_at']); ?></div>
                                        </div>
                                        <div>
                                            <a href="<?php echo SITE_URL; ?>/admin/dispute-detail.php?id=<?php echo $dispute['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Messages -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-chat"></i> Communication</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-chat display-4 text-muted"></i>
                                    <p class="text-muted">No messages</p>
                                </div>
                            <?php else: ?>
                                <div class="messages-container" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="d-flex mb-2">
                                            <div class="flex-shrink-0 me-2">
                                                <i class="bi bi-person-circle fs-4 text-muted"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                                <div><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                                <small class="text-muted"><?php echo timeAgo($msg['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Order Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-receipt"></i> Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>Item Price</td>
                                    <td class="text-end"><?php echo formatNaira($order['total_amount'] - $order['shipping_cost'] - $order['tax_amount']); ?></td>
                                </tr>
                                <?php if ($order['shipping_cost'] > 0): ?>
                                    <tr>
                                        <td>Shipping</td>
                                        <td class="text-end"><?php echo formatNaira($order['shipping_cost']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($order['tax_amount'] > 0): ?>
                                    <tr>
                                        <td>Tax</td>
                                        <td class="text-end"><?php echo formatNaira($order['tax_amount']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>Platform Fee</td>
                                    <td class="text-end text-danger">-<?php echo formatNaira($order['platform_fee']); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?php echo formatNaira($order['total_amount']); ?></td>
                                </tr>
                            </table>
                            <div class="text-muted small">
                                <i class="bi bi-credit-card"></i> Payment: 
                                <strong><?php echo $methodLabel; ?></strong>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-info-circle"></i> Status: 
                                <span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <?php if ($order['payment_id']): ?>
                                <div class="text-muted small">
                                    <i class="bi bi-hash"></i> Txn ID: <?php echo htmlspecialchars($order['payment_id']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Shipping Address</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($order['shipping_state']) || !empty($order['shipping_lga']) || !empty($order['shipping_address'])): ?>
                                <!-- State -->
                                <?php if (!empty($order['shipping_state'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">State</small>
                                        <div class="fw-bold">
                                            <i class="bi bi-geo-alt-fill text-primary"></i>
                                            <?php echo htmlspecialchars($order['shipping_state']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- LGA -->
                                <?php if (!empty($order['shipping_lga'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Local Government Area (LGA)</small>
                                        <div class="fw-bold">
                                            <i class="bi bi-geo-fill text-primary"></i>
                                            <?php echo htmlspecialchars($order['shipping_lga']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Street Address -->
                                <?php if (!empty($order['shipping_address'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Street Address</small>
                                        <div>
                                            <i class="bi bi-house-door text-primary"></i>
                                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <hr>

                                <!-- Tracking -->
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Tracking Number</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                    </div>
                                    <?php if (!empty($order['tracking_carrier'])): ?>
                                        <div>
                                            <small class="text-muted">Carrier</small>
                                            <div><?php echo htmlspecialchars($order['tracking_carrier']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-geo-alt display-4 text-muted"></i>
                                    <p class="text-muted mb-0">No shipping information</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Buyer Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-person"></i> Buyer</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo getProfileImageUrl($order['buyer_profile_pic'] ?? null); ?>" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="ms-2">
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($order['buyer_email']); ?></div>
                                    <?php if ($order['buyer_phone']): ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['buyer_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="<?php echo SITE_URL; ?>/admin/user-detail.php?id=<?php echo $order['buyer_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-eye"></i> View Buyer Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Seller Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-person"></i> Seller</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo getProfileImageUrl($order['seller_profile_pic'] ?? null); ?>" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="ms-2">
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['seller_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($order['seller_email']); ?></div>
                                    <?php if ($order['seller_phone']): ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['seller_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($order['seller_state']) || !empty($order['seller_lga'])): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted">Seller Location</small>
                                    <div class="small">
                                        <i class="bi bi-geo-alt text-primary"></i>
                                        <?php 
                                        $sellerLocation = [];
                                        if (!empty($order['seller_lga'])) $sellerLocation[] = $order['seller_lga'];
                                        if (!empty($order['seller_state'])) $sellerLocation[] = $order['seller_state'] . ' State';
                                        echo htmlspecialchars(implode(', ', $sellerLocation));
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <a href="<?php echo SITE_URL; ?>/admin/user-detail.php?id=<?php echo $order['seller_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-eye"></i> View Seller Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-gear"></i> Admin Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($order['order_status'] == 'pending'): ?>
                                    <button class="btn btn-success update-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-status="confirmed">
                                        <i class="bi bi-check-circle"></i> Confirm Order
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['order_status'] == 'confirmed'): ?>
                                    <button class="btn btn-primary update-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-status="shipped">
                                        <i class="bi bi-truck"></i> Mark as Shipped
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['order_status'] == 'shipped'): ?>
                                    <button class="btn btn-info update-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-status="delivered">
                                        <i class="bi bi-box"></i> Mark as Delivered
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['order_status'] == 'delivered'): ?>
                                    <button class="btn btn-success update-status-btn" 
                                            data-order-id="<?php echo $order['id']; ?>" 
                                            data-status="completed">
                                        <i class="bi bi-check-all"></i> Complete Order
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['order_status'], ['pending', 'confirmed', 'shipped'])): ?>
                                    <button class="btn btn-danger cancel-order-btn" 
                                            data-order-id="<?php echo $order['id']; ?>">
                                        <i class="bi bi-x-circle"></i> Cancel Order
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['open_disputes'] > 0): ?>
                                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?order_id=<?php echo $order['id']; ?>" 
                                       class="btn btn-warning">
                                        <i class="bi bi-exclamation-triangle"></i> View Disputes (<?php echo $order['open_disputes']; ?>)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update order status
    document.querySelectorAll('.update-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const status = this.dataset.status;
            
            if (confirm('Update order status to ' + status + '?')) {
                fetch('<?php echo SITE_URL; ?>/api/orders/update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + orderId + '&status=' + status + '&csrf_token=<?php echo $csrfToken; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update order status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        });
    });

    // Cancel order
    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            
            if (confirm('Are you sure you want to cancel this order? This cannot be undone.')) {
                fetch('<?php echo SITE_URL; ?>/api/orders/cancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + orderId + '&csrf_token=<?php echo $csrfToken; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel order');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>