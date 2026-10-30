<?php
// marketplace/admin/reports.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Get date range filters
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'sales';

// Build date filter - USE TABLE ALIAS 'o'
$dateFilter = "DATE(o.created_at) BETWEEN ? AND ?";
$dateParams = [$dateFrom, $dateTo];

// Get sales summary
$salesSummary = $db->fetchOne(
    "SELECT 
        COALESCE(COUNT(*), 0) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_revenue,
        COALESCE(SUM(o.platform_fee), 0) as total_fees,
        COALESCE(SUM(o.net_amount), 0) as net_revenue,
        COALESCE(AVG(o.total_amount), 0) as average_order_value
     FROM orders o
     WHERE $dateFilter AND o.payment_status = 'paid'",
    $dateParams
);

// Get user statistics
$userStats = $db->fetchOne(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?) as new_users,
        (SELECT COUNT(*) FROM users WHERE user_type = 'buyer' AND DATE(created_at) BETWEEN ? AND ?) as new_buyers,
        (SELECT COUNT(*) FROM users WHERE user_type = 'seller' AND DATE(created_at) BETWEEN ? AND ?) as new_sellers,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE user_type = 'buyer') as total_buyers,
        (SELECT COUNT(*) FROM users WHERE user_type = 'seller') as total_sellers
    ",
    [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo]
);

// Get listing statistics
$listingStats = $db->fetchOne(
    "SELECT 
        (SELECT COUNT(*) FROM listings WHERE DATE(created_at) BETWEEN ? AND ?) as new_listings,
        (SELECT COUNT(*) FROM listings WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'active') as new_active,
        (SELECT COUNT(*) FROM listings WHERE status = 'active') as total_active,
        (SELECT COUNT(*) FROM listings WHERE status = 'sold') as total_sold,
        (SELECT COUNT(*) FROM listings WHERE status = 'draft') as total_pending
    ",
    [$dateFrom, $dateTo, $dateFrom, $dateTo]
);

// Get daily sales chart data
$dailySales = $db->fetchAll(
    "SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as orders,
        COALESCE(SUM(o.total_amount), 0) as revenue
     FROM orders o
     WHERE $dateFilter AND o.payment_status = 'paid'
     GROUP BY DATE(o.created_at)
     ORDER BY date ASC",
    $dateParams
);

// Get top sellers
$topSellers = $db->fetchAll(
    "SELECT 
        u.id,
        u.full_name,
        u.email,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_revenue,
        COALESCE(SUM(o.net_amount), 0) as net_earnings,
        sp.rating_avg as seller_rating
     FROM users u
     JOIN orders o ON u.id = o.seller_id
     LEFT JOIN seller_profiles sp ON u.id = sp.user_id
     WHERE $dateFilter AND o.payment_status = 'paid'
     GROUP BY u.id
     ORDER BY total_revenue DESC
     LIMIT 10",
    $dateParams
);

// Get top buyers
$topBuyers = $db->fetchAll(
    "SELECT 
        u.id,
        u.full_name,
        u.email,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent
     FROM users u
     JOIN orders o ON u.id = o.buyer_id
     WHERE $dateFilter AND o.payment_status = 'paid'
     GROUP BY u.id
     ORDER BY total_spent DESC
     LIMIT 10",
    $dateParams
);

// Get category sales
$categorySales = $db->fetchAll(
    "SELECT 
        c.name as category_name,
        c.slug as category_slug,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_revenue
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     LEFT JOIN categories c ON l.category_id = c.id
     WHERE $dateFilter AND o.payment_status = 'paid'
     GROUP BY c.id
     ORDER BY total_revenue DESC
     LIMIT 10",
    $dateParams
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
                <h1 class="h2"><i class="bi bi-file-earmark-text"></i> Reports & Analytics</h1>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/reports.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo $dateFrom; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo $dateTo; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type">
                                <option value="sales" <?php echo $reportType == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                                <option value="users" <?php echo $reportType == 'users' ? 'selected' : ''; ?>>User Report</option>
                                <option value="listings" <?php echo $reportType == 'listings' ? 'selected' : ''; ?>>Listings Report</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sales Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($salesSummary['total_revenue']); ?></div>
                                <div>Total Revenue</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $salesSummary['total_orders']; ?></div>
                                <div>Total Orders</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($salesSummary['average_order_value']); ?></div>
                                <div>Average Order</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-cart"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($salesSummary['net_revenue']); ?></div>
                                <div>Net Revenue</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart -->
            <?php if (!empty($dailySales)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Daily Sales Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="250"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Top Sellers -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-trophy"></i> Top Sellers</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topSellers)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-people display-4 text-muted"></i>
                                    <p class="text-muted">No data available</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Seller</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topSellers as $seller): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($seller['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($seller['email']); ?></small>
                                                    </td>
                                                    <td><?php echo $seller['total_orders']; ?></td>
                                                    <td><?php echo formatNaira($seller['total_revenue']); ?></td>
                                                    <td>
                                                        <?php if ($seller['seller_rating'] > 0): ?>
                                                            <i class="bi bi-star-fill text-warning"></i>
                                                            <?php echo number_format($seller['seller_rating'], 1); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Buyers -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-cart-check"></i> Top Buyers</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topBuyers)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-people display-4 text-muted"></i>
                                    <p class="text-muted">No data available</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Buyer</th>
                                                <th>Orders</th>
                                                <th>Total Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topBuyers as $buyer): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($buyer['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($buyer['email']); ?></small>
                                                    </td>
                                                    <td><?php echo $buyer['total_orders']; ?></td>
                                                    <td><?php echo formatNaira($buyer['total_spent']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Sales -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Sales by Category</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categorySales)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-tags display-4 text-muted"></i>
                                    <p class="text-muted">No data available</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-lg-8">
                                        <canvas id="categoryChart" height="200"></canvas>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Orders</th>
                                                        <th>Revenue</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($categorySales as $cat): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="bi <?php echo getCategoryIcon($cat['category_slug'] ?? 'other'); ?> text-primary me-1"></i>
                                                                <?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?>
                                                            </td>
                                                            <td><?php echo $cat['total_orders']; ?></td>
                                                            <td><?php echo formatNaira($cat['total_revenue']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="mt-4">
                <div class="d-flex gap-2">
                    <a href="<?php echo SITE_URL; ?>/admin/export-report.php?type=csv&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" 
                       class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export CSV
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/export-report.php?type=pdf&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" 
                       class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($dailySales)): ?>
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    const dates = <?php echo json_encode(array_column($dailySales, 'date')); ?>;
    const revenues = <?php echo json_encode(array_column($dailySales, 'revenue')); ?>;
    const orders = <?php echo json_encode(array_column($dailySales, 'orders')); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Revenue (₦)',
                    data: revenues,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y',
                    order: 1
                },
                {
                    label: 'Orders',
                    data: orders,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return '₦' + Number(value).toLocaleString();
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($categorySales)): ?>
    // Category Chart
    const ctx2 = document.getElementById('categoryChart').getContext('2d');
    const catNames = <?php echo json_encode(array_column($categorySales, 'category_name')); ?>;
    const catRevenue = <?php echo json_encode(array_column($categorySales, 'total_revenue')); ?>;
    const colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#fd7e14', '#6f42c1', '#20c997', '#0dcaf0'];
    
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: catNames,
            datasets: [{
                data: catRevenue,
                backgroundColor: colors.slice(0, catNames.length),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>