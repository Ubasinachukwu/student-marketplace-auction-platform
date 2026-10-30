<?php
// marketplace/seller/order-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireSeller();

$db = db();
$userId = Session::getUserId();
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    setError('Invalid order ID');
    redirect(SITE_URL . '/seller/orders.php');
}

$order = $db->fetchOne(
    "SELECT o.*, 
            l.title as listing_title,
            l.description as listing_description,
            l.condition as listing_condition,
            l.sale_type,
            u.full_name as buyer_name,
            u.email as buyer_email,
            u.phone as buyer_phone,
            u.address as buyer_address,
            u.profile_pic as buyer_profile_pic,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.buyer_id = u.id
     WHERE o.id = ? AND o.seller_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/seller/orders.php');
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

$messages = $db->fetchAll(
    "SELECT m.*, u.full_name as sender_name, u.user_type as sender_type
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.order_id = ? OR (m.listing_id = ? AND m.sender_id != ?)
     ORDER BY m.created_at ASC",
    [$orderId, $order['listing_id'], $userId]
);

$db->update(
    "UPDATE messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ? AND is_read = 0",
    [$orderId, $userId]
);

$pageJS = 'order-detail.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>

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

                    <!-- Buyer -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-person"></i> Buyer Information</h6></div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo getProfileImageUrl($order['buyer_profile_pic'] ?? null); ?>" 
                                     class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover;" alt="">
                                <div class="ms-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($order['buyer_email']); ?></div>
                                    <?php if ($order['buyer_phone']): ?>
                                        <div class="text-muted small"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['buyer_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-auto">
                                    <a href="<?php echo SITE_URL; ?>/seller/messages.php?user=<?php echo $order['buyer_id']; ?>&order=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-envelope"></i> Message
                                    </a>
                                </div>
                            </div>

                            <?php if ($order['shipping_address']): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">Shipping Address</small>
                                    <div><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>

                                    <?php if (!empty($order['preferred_carrier'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Buyer Prefers Carrier</small>
                                            <div class="fw-bold text-primary">
                                                <i class="bi bi-truck"></i>
                                                <?php echo htmlspecialchars(NG_DELIVERY_CARRIERS[$order['preferred_carrier']] ?? $order['preferred_carrier']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-chat"></i> Communication</h6></div>
                        <div class="card-body">
                            <div class="messages-container" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-chat display-4 text-muted"></i>
                                        <p class="text-muted">No messages yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="d-flex mb-3 <?php echo $msg['sender_id'] == $userId ? 'justify-content-end' : ''; ?>">
                                            <div class="<?php echo $msg['sender_id'] == $userId ? 'order-2 ms-3' : 'order-0 me-3'; ?>">
                                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="<?php echo $msg['sender_id'] == $userId ? 'order-1' : ''; ?>" 
                                                 style="max-width: 70%;">
                                                <div class="card <?php echo $msg['sender_id'] == $userId ? 'bg-primary text-white' : 'bg-light'; ?>">
                                                    <div class="card-body p-2">
                                                        <div class="fw-bold small"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                                        <div><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                                        <small class="<?php echo $msg['sender_id'] == $userId ? 'text-light opacity-75' : 'text-muted'; ?>">
                                                            <?php echo timeAgo($msg['created_at']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (Session::isLoggedIn()): ?>
                                <form id="messageForm" class="mt-3">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="receiver_id" value="<?php echo $order['buyer_id']; ?>">
                                    <input type="hidden" name="listing_id" value="<?php echo $order['listing_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                                    
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="message" placeholder="Type a message..." required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send"></i> Send
                                        </button>
                                    </div>
                                </form>
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
                                <tr>
                                    <td>Platform Fee</td>
                                    <td class="text-end text-danger">-<?php echo formatNaira($order['platform_fee']); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?php echo formatNaira($order['total_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td>Net to You</td>
                                    <td class="text-end text-success"><?php echo formatNaira($order['net_amount']); ?></td>
                                </tr>
                            </table>
                            <div class="text-muted small">
                                <i class="bi bi-credit-card"></i> Payment: 
                                <?php 
                                    $methodLabels = [
                                        'pay_on_delivery' => 'Pay on Delivery (Cash)',
                                        'paystack' => 'Bank Transfer (Paystack)',
                                        'flutterwave' => 'Flutterwave'
                                        
                                    ];
                                    $methodLabel = $methodLabels[$order['payment_method']] ?? ucfirst($order['payment_method'] ?: 'Not specified');
                                    echo htmlspecialchars($methodLabel);
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Info -->
                    <?php if (in_array($order['order_status'], ['shipped', 'delivered'], true)): ?>
                        <div class="card mb-4">
                            <div class="card-header"><h6 class="mb-0"><i class="bi bi-truck"></i> Shipping Information</h6></div>
                            <div class="card-body">
                                <?php if ($order['tracking_number']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Tracking Number</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['tracking_carrier']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Carrier</small>
                                        <div><?php echo htmlspecialchars(NG_DELIVERY_CARRIERS[$order['tracking_carrier']] ?? $order['tracking_carrier']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['shipped_date']): ?>
                                    <div>
                                        <small class="text-muted">Shipped On</small>
                                        <div><?php echo formatDateTime($order['shipped_date']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="bi bi-gear"></i> Actions</h6></div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($order['order_status'] == 'pending'): ?>
                                    <button class="btn btn-success update-status-btn" data-order-id="<?php echo $order['id']; ?>" data-status="confirmed">
                                        <i class="bi bi-check-circle"></i> Confirm Order
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'confirmed'): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shippingModal">
                                        <i class="bi bi-truck"></i> Mark as Shipped
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'shipped'): ?>
                                    <button class="btn btn-info update-status-btn" data-order-id="<?php echo $order['id']; ?>" data-status="delivered">
                                        <i class="bi bi-box"></i> Mark as Delivered
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['order_status'] == 'delivered'): ?>
                                    <button class="btn btn-success update-status-btn" data-order-id="<?php echo $order['id']; ?>" data-status="completed">
                                        <i class="bi bi-check-all"></i> Complete Order
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($order['order_status'], ['pending', 'confirmed'], true)): ?>
                                    <button class="btn btn-danger cancel-order-btn" data-order-id="<?php echo $order['id']; ?>">
                                        <i class="bi bi-x-circle"></i> Cancel Order
                                    </button>
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

                                <a href="<?php echo SITE_URL; ?>/seller/download-receipt.php?order_id=<?php echo $order['id']; ?>" 
                                   class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> Download Receipt (PDF)
                                </a>

                                <?php if (in_array($order['order_status'], ['confirmed', 'shipped'], true)): ?>
                                    <a href="<?php echo SITE_URL; ?>/seller/download-shipping-label.php?order_id=<?php echo $order['id']; ?>" 
                                       class="btn btn-outline-primary" target="_blank">
                                        <i class="bi bi-printer"></i> Print Shipping Label (PDF)
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo SITE_URL; ?>/seller/orders.php" class="btn btn-outline-secondary">
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
                        Please try messaging the buyer first. Disputes are reviewed by our team and may take 24–48 hours.
                    </div>

                    <div class="mb-3">
                        <label for="dispute_type" class="form-label">What's the problem? <span class="text-danger">*</span></label>
                        <select class="form-select" id="dispute_type" name="dispute_type" required>
    <option value="">Select a reason...</option>
    <optgroup label="Payment problems">
        <option value="buyer_no_payment">Buyer has not paid</option>
        <option value="payment_issue">Other payment issue</option>
    </optgroup>
    <optgroup label="Buyer issues">
        <option value="buyer_unresponsive">Buyer is unresponsive</option>
        <option value="buyer_fraudulent_claim">Fraudulent buyer claim</option>
    </optgroup>
    <optgroup label="Delivery issues">
        <option value="shipping_problem">Shipping problem (address, refusal, etc.)</option>
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

<!-- Shipping Modal -->
<div class="modal fade" id="shippingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="shippingForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Update Shipping</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">

                    <?php if (!empty($order['preferred_carrier'])): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle"></i>
                            Buyer requested:
                            <strong><?php echo htmlspecialchars(NG_DELIVERY_CARRIERS[$order['preferred_carrier']] ?? $order['preferred_carrier']); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="tracking_number" class="form-label">
                            Tracking Number <span class="text-muted">(optional)</span>
                        </label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number"
                               placeholder="Leave blank if no tracking code">
                        <small class="text-muted">The code your courier gives you on the receipt</small>
                    </div>
                    <div class="mb-3">
                        <label for="tracking_carrier" class="form-label">Carrier <span class="text-danger">*</span></label>
                        <select class="form-select" id="tracking_carrier" name="tracking_carrier" required>
                            <option value="">Select Carrier</option>
                            <?php foreach (NG_DELIVERY_CARRIERS as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"
                                    <?php echo ($value === ($order['preferred_carrier'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="otherCarrierWrap">
                        <label for="other_carrier" class="form-label">Specify Carrier</label>
                        <input type="text" class="form-control" id="other_carrier" name="other_carrier"
                               placeholder="e.g. Local dispatch rider">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shipping Date</label>
                        <input type="date" class="form-control" name="shipped_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Mark as Shipped
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Config bootstrap for assets/js/order-detail.js -->
<script>
window.OrderDetailConfig = {
    siteUrl:   <?php echo json_encode(SITE_URL); ?>,
    csrfToken: <?php echo json_encode(Session::generateCSRFToken()); ?>
};
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
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