<?php
// marketplace/seller/listing-analytics.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listingId) {
    setError('Invalid listing ID');
    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Get listing details
$listing = $db->fetchOne(
    "SELECT l.*, 
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     WHERE l.id = ? AND l.seller_id = ?",
    [$listingId, $userId]
);

if (!$listing) {
    setError('Listing not found');
    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Get view history (last 30 days)
$viewHistory = $db->fetchAll(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as views
     FROM listing_views
     WHERE listing_id = ?
       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$listingId]
);

// Get bid history (for auctions)
$bidHistory = [];
if (in_array($listing['sale_type'], ['auction', 'hybrid'])) {
    $bidHistory = $db->fetchAll(
        "SELECT 
            DATE(created_at) as date,
            COUNT(*) as bids,
            MIN(bid_amount) as min_bid,
            MAX(bid_amount) as max_bid,
            AVG(bid_amount) as avg_bid
         FROM bids
         WHERE listing_id = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC",
        [$listingId]
    );
}

// Get watchlist activity
$watchActivity = $db->fetchAll(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as watches
     FROM watchlist
     WHERE listing_id = ?
       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$listingId]
);

// =============================================
// FIX: Get total watch count separately
// =============================================
$watchCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM watchlist WHERE listing_id = ?",
    [$listingId]
);
$totalWatchers = $watchCount['count'] ?? 0;

// Get top referrers
$referrers = $db->fetchAll(
    "SELECT 
        referrer,
        COUNT(*) as visits
     FROM listing_views
     WHERE listing_id = ?
       AND referrer IS NOT NULL
       AND referrer != ''
     GROUP BY referrer
     ORDER BY visits DESC
     LIMIT 10",
    [$listingId]
);

$pageJS = 'analytics.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-bar-chart"></i> Listing Analytics</h1>
                <div>
                    <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listingId; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> View Listing
                    </a>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Listing Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?php echo htmlspecialchars($listing['title']); ?>"
                                 style="max-height: 100px; width: 100%; object-fit: cover;">
                        </div>
                        <div class="col-md-10">
                            <h4><?php echo htmlspecialchars($listing['title']); ?></h4>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <small class="text-muted">Status</small>
                                    <div>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($listing['status']); ?>">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Sale Type</small>
                                    <div><?php echo ucfirst($listing['sale_type']); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Price</small>
                                    <div class="fw-bold"><?php echo formatNaira($listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid']); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Created</small>
                                    <div><?php echo formatDate($listing['created_at']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $listing['views_count'] ?? 0; ?></div>
                                <div>Total Views</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $totalWatchers; ?></div>
                                <div>Watchers</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-heart"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (in_array($listing['sale_type'], ['auction', 'hybrid'])): ?>
                    <div class="col-md-3 col-6">
                        <div class="stat-card bg-warning text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php 
                                        $bidCount = $db->fetchOne("SELECT COUNT(*) as count FROM bids WHERE listing_id = ?", [$listingId]);
                                        echo $bidCount['count'] ?? 0;
                                    ?></div>
                                    <div>Total Bids</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-gavel"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php 
                                    $orderCount = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE listing_id = ?", [$listingId]);
                                    echo $orderCount['count'] ?? 0;
                                ?></div>
                                <div>Orders</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> View Trends (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($viewHistory)): ?>
                        <div class="text-center py-3">
                            <p class="text-muted">No view data available yet</p>
                        </div>
                    <?php else: ?>
                        <canvas id="viewChart" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bid & Watch Activity -->
            <div class="row g-4">
                <?php if (in_array($listing['sale_type'], ['auction', 'hybrid']) && !empty($bidHistory)): ?>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-gavel"></i> Bid Activity</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Bids</th>
                                                <th>Min</th>
                                                <th>Max</th>
                                                <th>Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bidHistory as $row): ?>
                                                <tr>
                                                    <td><?php echo formatDate($row['date']); ?></td>
                                                    <td><?php echo $row['bids']; ?></td>
                                                    <td><?php echo formatNaira($row['min_bid']); ?></td>
                                                    <td><?php echo formatNaira($row['max_bid']); ?></td>
                                                    <td><?php echo formatNaira($row['avg_bid']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($watchActivity)): ?>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-heart"></i> Watch Activity</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>New Watchers</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($watchActivity as $row): ?>
                                                <tr>
                                                    <td><?php echo formatDate($row['date']); ?></td>
                                                    <td><?php echo $row['watches']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Referrers -->
            <?php if (!empty($referrers)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-link"></i> Top Referrers</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Visits</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalVisits = array_sum(array_column($referrers, 'visits'));
                                    foreach ($referrers as $ref): 
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if (strpos($ref['referrer'], SITE_URL) !== false): ?>
                                                    <span class="text-success">Internal</span>
                                                <?php else: ?>
                                                    <?php 
                                                    $host = parse_url($ref['referrer'], PHP_URL_HOST);
                                                    if ($host): 
                                                    ?>
                                                        <a href="<?php echo htmlspecialchars($ref['referrer']); ?>" target="_blank">
                                                            <?php echo htmlspecialchars($host); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Direct / Unknown</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $ref['visits']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo ($ref['visits'] / $totalVisits) * 100; ?>%;">
                                                        <?php echo round(($ref['visits'] / $totalVisits) * 100, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($viewHistory)): ?>
    const ctx = document.getElementById('viewChart').getContext('2d');
    const dates = <?php echo json_encode(array_column($viewHistory, 'date')); ?>;
    const views = <?php echo json_encode(array_column($viewHistory, 'views')); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Views',
                data: views,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>