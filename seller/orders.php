<?php
// marketplace/seller/orders.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Get filter parameters
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$page   = isset($_GET['page'])   ? max(1, (int)$_GET['page'])  : 1;
$perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 20;
$offset  = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["o.seller_id = ?"];
$params = [$userId];

if ($status !== '') {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $whereConditions[] = "(o.order_number LIKE ? OR l.title LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM orders o 
               JOIN listings l ON o.listing_id = l.id 
               JOIN users u ON o.buyer_id = u.id 
               $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = (int)($totalResult['total'] ?? 0);
$totalPages = (int)ceil($totalItems / $perPage);

// Get orders
$query = "
    SELECT o.*, 
           l.title as listing_title,
           l.sale_type,
           u.full_name as buyer_name,
           u.email as buyer_email,
           u.phone as buyer_phone,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u ON o.buyer_id = u.id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$orders = $db->fetchAll($query, $queryParams);

// Get order status counts for filters
$statusCounts = $db->fetchAll(
    "SELECT order_status, COUNT(*) as count 
     FROM orders 
     WHERE seller_id = ? 
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

        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>

        <!-- MAIN CONTENT -->
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
                    <form method="GET" action="<?php echo SITE_URL; ?>/seller/orders.php" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Orders</option>
                                <option value="pending"   <?php echo $status == 'pending'   ? 'selected' : ''; ?>>Pending (<?php echo $statusCountMap['pending']   ?? 0; ?>)</option>
                                <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Confirmed (<?php echo $statusCountMap['confirmed'] ?? 0; ?>)</option>
                                <option value="shipped"   <?php echo $status == 'shipped'   ? 'selected' : ''; ?>>Shipped (<?php echo $statusCountMap['shipped']   ?? 0; ?>)</option>
                                <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered (<?php echo $statusCountMap['delivered'] ?? 0; ?>)</option>
                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed (<?php echo $statusCountMap['completed'] ?? 0; ?>)</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled (<?php echo $statusCountMap['cancelled'] ?? 0; ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Order #, item, or buyer..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo SITE_URL; ?>/seller/orders.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>

                                   <a href="<?php echo SITE_URL; ?>/seller/download-receipt.php?order_id=<?php echo $order['id']; ?>" 
       class="btn btn-outline-secondary" 
       title="Download Receipt" 
       target="_blank">
        <i class="bi bi-file-earmark-pdf"></i>
    </a>


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
                    <p class="text-muted">You haven't received any orders yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Item</th>
                                <th>Buyer</th>
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
                                    'paystack'        => 'Paystack',
                                    'flutterwave'     => 'Flutterwave'
                                    
                                ];
                                $methodLabel = $methodLabels[$order['payment_method']] ?? ucfirst($order['payment_method'] ?: 'N/A');
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/seller/order-detail.php?id=<?php echo $order['id']; ?>" class="fw-bold">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                                 alt="">
                                            <div class="ms-2">
                                                <div class="text-truncate" style="max-width: 150px;">
                                                    <?php echo htmlspecialchars($order['listing_title']); ?>
                                                </div>
                                                <?php if ($order['sale_type'] == 'auction' || $order['sale_type'] == 'hybrid'): ?>
                                                    <span class="badge bg-warning text-dark">Auction</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['buyer_email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo formatNaira($order['total_amount']); ?></strong>
                                        <br>
                                        <small class="text-muted">Net: <?php echo formatNaira($order['net_amount']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <?php if ($order['order_status'] == 'shipped' && !empty($order['tracking_number'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-truck"></i> <?php echo htmlspecialchars($order['tracking_number']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($methodLabel); ?>
                                        </span>
                                        <?php if ($order['payment_status'] == 'paid'): ?>
                                            <br>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($order['payment_status'] == 'pending' && $order['payment_method'] == 'pay_on_delivery'): ?>
                                            <br>
                                            <span class="badge bg-warning text-dark">COD</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDate($order['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo SITE_URL; ?>/seller/order-detail.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <?php if ($order['order_status'] == 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success js-confirm-order" 
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        title="Confirm Order">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['order_status'] == 'confirmed'): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-primary js-open-shipping" 
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        title="Mark as Shipped">
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
                        $baseUrl = SITE_URL . '/seller/orders.php?' . http_build_query($queryParams);
                        echo paginate($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Shipping Modal -->
<div class="modal fade" id="shippingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="shippingForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Mark as Shipped</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="shippingOrderId">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="tracking_number" class="form-label">Tracking Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="tracking_carrier" class="form-label">Carrier <span class="text-danger">*</span></label>
                        <select class="form-select" id="tracking_carrier" name="tracking_carrier" required>
                            <option value="">Select Carrier</option>
                            <?php foreach (NG_DELIVERY_CARRIERS as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>">
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

<script>
window.SellerOrdersConfig = {
    siteUrl:   <?php echo json_encode(SITE_URL); ?>,
    csrfToken: <?php echo json_encode(Session::generateCSRFToken()); ?>
};
</script>

<script src="<?php echo SITE_URL; ?>/assets/js/seller-orders.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>