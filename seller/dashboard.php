<?php
// marketplace/seller/dashboard.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Get seller profile
$profile = $db->fetchOne(
    "SELECT * FROM seller_profiles WHERE user_id = ?",
    [$userId]
);

// Get seller statistics
$stats = $db->fetchOne(
    "SELECT 
        (SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'active') as active_listings,
        (SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'draft') as draft_listings,
        (SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'sold') as sold_listings,
        (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND order_status IN ('pending', 'confirmed')) as pending_orders,
        (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND order_status = 'shipped') as shipped_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE seller_id = ? AND payment_status = 'paid') as total_revenue,
        (SELECT COALESCE(SUM(platform_fee), 0) FROM orders WHERE seller_id = ? AND payment_status = 'paid') as total_fees,
        (SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE seller_id = ? AND payment_status = 'paid') as net_earnings,
        (SELECT AVG(rating) FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller') as rating_avg,
        (SELECT COUNT(*) FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller') as total_reviews
    ",
    [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
);

// Get recent orders
$recentOrders = $db->fetchAll(
    "SELECT o.*, l.title as listing_title, u.full_name as buyer_name,
            li.image_url as listing_image
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.buyer_id = u.id
     LEFT JOIN listing_images li ON l.id = li.listing_id AND li.is_primary = 1
     WHERE o.seller_id = ?
     ORDER BY o.created_at DESC
     LIMIT 5",
    [$userId]
);

// Get recent listings
$recentListings = $db->fetchAll(
    "SELECT l.*, 
            (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     WHERE l.seller_id = ?
     ORDER BY l.created_at DESC
     LIMIT 5",
    [$userId]
);

// Get auction activity (ending soon)
$endingAuctions = $db->fetchAll(
    "SELECT l.*,
            (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as highest_bid,
            (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as total_bids
     FROM listings l
     WHERE l.seller_id = ? 
       AND l.status = 'active' 
       AND l.sale_type IN ('auction', 'hybrid')
       AND l.auction_end > NOW()
       AND l.auction_end < DATE_ADD(NOW(), INTERVAL 24 HOUR)
     ORDER BY l.auction_end ASC
     LIMIT 10",
    [$userId]
);

// Get monthly earnings for chart
$monthlyEarnings = $db->fetchAll(
    "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        COALESCE(SUM(o.net_amount), 0) as earnings,
        COUNT(*) as orders
     FROM orders o
     WHERE o.seller_id = ? AND o.payment_status = 'paid'
     GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
     ORDER BY month DESC
     LIMIT 6",
    [$userId]
);

// =============================================
// FIX: Prepare stats for sidebar
// =============================================
$sidebarStats = [
    'active_listings' => $stats['active_listings'] ?? 0,
    'pending_orders' => $stats['pending_orders'] ?? 0,
    'unread_messages' => getUnreadMessagesCount($userId),
    'new_reviews' => getNewReviewsCount($userId),
    'rating' => $stats['rating_avg'] ?? 0,
    'total_reviews' => $stats['total_reviews'] ?? 0
];

$pageJS = 'dashboard.js';
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

            <!-- Welcome Banner -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-shop"></i> Welcome, <?php echo htmlspecialchars(Session::get('full_name')); ?>!
                    <?php if ($profile && isset($profile['is_verified']) && $profile['is_verified']): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php" class="btn btn-sm btn-success me-2">
                        <i class="bi bi-plus-circle"></i> New Listing
                    </a>
                    <!-- FIX: Changed manage-listings.php to my-listings.php -->
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-grid"></i> My Listings
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['active_listings'] ?? 0; ?></div>
                                <div class="text-muted">Active Listings</div>
                            </div>
                            <div class="stat-icon text-primary">
                                <i class="bi bi-grid"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['sold_listings'] ?? 0; ?></div>
                                <div class="text-muted">Sold Items</div>
                            </div>
                            <div class="stat-icon text-success">
                                <i class="bi bi-tag"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <!-- FIX: Changed to Naira currency -->
                                <div class="stat-number"><?php echo formatNaira($stats['net_earnings'] ?? 0); ?></div>
                                <div class="text-muted">Net Earnings</div>
                            </div>
                            <div class="stat-icon text-warning">
                                <i class="bi bi-currency-naira"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number">
                                    <?php echo ($stats['rating_avg'] ?? 0) ? number_format($stats['rating_avg'], 1) : 'N/A'; ?>
                                    <i class="bi bi-star-fill text-warning" style="font-size: 1rem;"></i>
                                </div>
                                <div class="text-muted">Rating (<?php echo $stats['total_reviews'] ?? 0; ?> reviews)</div>
                            </div>
                            <div class="stat-icon text-danger">
                                <i class="bi bi-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Orders -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Orders</h5>
                            <a href="<?php echo SITE_URL; ?>/seller/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentOrders)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-box display-1 text-muted"></i>
                                    <p class="text-muted">No orders yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                             alt="<?php echo htmlspecialchars($order['listing_title'] ?? 'Item'); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <div class="ms-3 flex-grow-1">
                                            <div>
                                                <a href="<?php echo SITE_URL; ?>/seller/order-detail.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($order['listing_title'] ?? 'Unknown Item'); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                Buyer: <?php echo htmlspecialchars($order['buyer_name'] ?? 'Unknown'); ?>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status'] ?? 'pending'); ?> ms-2">
                                                    <?php echo ucfirst($order['order_status'] ?? 'pending'); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo formatNaira($order['total_amount'] ?? 0); ?></strong>
                                            <?php if (isset($order['order_status']) && $order['order_status'] == 'pending'): ?>
                                                <div>
                                                    <a href="<?php echo SITE_URL; ?>/seller/order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Process</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Listings -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-grid"></i> Recent Listings</h5>
                            <!-- FIX: Changed manage-listings.php to my-listings.php -->
                            <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentListings)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-grid display-1 text-muted"></i>
                                    <p class="text-muted">No listings yet.</p>
                                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php" class="btn btn-primary">Create First Listing</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentListings as $listing): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                             alt="<?php echo htmlspecialchars($listing['title'] ?? 'Item'); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <div class="ms-3 flex-grow-1">
                                            <div>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($listing['title'] ?? 'Unknown Item'); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                <?php 
                                                    $price = ($listing['sale_type'] ?? 'fixed') == 'fixed' 
                                                        ? ($listing['price'] ?? 0) 
                                                        : ($listing['current_bid'] ?? 0);
                                                    echo formatNaira($price);
                                                ?>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($listing['status'] ?? 'draft'); ?> ms-2">
                                                    <?php echo ucfirst($listing['status'] ?? 'draft'); ?>
                                                </span>
                                                <?php if (($listing['sale_type'] ?? '') == 'auction' && ($listing['bid_count'] ?? 0) > 0): ?>
                                                    <span class="badge bg-info"><?php echo $listing['bid_count']; ?> bids</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <!-- FIX: Changed edit-listing.php to my-listings.php with edit action -->
                                            <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?action=edit&id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ending Auctions -->
            <?php if (!empty($endingAuctions)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> Auctions Ending Soon (Next 24 Hours)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($endingAuctions as $auction): ?>
                                    <div class="col-md-4 col-lg-3">
                                        <div class="card h-100 border-warning">
                                            <div class="card-body">
                                                <h6 class="card-title text-truncate"><?php echo htmlspecialchars($auction['title'] ?? 'Item'); ?></h6>
                                                <p class="card-text">
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-clock"></i> <?php echo timeRemaining($auction['auction_end'] ?? ''); ?>
                                                    </span>
                                                </p>
                                                <p class="card-text">
                                                    Current bid: <strong><?php echo formatNaira($auction['current_bid'] ?? 0); ?></strong>
                                                    <?php if (!empty($auction['highest_bid'])): ?>
                                                        <br><small class="text-muted">Highest: <?php echo formatNaira($auction['highest_bid']); ?></small>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="card-text">
                                                    <small class="text-muted"><?php echo $auction['total_bids'] ?? 0; ?> bids</small>
                                                </p>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $auction['id']; ?>" class="btn btn-sm btn-primary w-100">View Auction</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Monthly Earnings Chart -->
            <?php if (!empty($monthlyEarnings)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Earnings</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="earningsChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js for earnings chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($monthlyEarnings)): ?>
    const ctx = document.getElementById('earningsChart').getContext('2d');
    const months = <?php echo json_encode(array_reverse(array_column($monthlyEarnings, 'month'))); ?>;
    const earnings = <?php echo json_encode(array_reverse(array_column($monthlyEarnings, 'earnings'))); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Earnings',
                data: earnings,
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
                        // FIX: Changed $ to ₦
                        callback: function(value) {
                            return '₦' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>