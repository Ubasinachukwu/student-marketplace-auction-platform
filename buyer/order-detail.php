<?php
// marketplace/buyer/order-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireLogin();

$db = db();
$userId = Session::getUserId();
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    setError('Invalid order ID');
    redirect(SITE_URL . '/buyer/orders.php');
}

$order = $db->fetchOne(
    "SELECT o.*, 
            l.title as listing_title,
            l.description as listing_description,
            l.condition as listing_condition,
            l.sale_type,
            u.full_name as seller_name,
            u.email as seller_email,
            u.phone as seller_phone,
            u.profile_pic as seller_profile_pic,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image,
            (SELECT COUNT(*) FROM reviews WHERE order_id = o.id AND reviewer_id = ?) as has_reviewed
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.seller_id = u.id
     WHERE o.id = ? AND o.buyer_id = ?",
    [$userId, $orderId, $userId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/buyer/orders.php');
}

// ---- Dispute status (NEW) -----------------------------------------
$existingDispute = $db->fetchOne(
    "SELECT id, status, dispute_type, created_at 
     FROM disputes 
     WHERE order_id = ? AND raised_by = ?
     ORDER BY id DESC LIMIT 1",
    [$orderId, $userId]
);

$openDispute = $db->fetchOne(
    "SELECT id, status FROM disputes 
     WHERE order_id = ? AND status IN ('open', 'under_review')
     ORDER BY id DESC LIMIT 1",
    [$orderId]
);

$canRaiseDispute = in_array($order['order_status'], ['delivered', 'completed'], true)
                && !$openDispute;

// Timeline
$timeline = [
    ['status' => 'Order Placed', 'date' => $order['created_at'], 'icon' => 'bi-cart'],
];
if (in_array($order['order_status'], ['confirmed','shipped','delivered','completed'], true)) {
    $timeline[] = ['status' => 'Order Confirmed', 'date' => $order['updated_at'], 'icon' => 'bi-check-circle'];
}
if (in_array($order['order_status'], ['shipped','delivered','completed'], true)) {
    $timeline[] = ['status' => 'Shipped', 'date' => $order['shipped_date'] ?? $order['updated_at'], 'icon' => 'bi-truck'];
}
if (in_array($order['order_status'], ['delivered','completed'], true)) {
    $timeline[] = ['status' => 'Delivered', 'date' => $order['delivered_date'] ?? $order['updated_at'], 'icon' => 'bi-box'];
}
if ($order['order_status'] === 'completed') {
    $timeline[] = ['status' => 'Completed', 'date' => $order['updated_at'], 'icon' => 'bi-check-all'];
}
if ($order['order_status'] === 'cancelled') {
    $timeline[] = ['status' => 'Cancelled', 'date' => $order['updated_at'], 'icon' => 'bi-x-circle'];
}

$pageJS = 'order-detail.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="row">
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h1>Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                            <p class="text-muted">Placed on <?php echo formatDateTime($order['created_at']); ?></p>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?> fs-6 p-2">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Existing dispute banner (NEW) -->
                    <?php if ($openDispute): ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle fs-4 me-2"></i>
                            <div>
                                <strong>A dispute is currently <?php echo htmlspecialchars($openDispute['status'] === 'open' ? 'open' : 'under review'); ?> on this order.</strong>
                                <div class="small">An admin will review it and contact both parties.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-clock-history"></i> Order Timeline</h6>
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

                    <!-- Item -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-box"></i> Item Details</h6></div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-3">
                                    <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                         class="img-fluid rounded" alt="">
                                </div>
                                <div class="col-md-9">
                                    <h5><?php echo htmlspecialchars($order['listing_title']); ?></h5>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['listing_description'])); ?></p>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <small class="text-muted">Condition</small>
                                            <div><?php echo htmlspecialchars(getConditionLabel($order['listing_condition'])); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Sale Type</small>
                                            <div><?php echo htmlspecialchars(ucfirst($order['sale_type'])); ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Price</small>
                                            <div class="fw-bold"><?php echo formatNaira($order['total_amount']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seller -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-person"></i> Seller Information</h6></div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo getProfileImageUrl($order['seller_profile_pic'] ?? null); ?>" 
                                     class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover;" alt="">
                                <div class="ms-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['seller_name']); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($order['seller_email']); ?></div>
                                    <?php if ($order['seller_phone']): ?>
                                        <div class="text-muted small"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['seller_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-auto">
                                    <a href="<?php echo SITE_URL; ?>/buyer/messages.php?user=<?php echo $order['seller_id']; ?>&order=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-envelope"></i> Message
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Info -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-geo-alt"></i> Shipping Information</h6></div>
                        <div class="card-body">
                            <?php if ($order['shipping_address']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Shipping Address</small>
                                    <div><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order['preferred_carrier'])): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Your Preferred Carrier</small>
                                    <div class="fw-bold text-primary">
                                        <i class="bi bi-truck"></i>
                                        <?php echo htmlspecialchars(NG_DELIVERY_CARRIERS[$order['preferred_carrier']] ?? $order['preferred_carrier']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($order['tracking_number']): ?>
                                <div class="pt-3 border-top">
                                    <small class="text-muted">Tracking Number</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                    <?php if ($order['tracking_carrier']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Carrier</small>
                                            <div><?php echo htmlspecialchars(NG_DELIVERY_CARRIERS[$order['tracking_carrier']] ?? $order['tracking_carrier']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['shipped_date']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Shipped On</small>
                                            <div><?php echo formatDateTime($order['shipped_date']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-receipt"></i> Order Summary</h6></div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>Item Price</td>
                                    <td class="text-end"><?php echo formatNaira($order['total_amount'] - $order['shipping_cost'] - $order['tax_amount']); ?></td>
                                </tr>
                                <?php if ($order['shipping_cost'] > 0): ?>
                                    <tr><td>Shipping</td><td class="text-end"><?php echo formatNaira($order['shipping_cost']); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($order['tax_amount'] > 0): ?>
                                    <tr><td>Tax</td><td class="text-end"><?php echo formatNaira($order['tax_amount']); ?></td></tr>
                                <?php endif; ?>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?php echo formatNaira($order['total_amount']); ?></td>
                                </tr>
                            </table>
                            <div class="text-muted small">
                                <i class="bi bi-credit-card"></i> Payment: <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?>
                                <?php if ($order['payment_method']): ?>
                                    (<?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-gear"></i> Actions</h6></div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($order['order_status'] == 'pending'): ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-clock"></i> Waiting for seller confirmation
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'shipped'): ?>
                                    <button class="btn btn-success confirm-delivery-btn" data-order-id="<?php echo $order['id']; ?>">
                                        <i class="bi bi-check-circle"></i> Confirm Delivery
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'delivered'): ?>
                                    <button class="btn btn-success confirm-delivery-btn" data-order-id="<?php echo $order['id']; ?>">
                                        <i class="bi bi-check-all"></i> Complete Order
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($order['order_status'], ['pending', 'confirmed'], true)): ?>
                                    <button class="btn btn-danger cancel-order-btn" data-order-id="<?php echo $order['id']; ?>">
                                        <i class="bi bi-x-circle"></i> Cancel Order
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'completed' && !$order['has_reviewed']): ?>
                                    <a href="<?php echo SITE_URL; ?>/buyer/reviews.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning">
                                        <i class="bi bi-star"></i> Leave Review
                                    </a>
                                <?php endif; ?>

                                <!-- NEW: Report a Problem -->
                                <?php if ($canRaiseDispute): ?>
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disputeModal">
                                        <i class="bi bi-exclamation-triangle"></i> Report a Problem
                                    </button>
                                <?php elseif ($existingDispute): ?>
                                    <button class="btn btn-outline-warning" disabled>
                                        <i class="bi bi-exclamation-circle"></i> Dispute <?php echo htmlspecialchars(ucfirst($existingDispute['status'])); ?>
                                    </button>
                                <?php endif; ?>

                                <a href="<?php echo SITE_URL; ?>/buyer/download-receipt.php?order_id=<?php echo $order['id']; ?>" 
                                   class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> Download Receipt (PDF)
                                </a>

                                <a href="<?php echo SITE_URL; ?>/buyer/orders.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dispute Modal (NEW) -->
<?php if ($canRaiseDispute): ?>
<div class="modal fade" id="disputeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="disputeForm">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Report a Problem</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">

                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i>
                        Please try messaging the seller first. Disputes are reviewed by our team and may take 24–48 hours.
                    </div>

                    <div class="mb-3">
                        <label for="dispute_type" class="form-label">What's the problem? <span class="text-danger">*</span></label>
                       <select class="form-select" id="dispute_type" name="dispute_type" required>
    <option value="">Select a reason...</option>
    <optgroup label="Item issues">
        <option value="item_not_received">Item never arrived</option>
        <option value="item_not_as_described">Item not as described</option>
    </optgroup>
    <optgroup label="Payment">
        <option value="payment_issue">Payment issue with my order</option>
    </optgroup>
    <optgroup label="Other">
        <option value="other">Other (please describe)</option>
    </optgroup>
</select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Describe the issue <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" 
                                  minlength="10" maxlength="2000" required
                                  placeholder="Please explain what happened with this order in detail..."></textarea>
                        <small class="text-muted">Between 10 and 2000 characters.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-send"></i> Submit Dispute
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delivery-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (!confirm('Confirm delivery of this order?')) return;
            fetch('<?php echo SITE_URL; ?>/api/orders/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'order_id=' + encodeURIComponent(orderId) 
                    + '&status=completed'
                    + '&csrf_token=<?php echo Session::generateCSRFToken(); ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert(data.message || 'Failed to confirm delivery');
            })
            .catch(err => { console.error('Error:', err); alert('An error occurred. Please try again.'); });
        });
    });

    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (!confirm('Are you sure you want to cancel this order? This cannot be undone.')) return;
            fetch('<?php echo SITE_URL; ?>/api/orders/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'order_id=' + encodeURIComponent(orderId)
                    + '&csrf_token=<?php echo Session::generateCSRFToken(); ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert(data.message || 'Failed to cancel order');
            })
            .catch(err => { console.error('Error:', err); alert('An error occurred. Please try again.'); });
        });
    });

    // ---- Dispute form submit (NEW) --------------------------------
    const disputeForm = document.getElementById('disputeForm');
    if (disputeForm) {
        disputeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

            fetch('<?php echo SITE_URL; ?>/api/dispute/create.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Dispute submitted successfully.');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to submit dispute');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>