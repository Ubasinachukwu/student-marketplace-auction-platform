<?php
// marketplace/admin/pending-sellers.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireRole('admin');

$db = db();
$adminId = Session::getUserId();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token.');
        redirect(SITE_URL . '/admin/pending-sellers.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    $target = $db->fetchOne("SELECT * FROM users WHERE id = ? AND is_seller = 1", [$userId]);

    if (!$target) {
        setError('Seller not found.');
        redirect(SITE_URL . '/admin/pending-sellers.php');
    }

    try {
        if ($action === 'approve') {
            $db->update(
                "UPDATE users 
                 SET seller_status = 'active', 
                     seller_since = NOW(), 
                     seller_rejected_reason = NULL 
                 WHERE id = ?",
                [$userId]
            );

            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link) 
                 VALUES (?, 'seller_approved', 'Seller Account Approved', 
                         'Your seller account has been approved! You can now list items.', 
                         '/seller/dashboard.php')",
                [$userId]
            );

            if (function_exists('logActivity')) {
                logActivity($adminId, 'seller_approved', 'Approved seller user #' . $userId);
            }

            setSuccess('Seller approved successfully.');

        } elseif ($action === 'reject') {
            $db->update(
                "UPDATE users 
                 SET seller_status = 'rejected', 
                     seller_rejected_reason = ? 
                 WHERE id = ?",
                [$reason ?: 'Application not approved.', $userId]
            );

            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link) 
                 VALUES (?, 'seller_rejected', 'Seller Application Update', 
                         CONCAT('Your seller application was not approved: ', ?), 
                         '/buyer/become-seller.php')",
                [$userId, $reason ?: 'Application not approved.']
            );

            if (function_exists('logActivity')) {
                logActivity($adminId, 'seller_rejected', 'Rejected seller user #' . $userId);
            }

            setSuccess('Seller rejected.');
        }
    } catch (Exception $e) {
        error_log('Seller approval error: ' . $e->getMessage());
        setError('Action failed. Please try again.');
    }

    redirect(SITE_URL . '/admin/pending-sellers.php');
}

// Load pending + recently decided
$pending = $db->fetchAll(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM listings WHERE seller_id = u.id) as listing_count
     FROM users u
     WHERE u.is_seller = 1 AND u.seller_status = 'pending'
     ORDER BY u.seller_since ASC"
);

$recent = $db->fetchAll(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM listings WHERE seller_id = u.id) as listing_count
     FROM users u
     WHERE u.is_seller = 1 AND u.seller_status IN ('active','rejected')
     ORDER BY u.seller_since DESC, u.id DESC
     LIMIT 20"
);

$csrfToken = Session::generateCSRFToken();
$isAdminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2"><i class="bi bi-person-check"></i> Seller Applications</h1>
        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Admin Dashboard
        </a>
    </div>

    <!-- Pending -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-hourglass-split"></i> Pending Approval
                <span class="badge bg-dark"><?php echo count($pending); ?></span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($pending)): ?>
                <p class="text-muted mb-0"><i class="bi bi-check-circle text-success"></i> No pending applications.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Applied</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                        <br><small class="text-muted">ID #<?php echo (int)$u['id']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                            $loc = array_filter([$u['lga'] ?? '', $u['state'] ?? '']);
                                            echo $loc ? htmlspecialchars(implode(', ', $loc)) : '—';
                                        ?>
                                    </td>
                                    <td><small><?php echo formatDateTime($u['created_at']); ?></small></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-sm btn-success"
                                                    onclick="return confirm('Approve this seller?');">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                data-user-id="<?php echo (int)$u['id']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($u['full_name']); ?>">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently decided -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recently Reviewed</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recent)): ?>
                <p class="text-muted mb-0">No recent decisions.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Since</th>
                                <th>Listings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if ($u['seller_status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo $u['seller_since'] ? formatDateTime($u['seller_since']) : '—'; ?></small></td>
                                    <td><?php echo (int)$u['listing_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger"></i> Reject Seller</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" id="rejectUserId">
                    <p>Rejecting <strong id="rejectUserName"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason (optional)</label>
                        <textarea name="reason" class="form-control" rows="3"
                                  placeholder="e.g., Incomplete information, suspected fraud..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Seller
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('rejectUserId').value = btn.dataset.userId;
    document.getElementById('rejectUserName').textContent = btn.dataset.userName;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>