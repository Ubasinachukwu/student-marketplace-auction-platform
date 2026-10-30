<?php
// marketplace/admin/transactions.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR t.description LIKE ? OR t.gateway_transaction_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($type)) {
    $whereConditions[] = "t.type = ?";
    $params[] = $type;
}

if (!empty($status)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(t.created_at) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM transactions t 
               JOIN users u ON t.user_id = u.id 
               $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get transactions
$query = "
    SELECT t.*, 
           u.full_name as user_name,
           u.email as user_email,
           u.user_type,
           o.order_number
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN orders o ON t.order_id = o.id
    $whereClause
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$transactions = $db->fetchAll($query, $queryParams);

// Get summary stats
$summary = $db->fetchOne(
    "SELECT 
        COALESCE(SUM(CASE WHEN type = 'payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_payments,
        COALESCE(SUM(CASE WHEN type = 'payout' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_payouts,
        COALESCE(SUM(CASE WHEN type = 'fee' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_fees,
        COALESCE(SUM(CASE WHEN type = 'refund' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_refunds
    FROM transactions
    WHERE DATE(created_at) BETWEEN ? AND ?
    ",
    [$dateFrom, $dateTo]
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
                <h1 class="h2"><i class="bi bi-cash-coin"></i> Transactions</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total transactions</span>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($summary['total_payments']); ?></div>
                                <div>Total Payments</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($summary['total_payouts']); ?></div>
                                <div>Total Payouts</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($summary['total_fees']); ?></div>
                                <div>Total Fees</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-percent"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($summary['total_refunds']); ?></div>
                                <div>Total Refunds</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/transactions.php" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="User, description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <option value="payment" <?php echo $type == 'payment' ? 'selected' : ''; ?>>Payment</option>
                                <option value="payout" <?php echo $type == 'payout' ? 'selected' : ''; ?>>Payout</option>
                                <option value="fee" <?php echo $type == 'fee' ? 'selected' : ''; ?>>Fee</option>
                                <option value="refund" <?php echo $type == 'refund' ? 'selected' : ''; ?>>Refund</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
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
                            <a href="<?php echo SITE_URL; ?>/admin/transactions.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-cash-coin display-1 text-muted"></i>
                    <h3 class="mt-3">No transactions found</h3>
                    <p class="text-muted">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td>#<?php echo $txn['id']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($txn['user_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($txn['user_email']); ?></small>
                                        <br>
                                        <span class="badge bg-<?php echo $txn['user_type'] == 'admin' ? 'danger' : 'info'; ?>">
                                            <?php echo ucfirst($txn['user_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $txn['type'] == 'payment' ? 'success' : ($txn['type'] == 'payout' ? 'primary' : ($txn['type'] == 'fee' ? 'warning' : 'danger')); ?>">
                                            <?php echo ucfirst($txn['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($txn['description']); ?>
                                        <?php if ($txn['order_number']): ?>
                                            <br>
                                            <small class="text-muted">Order: #<?php echo $txn['order_number']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="<?php echo in_array($txn['type'], ['payment', 'payout']) ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($txn['type'] == 'payout' || $txn['type'] == 'refund') ? '-' : ''; ?>
                                            <?php echo formatNaira($txn['amount']); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            Balance: <?php echo formatNaira($txn['balance_after'] ?? 0); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $txn['status'] == 'completed' ? 'success' : ($txn['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($txn['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDateTime($txn['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($txn['gateway_transaction_id']): ?>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($txn['gateway_transaction_id']); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
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
                        $baseUrl = SITE_URL . '/admin/transactions.php?' . http_build_query($queryParams);
                        echo paginate($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>