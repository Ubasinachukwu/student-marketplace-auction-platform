<?php
// marketplace/admin/admin-logs.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$targetType = isset($_GET['target_type']) ? sanitize($_GET['target_type']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR al.action LIKE ? OR al.new_value LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($action)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $action;
}

if (!empty($targetType)) {
    $whereConditions[] = "al.target_type = ?";
    $params[] = $targetType;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM admin_logs al 
               JOIN users u ON al.admin_id = u.id 
               $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get logs
$query = "
    SELECT al.*, 
           u.full_name as admin_name,
           u.email as admin_email
    FROM admin_logs al
    JOIN users u ON al.admin_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$logs = $db->fetchAll($query, $queryParams);

// Get unique actions for filter
$actions = $db->fetchAll(
    "SELECT DISTINCT action FROM admin_logs ORDER BY action"
);

// Get target types for filter
$targetTypes = $db->fetchAll(
    "SELECT DISTINCT target_type FROM admin_logs WHERE target_type IS NOT NULL ORDER BY target_type"
);

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
                <h1 class="h2"><i class="bi bi-clock-history"></i> Audit Trail</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total entries</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/admin-logs.php" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Admin, action..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Action</label>
                            <select class="form-select" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $a): ?>
                                    <option value="<?php echo $a['action']; ?>" 
                                            <?php echo $action == $a['action'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $a['action'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Target Type</label>
                            <select class="form-select" name="target_type">
                                <option value="">All Types</option>
                                <?php foreach ($targetTypes as $tt): ?>
                                    <option value="<?php echo $tt['target_type']; ?>" 
                                            <?php echo $targetType == $tt['target_type'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($tt['target_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/admin-logs.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted"></i>
                    <h3 class="mt-3">No logs found</h3>
                    <p class="text-muted">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>#<?php echo $log['id']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($log['admin_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['admin_email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['target_type']): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($log['target_type']); ?>
                                            </span>
                                            <?php if ($log['target_id']): ?>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $log['target_id']; ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($log['new_value'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($log['old_value'])): ?>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-arrow-right"></i> Old: <?php echo htmlspecialchars($log['old_value']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['ip_address']): ?>
                                            <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDateTime($log['created_at']); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($log['created_at']); ?></small>
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
                        $baseUrl = SITE_URL . '/admin/admin-logs.php?' . http_build_query($queryParams);
                        echo paginate($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>