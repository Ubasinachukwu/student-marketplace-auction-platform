<?php
// marketplace/admin/orders.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$paymentStatus = isset($_GET['payment_status']) ? sanitize($_GET['payment_status']) : '';
$stateFilter = isset($_GET['state']) ? sanitize($_GET['state']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(o.order_number LIKE ? OR l.title LIKE ? OR u.full_name LIKE ? OR u2.full_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $status;
}

if (!empty($paymentStatus)) {
    $whereConditions[] = "o.payment_status = ?";
    $params[] = $paymentStatus;
}

if (!empty($stateFilter)) {
    $whereConditions[] = "o.shipping_state = ?";
    $params[] = $stateFilter;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM orders o 
               JOIN listings l ON o.listing_id = l.id 
               JOIN users u ON o.buyer_id = u.id
               JOIN users u2 ON o.seller_id = u2.id
               $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get orders
$query = "
    SELECT o.*, 
           l.title as listing_title,
           u.full_name as buyer_name,
           u.email as buyer_email,
           u2.full_name as seller_name,
           u2.email as seller_email,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image,
           (SELECT COUNT(*) FROM disputes WHERE order_id = o.id AND status IN ('open', 'under_review')) as open_disputes
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u ON o.buyer_id = u.id
    JOIN users u2 ON o.seller_id = u2.id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$orders = $db->fetchAll($query, $queryParams);

// Get status counts for filters
$statusCounts = $db->fetchAll(
    "SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status"
);
$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['order_status']] = $sc['count'];
}

$paymentStatusCounts = $db->fetchAll(
    "SELECT payment_status, COUNT(*) as count FROM orders GROUP BY payment_status"
);
$paymentStatusCountMap = [];
foreach ($paymentStatusCounts as $psc) {
    $paymentStatusCountMap[$psc['payment_status']] = $psc['count'];
}

// Get shipping states with order counts (for filter)
$statesWithOrders = $db->fetchAll(
    "SELECT shipping_state, COUNT(*) as count FROM orders 
     WHERE shipping_state IS NOT NULL AND shipping_state != '' 
     GROUP BY shipping_state 
     ORDER BY shipping_state"
);

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
                <h1 class="h2"><i class="bi bi-box"></i> Orders</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total orders</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/orders.php" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Order #, item, user..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <?php foreach ($statusCountMap as $key => $count): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($key); ?> (<?php echo $count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment_status">
                                <option value="">All Payment</option>
                                <?php foreach ($paymentStatusCountMap as $key => $count): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $paymentStatus == $key ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($key); ?> (<?php echo $count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Shipping State</label>
                            <select class="form-select" name="state">
                                <option value="">All States</option>
                                <?php foreach ($statesWithOrders as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['shipping_state']); ?>" 
                                            <?php echo $stateFilter == $s['shipping_state'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['shipping_state']); ?> (<?php echo $s['count']; ?>)
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
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo SITE_URL; ?>/admin/orders.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box display-1 text-muted"></i>
                    <h3 class="mt-3">No orders found</h3>
                    <p class="text-muted">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Item</th>
                                <th>Buyer</th>
                                <th>Ship To</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $methodLabels = [
                                    'pay_on_delivery' => 'Pay on Delivery',
                                    'paystack' => 'Paystack',
                                    'flutterwave' => 'Flutterwave'
                                    
                                ];
                                $methodLabel = $methodLabels[$order['payment_method']] ?? null;
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $order['id']; ?>" class="fw-bold">
                                            <?php echo $order['order_number']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <div class="ms-2 text-truncate" style="max-width: 120px;">
                                                <?php echo htmlspecialchars($order['listing_title']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 100px;">
                                            <?php echo htmlspecialchars($order['buyer_name']); ?>
                                        </div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 100px;">
                                            <?php echo htmlspecialchars($order['buyer_email']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['shipping_state']) || !empty($order['shipping_lga'])): ?>
                                            <div class="small">
                                                <i class="bi bi-geo-alt text-primary"></i>
                                                <?php if (!empty($order['shipping_lga'])): ?>
                                                    <strong><?php echo htmlspecialchars($order['shipping_lga']); ?></strong>
                                                <?php endif; ?>
                                                <?php if (!empty($order['shipping_state'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($order['shipping_state']); ?> State</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <i class="bi bi-geo-alt"></i> Not set
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo formatNaira($order['total_amount']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <?php if ($order['open_disputes'] > 0): ?>
                                            <br>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-triangle"></i> <?php echo $order['open_disputes']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                        <?php if ($methodLabel): ?>
                                            <br>
                                            <small class="text-muted"><?php echo $methodLabel; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDate($order['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($order['order_status'] == 'pending'): ?>
                                                <button class="btn btn-outline-success update-status-btn" 
                                                        data-order-id="<?php echo $order['id']; ?>" 
                                                        data-status="confirmed"
                                                        title="Confirm order">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($order['order_status'] == 'confirmed'): ?>
                                                <button class="btn btn-outline-primary update-status-btn" 
                                                        data-order-id="<?php echo $order['id']; ?>" 
                                                        data-status="shipped"
                                                        title="Mark as shipped">
                                                    <i class="bi bi-truck"></i>
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
                        $baseUrl = SITE_URL . '/admin/orders.php?' . http_build_query($queryParams);
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
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>