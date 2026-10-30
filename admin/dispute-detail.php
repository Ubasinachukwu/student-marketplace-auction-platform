<?php
// marketplace/admin/dispute-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';   // sendEmail() + renderEmailTemplate()

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;
$disputeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$disputeId) {
    setError('Invalid dispute ID');
    redirect(base_url('admin/disputes.php'));
}

// Get dispute details
$dispute = $db->fetchOne(
    "SELECT d.*, 
            u.full_name as raised_by_name,
            u.email as raised_by_email,
            u.user_type as raised_by_type,
            u2.full_name as resolved_by_name,
            o.order_number,
            o.total_amount,
            o.order_status,
            o.shipping_address,
            o.tracking_number,
            o.buyer_id,
            o.seller_id,
            l.title as listing_title,
            l.id as listing_id,
            l.sale_type,
            l.price,
            l.current_bid,
            u3.full_name as seller_name,
            u3.email as seller_email,
            u4.full_name as buyer_name,
            u4.email as buyer_email
     FROM disputes d
     JOIN users u ON d.raised_by = u.id
     LEFT JOIN users u2 ON d.resolved_by = u2.id
     JOIN orders o ON d.order_id = o.id
     JOIN listings l ON o.listing_id = l.id
     JOIN users u3 ON o.seller_id = u3.id
     JOIN users u4 ON o.buyer_id = u4.id
     WHERE d.id = ?",
    [$disputeId]
);

if (!$dispute) {
    setError('Dispute not found');
    redirect(base_url('admin/disputes.php'));
}

// Get order messages
$messages = $db->fetchAll(
    "SELECT m.*, u.full_name as sender_name, u.user_type as sender_type
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.order_id = ? OR m.listing_id = ?
     ORDER BY m.created_at ASC",
    [$dispute['order_id'], $dispute['listing_id']]
);

// Handle dispute resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
    }

    $action         = sanitize($_POST['action']);
    $resolutionNotes = trim((string)($_POST['resolution_notes'] ?? ''));
    $refundAmount   = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : 0;

    // Basic validation
    if ($resolutionNotes === '') {
        setError('Resolution notes are required');
        redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
    }

    if ($refundAmount < 0) {
        setError('Refund amount cannot be negative');
        redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
    }

    if ($refundAmount > (float)$dispute['total_amount']) {
        setError('Refund cannot exceed order total of ' . formatNaira($dispute['total_amount']));
        redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
    }

    try {
        $db->beginTransaction();

        if ($action === 'resolve') {
            $db->update(
                "UPDATE disputes SET 
                    status = 'resolved', 
                    resolution_notes = ?, 
                    resolved_by = ?, 
                    resolved_at = NOW() 
                 WHERE id = ?",
                [$resolutionNotes, Session::getUserId(), $disputeId]
            );

            // If refund is issued
            if ($refundAmount > 0) {
                // Create refund transaction
                $db->insert(
                    "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
                     VALUES (?, ?, 'refund', ?, 'Refund from dispute resolution', 'completed')",
                    [$dispute['order_id'], $dispute['raised_by'], $refundAmount]
                );

                // Update order total
                // NOTE: this reduces the recorded order total by the refund amount.
                // If `orders.total_amount` is meant to be the historical paid amount,
                // remove this UPDATE and rely solely on the transactions table.
                $db->update(
                    "UPDATE orders SET total_amount = total_amount - ? WHERE id = ?",
                    [$refundAmount, $dispute['order_id']]
                );
            }

            // Log admin action (corrected — no string literal inside SQL)
            $db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, 'resolve_dispute', 'dispute', ?, ?)",
                [
                    Session::getUserId(),
                    $disputeId,
                    'Dispute resolved. Notes: ' . $resolutionNotes
                ]
            );

            $db->commit();

        } elseif ($action === 'close') {
            $db->update(
                "UPDATE disputes SET 
                    status = 'closed', 
                    resolution_notes = ?, 
                    resolved_by = ?, 
                    resolved_at = NOW() 
                 WHERE id = ?",
                [$resolutionNotes, Session::getUserId(), $disputeId]
            );

            // Log admin action
            $db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, 'close_dispute', 'dispute', ?, ?)",
                [
                    Session::getUserId(),
                    $disputeId,
                    'Dispute closed. Notes: ' . $resolutionNotes
                ]
            );

            $db->commit();

        } else {
            throw new Exception('Invalid action');
        }

    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        setError('Failed to process dispute: ' . $e->getMessage());
        redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
    }

    // ============================================
    // POST-COMMIT: notifications + emails
    // ============================================
    try {
        $statusLabel = $action === 'resolve' ? 'resolved' : 'closed';

        // In-app notifications
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'dispute', ?, ?, ?)",
            [
                $dispute['buyer_id'],
                'Dispute ' . ucfirst($statusLabel),
                "Your dispute on order #{$dispute['order_number']} has been {$statusLabel}.",
                '/buyer/order-detail.php?id=' . $dispute['order_id'],
            ]
        );

        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'dispute', ?, ?, ?)",
            [
                $dispute['seller_id'],
                'Dispute ' . ucfirst($statusLabel),
                "A dispute on order #{$dispute['order_number']} has been {$statusLabel}.",
                '/seller/order-detail.php?id=' . $dispute['order_id'],
            ]
        );

        // Emails
        $refundLine = $refundAmount > 0
            ? "\nRefund issued: " . formatNaira($refundAmount) . "\n"
            : '';

        if (!empty($dispute['buyer_email'])) {
            $buyerSubject = 'Dispute ' . $statusLabel . ' — Order #' . $dispute['order_number'];
            $buyerBody =
                "Hi " . $dispute['buyer_name'] . ",\n\n" .
                "Your dispute on order #{$dispute['order_number']} for \"{$dispute['listing_title']}\" has been {$statusLabel}.\n\n" .
                "Resolution notes:\n{$resolutionNotes}\n" .
                $refundLine . "\n" .
                "View your order: " . SITE_URL . "/buyer/order-detail.php?id=" . $dispute['order_id'] . "\n\n" .
                SITE_NAME . " Team";
            sendEmail($dispute['buyer_email'], $buyerSubject, nl2br($buyerBody), $dispute['buyer_name']);
        }

        if (!empty($dispute['seller_email'])) {
            $sellerSubject = 'Dispute ' . $statusLabel . ' — Order #' . $dispute['order_number'];
            $sellerBody =
                "Hi " . $dispute['seller_name'] . ",\n\n" .
                "A dispute on order #{$dispute['order_number']} for \"{$dispute['listing_title']}\" has been {$statusLabel}.\n\n" .
                "Resolution notes:\n{$resolutionNotes}\n" .
                $refundLine . "\n" .
                "View the order: " . SITE_URL . "/seller/order-detail.php?id=" . $dispute['order_id'] . "\n\n" .
                SITE_NAME . " Team";
            sendEmail($dispute['seller_email'], $sellerSubject, nl2br($sellerBody), $dispute['seller_name']);
        }
    } catch (Throwable $e) {
        // Don't fail the whole action if emails fail — just log it
        error_log('Dispute notification error: ' . $e->getMessage());
    }

    setSuccess('Dispute ' . $statusLabel . ' successfully');
    redirect(base_url('admin/dispute-detail.php?id=' . $disputeId));
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-exclamation-triangle"></i> Dispute #<?php echo $dispute['id']; ?></h1>
                <div>
                    <span class="badge bg-<?php echo $dispute['status'] == 'open' ? 'danger' : ($dispute['status'] == 'under_review' ? 'warning' : ($dispute['status'] == 'resolved' ? 'success' : 'secondary')); ?> fs-6">
                        <?php echo ucfirst($dispute['status']); ?>
                    </span>
                    <a href="<?php echo base_url('admin/disputes.php'); ?>" class="btn btn-sm btn-secondary ms-2">
                        <i class="bi bi-arrow-left"></i> Back to Disputes
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Dispute Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Dispute Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small">Raised By</div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($dispute['raised_by_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($dispute['raised_by_email']); ?></div>
                                    <div><span class="badge bg-info"><?php echo ucfirst($dispute['raised_by_type']); ?></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Order</div>
                                    <div class="fw-bold">#<?php echo $dispute['order_number']; ?></div>
                                    <div class="text-muted small">Total: <?php echo formatNaira($dispute['total_amount']); ?></div>
                                    <div><span class="badge bg-<?php echo getStatusBadgeClass($dispute['order_status']); ?>"><?php echo ucfirst($dispute['order_status']); ?></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Dispute Type</div>
                                    <div class="fw-bold"><?php echo ucfirst(str_replace('_', ' ', $dispute['dispute_type'])); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Created</div>
                                    <div><?php echo formatDateTime($dispute['created_at']); ?></div>
                                    <div class="text-muted small"><?php echo timeAgo($dispute['created_at']); ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small">Description</div>
                                    <div class="p-2 bg-light rounded"><?php echo nl2br(htmlspecialchars($dispute['description'])); ?></div>
                                </div>
                                <?php if ($dispute['resolution_notes']): ?>
                                    <div class="col-12">
                                        <div class="text-muted small">Resolution Notes</div>
                                        <div class="p-2 bg-success bg-opacity-10 rounded"><?php echo nl2br(htmlspecialchars($dispute['resolution_notes'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($dispute['resolved_by']): ?>
                                    <div class="col-12">
                                        <div class="text-muted small">Resolved By</div>
                                        <div><?php echo htmlspecialchars($dispute['resolved_by_name']); ?> on <?php echo formatDateTime($dispute['resolved_at']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-box"></i> Order Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small">Item</div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($dispute['listing_title']); ?></div>
                                    <a href="<?php echo base_url('buyer/listing-detail.php?id=' . $dispute['listing_id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                        <i class="bi bi-eye"></i> View Listing
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Seller</div>
                                    <div><?php echo htmlspecialchars($dispute['seller_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($dispute['seller_email']); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Buyer</div>
                                    <div><?php echo htmlspecialchars($dispute['buyer_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($dispute['buyer_email']); ?></div>
                                </div>
                                <?php if ($dispute['shipping_address']): ?>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Shipping Address</div>
                                        <div><?php echo nl2br(htmlspecialchars($dispute['shipping_address'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($dispute['tracking_number']): ?>
                                    <div class="col-12">
                                        <div class="text-muted small">Tracking Number</div>
                                        <div><?php echo htmlspecialchars($dispute['tracking_number']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-chat"></i> Communication History</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-4">
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
                                                <div class="fw-bold small">
                                                    <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($msg['sender_type']); ?></span>
                                                </div>
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
                    <!-- Resolution Form -->
                    <?php if (in_array($dispute['status'], ['open', 'under_review'])): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-check-circle"></i> Resolve Dispute</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="resolution_notes" class="form-label">Resolution Notes</label>
                                        <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="3" required></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="refund_amount" class="form-label">Refund Amount (Optional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" class="form-control" id="refund_amount" name="refund_amount" 
                                                   step="0.01" min="0" max="<?php echo $dispute['total_amount']; ?>" 
                                                   placeholder="0.00">
                                        </div>
                                        <small class="text-muted">Max: <?php echo formatNaira($dispute['total_amount']); ?></small>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="resolve" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Resolve Dispute
                                        </button>
                                        <button type="submit" name="action" value="close" class="btn btn-secondary">
                                            <i class="bi bi-x-circle"></i> Close Without Resolution
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-link"></i> Quick Links</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo base_url('admin/order-detail.php?id=' . $dispute['order_id']); ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-box"></i> View Order
                                </a>
                                <a href="<?php echo base_url('admin/user-detail.php?id=' . $dispute['raised_by']); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-person"></i> View Raised By Profile
                                </a>
                                <a href="<?php echo base_url('admin/user-detail.php?id=' . $dispute['seller_id']); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-person"></i> View Seller Profile
                                </a>
                                <a href="<?php echo base_url('admin/user-detail.php?id=' . $dispute['buyer_id']); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-person"></i> View Buyer Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>