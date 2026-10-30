<?php
// marketplace/admin/disputes.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($status)) {
    $whereConditions[] = "d.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $whereConditions[] = "d.dispute_type = ?";
    $params[] = $type;
}

if ($orderId > 0) {
    $whereConditions[] = "d.order_id = ?";
    $params[] = $orderId;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM disputes d $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get disputes
$query = "
    SELECT d.*, 
           u.full_name as raised_by_name,
           u.email as raised_by_email,
           u.user_type as raised_by_type,
           u2.full_name as resolved_by_name,
           o.order_number,
           o.total_amount,
           l.title as listing_title
    FROM disputes d
    JOIN users u ON d.raised_by = u.id
    LEFT JOIN users u2 ON d.resolved_by = u2.id
    JOIN orders o ON d.order_id = o.id
    JOIN listings l ON o.listing_id = l.id
    $whereClause
    ORDER BY 
        CASE WHEN d.status = 'open' THEN 0 
             WHEN d.status = 'under_review' THEN 1 
             ELSE 2 END,
        d.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$disputes = $db->fetchAll($query, $queryParams);

// Get status counts for filters
$statusCounts = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM disputes GROUP BY status"
);
$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['status']] = $sc['count'];
}

// Get type counts
$typeCounts = $db->fetchAll(
    "SELECT dispute_type, COUNT(*) as count FROM disputes GROUP BY dispute_type"
);
$typeCountMap = [];
foreach ($typeCounts as $tc) {
    $typeCountMap[$tc['dispute_type']] = $tc['count'];
}

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
                <h1 class="h2"><i class="bi bi-exclamation-triangle"></i> Disputes</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total disputes</span>
                </div>
            </div>

            <!-- Status Quick Filters -->
            <div class="mb-4">
                <div class="btn-group flex-wrap" role="group">
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php" class="btn btn-outline-secondary <?php echo empty($status) ? 'active' : ''; ?>">
                        All (<?php echo array_sum($statusCountMap); ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?status=open" class="btn btn-outline-danger <?php echo $status == 'open' ? 'active' : ''; ?>">
                        Open (<?php echo $statusCountMap['open'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?status=under_review" class="btn btn-outline-warning <?php echo $status == 'under_review' ? 'active' : ''; ?>">
                        Under Review (<?php echo $statusCountMap['under_review'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?status=resolved" class="btn btn-outline-success <?php echo $status == 'resolved' ? 'active' : ''; ?>">
                        Resolved (<?php echo $statusCountMap['resolved'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/disputes.php?status=closed" class="btn btn-outline-secondary <?php echo $status == 'closed' ? 'active' : ''; ?>">
                        Closed (<?php echo $statusCountMap['closed'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/disputes.php" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Dispute Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <?php foreach ($typeCountMap as $key => $count): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $type == $key ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $key)); ?> (<?php echo $count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Order ID</label>
                            <input type="number" class="form-control" name="order_id" 
                                   placeholder="Enter order ID..." 
                                   value="<?php echo $orderId > 0 ? $orderId : ''; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/disputes.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Disputes Table -->
            <?php if (empty($disputes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <h3 class="mt-3">No disputes found</h3>
                    <p class="text-muted">Everything is peaceful! 🎉</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Order</th>
                                <th>Item</th>
                                <th>Raised By</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputes as $dispute): ?>
                                <tr class="<?php echo $dispute['status'] == 'open' ? 'table-danger' : ($dispute['status'] == 'under_review' ? 'table-warning' : ''); ?>">
                                    <td>#<?php echo $dispute['id']; ?></td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $dispute['order_id']; ?>">
                                            #<?php echo $dispute['order_number']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 150px;">
                                            <?php echo htmlspecialchars($dispute['listing_title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($dispute['raised_by_name']); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($dispute['raised_by_type']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $dispute['dispute_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $dispute['status'] == 'open' ? 'danger' : ($dispute['status'] == 'under_review' ? 'warning' : ($dispute['status'] == 'resolved' ? 'success' : 'secondary')); ?>">
                                            <?php echo ucfirst($dispute['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo formatNaira($dispute['total_amount']); ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo timeAgo($dispute['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo SITE_URL; ?>/admin/dispute-detail.php?id=<?php echo $dispute['id']; ?>" 
                                               class="btn btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if (in_array($dispute['status'], ['open', 'under_review'])): ?>
                                                <button class="btn btn-success resolve-dispute-btn" 
                                                        data-dispute-id="<?php echo $dispute['id']; ?>">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-4">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = SITE_URL . '/admin/disputes.php?' . http_build_query($queryParams);
                        echo paginate($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Resolve dispute quick action
    document.querySelectorAll('.resolve-dispute-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const disputeId = this.dataset.disputeId;
            if (confirm('Mark this dispute as resolved?')) {
                fetch('<?php echo SITE_URL; ?>/api/admin/disputes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=resolve&dispute_id=' + disputeId + '&csrf_token=<?php echo $csrfToken; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to resolve dispute');
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