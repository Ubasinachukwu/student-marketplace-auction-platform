<?php
// marketplace/admin/listing-reports.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireRole('admin');

$db = db();
$isAdminPage = true;

$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page   = isset($_GET['page'])   ? max(1, (int)$_GET['page']) : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$whereConditions = ["1=1"];
$params = [];
if (in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $status;
}
$whereClause = "WHERE " . implode(" AND ", $whereConditions);

$totalResult = $db->fetchOne("SELECT COUNT(*) as total FROM reports r $whereClause", $params);
$totalItems  = (int)($totalResult['total'] ?? 0);
$totalPages  = (int)ceil($totalItems / $perPage);

$reports = $db->fetchAll(
    "SELECT r.*, 
            u.full_name AS reporter_name,
            u.email     AS reporter_email,
            l.title     AS listing_title,
            l.status    AS listing_status,
            l.seller_id AS listing_seller_id,
            s.full_name AS seller_name,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) AS listing_image
     FROM reports r
     JOIN users u    ON r.reported_by = u.id
     JOIN listings l ON r.listing_id  = l.id
     JOIN users s    ON l.seller_id   = s.id
     $whereClause
     ORDER BY 
        CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

$statusCounts = $db->fetchAll(
    "SELECT status, COUNT(*) as c FROM reports GROUP BY status"
);
$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['status']] = (int)$sc['c'];
}

$reasonLabels = [
    'scam_or_fraud'          => 'Scam or fraudulent listing',
    'prohibited_item'        => 'Prohibited or illegal item',
    'wrong_category'         => 'Wrong category',
    'misleading_description' => 'Misleading description or photos',
    'duplicate_listing'      => 'Duplicate listing',
    'offensive_content'      => 'Offensive content',
    'other'                  => 'Other',
];

$csrfToken = Session::generateCSRFToken();
$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-flag"></i> Listing Reports</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total reports</span>
                </div>
            </div>

            <!-- Status Filters -->
            <div class="mb-4">
                <div class="btn-group flex-wrap" role="group">
                    <a href="<?php echo SITE_URL; ?>/admin/listing-reports.php" 
                       class="btn btn-outline-secondary <?php echo empty($status) ? 'active' : ''; ?>">
                        All (<?php echo array_sum($statusCountMap); ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listing-reports.php?status=pending" 
                       class="btn btn-outline-danger <?php echo $status === 'pending' ? 'active' : ''; ?>">
                        Pending (<?php echo $statusCountMap['pending'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listing-reports.php?status=reviewed" 
                       class="btn btn-outline-success <?php echo $status === 'reviewed' ? 'active' : ''; ?>">
                        Reviewed (<?php echo $statusCountMap['reviewed'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listing-reports.php?status=dismissed" 
                       class="btn btn-outline-secondary <?php echo $status === 'dismissed' ? 'active' : ''; ?>">
                        Dismissed (<?php echo $statusCountMap['dismissed'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <?php if (empty($reports)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <h3 class="mt-3">No reports found</h3>
                    <p class="text-muted">Nothing to review right now.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Listing</th>
                                <th>Seller</th>
                                <th>Reporter</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <tr class="<?php echo $r['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                    <td>#<?php echo $r['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo getImageUrl($r['listing_image'] ?? null, 'listing'); ?>" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" 
                                                 alt="">
                                            <div class="ms-2">
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $r['listing_id']; ?>" 
                                                   target="_blank" class="text-decoration-none fw-bold small">
                                                    <?php echo htmlspecialchars($r['listing_title']); ?>
                                                </a>
                                                <div class="text-muted small">#<?php echo $r['listing_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/admin/user-detail.php?id=<?php echo $r['listing_seller_id']; ?>">
                                            <?php echo htmlspecialchars($r['seller_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/admin/user-detail.php?id=<?php echo $r['reported_by']; ?>">
                                            <?php echo htmlspecialchars($r['reporter_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($reasonLabels[$r['reason']] ?? $r['reason']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $r['status'] === 'pending' ? 'danger' : ($r['status'] === 'reviewed' ? 'success' : 'secondary'); ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo timeAgo($r['created_at']); ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary view-report-btn"
                                                    data-bs-toggle="modal" data-bs-target="#reportModal"
                                                    data-report-id="<?php echo $r['id']; ?>"
                                                    data-listing-title="<?php echo htmlspecialchars($r['listing_title'], ENT_QUOTES); ?>"
                                                    data-reason="<?php echo htmlspecialchars($reasonLabels[$r['reason']] ?? $r['reason'], ENT_QUOTES); ?>"
                                                    data-description="<?php echo htmlspecialchars($r['description'] ?? '', ENT_QUOTES); ?>"
                                                    data-status="<?php echo $r['status']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <button class="btn btn-success action-report-btn" 
                                                        data-report-id="<?php echo $r['id']; ?>" 
                                                        data-action="reviewed" 
                                                        title="Mark reviewed">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button class="btn btn-secondary action-report-btn" 
                                                        data-report-id="<?php echo $r['id']; ?>" 
                                                        data-action="dismissed" 
                                                        title="Dismiss">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-4">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = SITE_URL . '/admin/listing-reports.php?' . http_build_query($queryParams);
                        echo paginate($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Detail Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-flag"></i> Report Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Listing</dt>
                    <dd class="col-sm-9" id="modalListingTitle"></dd>

                    <dt class="col-sm-3">Reason</dt>
                    <dd class="col-sm-9" id="modalReason"></dd>

                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9" id="modalDescription"></dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9" id="modalStatus"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Populate modal
    document.querySelectorAll('.view-report-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalListingTitle').textContent = this.dataset.listingTitle || '—';
            document.getElementById('modalReason').textContent       = this.dataset.reason || '—';
            document.getElementById('modalDescription').textContent  = this.dataset.description || '(no description provided)';
            document.getElementById('modalStatus').textContent       = this.dataset.status || '—';
        });
    });

    // Action buttons
    document.querySelectorAll('.action-report-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const reportId = this.dataset.reportId;
            const action   = this.dataset.action;
            const label    = action === 'reviewed' ? 'mark as reviewed' : 'dismiss';

            if (!confirm('Are you sure you want to ' + label + ' this report?')) return;

            fetch('<?php echo SITE_URL; ?>/api/admin/listing-reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'report_id=' + encodeURIComponent(reportId)
                    + '&action=' + encodeURIComponent(action)
                    + '&csrf_token=' + encodeURIComponent('<?php echo $csrfToken; ?>')
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update report');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred. Please try again.');
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>