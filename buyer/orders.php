<?php
// marketplace/buyer/orders.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["o.buyer_id = ?"];
$params = [$userId];

if (!empty($status)) {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM orders o $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get orders
$query = "
    SELECT o.*, 
           l.title as listing_title,
           l.sale_type,
           u.full_name as seller_name,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image,
           (SELECT COUNT(*) FROM reviews WHERE order_id = o.id AND reviewer_id = ?) as reviewed
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u ON o.seller_id = u.id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge([$userId], $params, [$perPage, $offset]);
$orders = $db->fetchAll($query, $queryParams);

// Get order status counts
$statusCounts = $db->fetchAll(
    "SELECT order_status, COUNT(*) as count 
     FROM orders 
     WHERE buyer_id = ? 
     GROUP BY order_status",
    [$userId]
);

$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['order_status']] = $sc['count'];
}

$pageJS = 'orders.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-box"></i> My Orders</h1>
                <span class="text-muted"><?php echo $totalItems; ?> total orders</span>
            </div>

            <!-- Status Filters -->
            <ul class="nav nav-pills mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo empty($status) ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php">
                        All (<?php echo array_sum($statusCountMap); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'pending' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=pending">
                        Pending (<?php echo $statusCountMap['pending'] ?? 0; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'confirmed' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=confirmed">
                        Confirmed (<?php echo $statusCountMap['confirmed'] ?? 0; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'shipped' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=shipped">
                        Shipped (<?php echo $statusCountMap['shipped'] ?? 0; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'delivered' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=delivered">
                        Delivered (<?php echo $statusCountMap['delivered'] ?? 0; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'completed' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=completed">
                        Completed (<?php echo $statusCountMap['completed'] ?? 0; ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status == 'cancelled' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/orders.php?status=cancelled">
                        Cancelled (<?php echo $statusCountMap['cancelled'] ?? 0; ?>)
                    </a>
                </li>
            </ul>

            <!-- Orders List -->
            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box display-1 text-muted"></i>
                    <h3 class="mt-3">No orders found</h3>
                    <p class="text-muted">You haven't placed any orders yet.</p>
                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($orders as $order): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-2">
                                            <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="<?php echo htmlspecialchars($order['listing_title']); ?>"
                                                 style="max-height: 100px; object-fit: cover;">
                                        </div>
                                        <div class="col-md-4">
                                            <h5 class="mb-1">
                                                <a href="<?php echo SITE_URL; ?>/buyer/order-detail.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($order['listing_title']); ?>
                                                </a>
                                            </h5>
                                            <div class="text-muted small">
                                                Seller: <?php echo htmlspecialchars($order['seller_name']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                Order #<?php echo $order['order_number']; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="fw-bold"><?php echo formatNaira($order['total_amount']); ?></div>
                                            <div class="text-muted small"><?php echo formatDate($order['created_at']); ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                            <?php if ($order['order_status'] == 'shipped' && $order['tracking_number']): ?>
                                                <div class="text-muted small">
                                                    <i class="bi bi-truck"></i> <?php echo htmlspecialchars($order['tracking_number']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <a href="<?php echo SITE_URL; ?>/buyer/order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if ($order['order_status'] == 'delivered'): ?>
                                                <button class="btn btn-sm btn-success confirm-delivery-btn mt-1" 
                                                        data-order-id="<?php echo $order['id']; ?>">
                                                    <i class="bi bi-check"></i> Confirm
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($order['order_status'] == 'completed' && !$order['reviewed']): ?>
                                                <a href="<?php echo SITE_URL; ?>/buyer/reviews.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-warning mt-1">
                                                    <i class="bi bi-star"></i> Review
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-4">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = SITE_URL . '/buyer/orders.php?' . http_build_query($queryParams);
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
    // Confirm delivery
    document.querySelectorAll('.confirm-delivery-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            
            if (confirm('Confirm delivery of this order?')) {
                fetch('<?php echo SITE_URL; ?>/api/orders/update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + orderId + '&status=completed&csrf_token=<?php echo Session::generateCSRFToken(); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to confirm delivery');
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