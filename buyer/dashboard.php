<?php
// marketplace/buyer/dashboard.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and buyer role
Session::requireRole('buyer');

$db = db();
$userId = Session::getUserId();

// Get buyer profile (for location)
$profile = $db->fetchOne(
    "SELECT full_name, email, phone, state, lga, address FROM users WHERE id = ?",
    [$userId]
);

// Get buyer statistics
$stats = $db->fetchOne(
    "SELECT 
        (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_status IN ('pending', 'confirmed', 'shipped')) as active_orders,
        (SELECT COUNT(*) FROM watchlist WHERE user_id = ?) as watchlist_count,
        (SELECT COUNT(*) FROM bids WHERE buyer_id = ? AND is_winning = 1) as active_bids,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE buyer_id = ? AND payment_status = 'paid') as total_spent
    ",
    [$userId, $userId, $userId, $userId, $userId]
);

// =============================================
// Get categories with listing counts for cards
// =============================================
$categories = $db->fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM listings l 
             WHERE (l.category_id = c.id OR l.category_id IN 
                (SELECT id FROM categories WHERE parent_id = c.id))
             AND l.status = 'active') as total_count
     FROM categories c 
     WHERE c.parent_id IS NULL AND c.is_active = 1 
     ORDER BY c.sort_order, c.name
     LIMIT 12"
);

// Get recent orders
$recentOrders = $db->fetchAll(
    "SELECT o.*, l.title as listing_title, li.image_url as listing_image
     FROM orders o
     LEFT JOIN listings l ON o.listing_id = l.id
     LEFT JOIN listing_images li ON l.id = li.listing_id AND li.is_primary = 1
     WHERE o.buyer_id = ?
     ORDER BY o.created_at DESC
     LIMIT 5",
    [$userId]
);

// Get active bids (auctions user is participating in)
$activeBids = $db->fetchAll(
    "SELECT b.*, l.title, l.auction_end, l.current_bid, l.seller_id,
            u.full_name as seller_name,
            (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as highest_bid
     FROM bids b
     JOIN listings l ON b.listing_id = l.id
     JOIN users u ON l.seller_id = u.id
     WHERE b.buyer_id = ? AND l.status = 'active' AND l.sale_type IN ('auction', 'hybrid')
     GROUP BY b.listing_id
     ORDER BY l.auction_end ASC
     LIMIT 5",
    [$userId]
);

// Get watched listings with auction status
$watchedListings = $db->fetchAll(
    "SELECT w.*, l.title, l.price, l.current_bid, l.sale_type, l.auction_end, l.status,
            li.image_url as listing_image
     FROM watchlist w
     JOIN listings l ON w.listing_id = l.id
     LEFT JOIN listing_images li ON l.id = li.listing_id AND li.is_primary = 1
     WHERE w.user_id = ? AND l.status = 'active'
     ORDER BY w.created_at DESC
     LIMIT 5",
    [$userId]
);

// Get recent messages
$recentMessages = $db->fetchAll(
    "SELECT m.*, u.full_name as sender_name, u.user_type as sender_type,
            l.title as listing_title
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     LEFT JOIN listings l ON m.listing_id = l.id
     WHERE m.receiver_id = ? AND m.is_read = 0
     ORDER BY m.created_at DESC
     LIMIT 5",
    [$userId]
);

// Get recommendations
$recommendations = $db->fetchAll(
    "SELECT l.*, u.full_name as seller_name,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     JOIN users u ON l.seller_id = u.id
     WHERE l.status = 'active' 
       AND l.seller_id != ?
     ORDER BY RAND()
     LIMIT 8",
    [$userId]
);

$pageJS = 'dashboard.js';
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

            <!-- Welcome Banner -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">
                        <i class="bi bi-person-circle"></i> Welcome, <?php echo htmlspecialchars(Session::get('full_name')); ?>!
                    </h1>
                    <?php if (!empty($profile['state']) && !empty($profile['lga'])): ?>
                        <p class="text-muted mb-0 small">
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo htmlspecialchars($profile['lga']); ?>, 
                            <?php echo htmlspecialchars($profile['state']); ?> State
                        </p>
                    <?php elseif (!empty($profile['state'])): ?>
                        <p class="text-muted mb-0 small">
                            <i class="bi bi-geo-alt"></i> 
                            <?php echo htmlspecialchars($profile['state']); ?> State
                        </p>
                    <?php endif; ?>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-grid"></i> Browse Items
                    </a>
                </div>
            </div>

            <!-- Profile Completion Alert (if location missing) -->
            <?php if (empty($profile['state']) || empty($profile['lga'])): ?>
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle fs-4 me-3"></i>
                    <div class="flex-grow-1">
                        <strong>Complete your profile!</strong>
                        <br>
                        <small>Add your state and LGA to make checkout faster and help sellers deliver accurately.</small>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/buyer/settings.php" class="btn btn-sm btn-info">
                        <i class="bi bi-pencil"></i> Update Profile
                    </a>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                                <div class="text-muted">Total Orders</div>
                            </div>
                            <div class="stat-icon text-primary">
                                <i class="bi bi-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['active_bids']; ?></div>
                                <div class="text-muted">Active Bids</div>
                            </div>
                            <div class="stat-icon text-warning">
                                <i class="bi bi-gavel"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['watchlist_count']; ?></div>
                                <div class="text-muted">Watching</div>
                            </div>
                            <div class="stat-icon text-danger">
                                <i class="bi bi-heart"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($stats['total_spent']); ?></div>
                                <div class="text-muted">Total Spent</div>
                            </div>
                            <div class="stat-icon text-success">
                                <i class="bi bi-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- BROWSE BY CATEGORY                            -->
            <!-- ============================================ -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Browse by Category</h5>
                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($categories as $cat): ?>
                            <div class="col-lg-2 col-md-3 col-4">
                                <a href="<?php echo SITE_URL; ?>/buyer/browse.php?category=<?php echo $cat['id']; ?>" 
                                   class="category-card text-decoration-none">
                                    <div class="card h-100 text-center border-0 shadow-sm category-card-inner">
                                        <div class="card-body p-3">
                                            <div class="category-icon-wrapper mb-2">
                                                <i class="bi <?php echo getCategoryIcon($cat['slug']); ?> category-icon"></i>
                                            </div>
                                            <h6 class="category-name mb-1"><?php echo htmlspecialchars($cat['name']); ?></h6>
                                            <small class="text-muted category-count">
                                                <?php echo $cat['total_count']; ?> items
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Orders -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Orders</h5>
                            <a href="<?php echo SITE_URL; ?>/buyer/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentOrders)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-box display-1 text-muted"></i>
                                    <p class="text-muted">No orders yet. Start shopping!</p>
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">Browse Items</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                             alt="<?php echo htmlspecialchars($order['listing_title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <div class="ms-3 flex-grow-1">
                                            <div>
                                                <a href="<?php echo SITE_URL; ?>/buyer/order-detail.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($order['listing_title']); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> <?php echo formatDate($order['created_at']); ?>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?> ms-2">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo formatNaira($order['total_amount']); ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Active Bids -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-gavel"></i> Active Bids</h5>
                            <a href="<?php echo SITE_URL; ?>/buyer/browse.php?sale_type=auction" class="btn btn-sm btn-outline-primary">Find Auctions</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activeBids)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-gavel display-1 text-muted"></i>
                                    <p class="text-muted">No active bids. Join an auction!</p>
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php?sale_type=auction" class="btn btn-primary">Browse Auctions</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeBids as $bid): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="flex-grow-1">
                                            <div>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $bid['listing_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($bid['title']); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                Seller: <?php echo htmlspecialchars($bid['seller_name']); ?>
                                            </small>
                                            <div class="mt-1">
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-clock"></i> 
                                                    <?php echo timeRemaining($bid['auction_end']); ?>
                                                </span>
                                                <?php if ($bid['bid_amount'] == $bid['highest_bid']): ?>
                                                    <span class="badge bg-success">Leading</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Outbid</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div>Your bid: <strong><?php echo formatNaira($bid['bid_amount']); ?></strong></div>
                                            <div class="text-muted small">Current: <?php echo formatNaira($bid['current_bid']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Watchlist -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-heart"></i> Watchlist</h5>
                            <a href="<?php echo SITE_URL; ?>/buyer/watchlist.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($watchedListings)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-heart display-1 text-muted"></i>
                                    <p class="text-muted">Your watchlist is empty.</p>
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">Browse Items</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($watchedListings as $watch): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <img src="<?php echo getImageUrl($watch['listing_image'] ?? null, 'listing'); ?>" 
                                             alt="<?php echo htmlspecialchars($watch['title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <div class="ms-3 flex-grow-1">
                                            <div>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $watch['listing_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($watch['title']); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                <?php if ($watch['sale_type'] == 'auction' || $watch['sale_type'] == 'hybrid'): ?>
                                                    <i class="bi bi-clock"></i> <?php echo timeRemaining($watch['auction_end']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo formatNaira($watch['sale_type'] == 'fixed' ? $watch['price'] : $watch['current_bid']); ?></strong>
                                            <div>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($watch['status']); ?>">
                                                    <?php echo ucfirst($watch['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-envelope"></i> Messages</h5>
                            <a href="<?php echo SITE_URL; ?>/buyer/messages.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentMessages)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-envelope display-1 text-muted"></i>
                                    <p class="text-muted">No new messages.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentMessages as $msg): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-person-circle fs-1 text-muted"></i>
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div>
                                                <strong><?php echo htmlspecialchars($msg['sender_name']); ?></strong>
                                                <?php if ($msg['listing_title']): ?>
                                                    <small class="text-muted">about <?php echo htmlspecialchars($msg['listing_title']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-truncate" style="max-width: 250px;">
                                                <?php echo htmlspecialchars($msg['message_text']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo timeAgo($msg['created_at']); ?></small>
                                        </div>
                                        <div>
                                            <span class="badge bg-danger">New</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Recommended For You</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($recommendations as $listing): ?>
                                    <div class="col-md-3 col-6">
                                        <div class="card h-100">
                                            <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($listing['title']); ?>"
                                                 style="height: 150px; object-fit: cover;">
                                            <div class="card-body p-2">
                                                <h6 class="card-title text-truncate"><?php echo htmlspecialchars($listing['title']); ?></h6>
                                                <p class="card-text">
                                                    <strong><?php echo formatNaira($listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid']); ?></strong>
                                                </p>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-primary w-100">View</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- CUSTOM STYLES FOR CATEGORY CARDS               -->
<!-- ============================================= -->
<style>
.category-card {
    display: block;
    transition: transform 0.2s, box-shadow 0.2s;
}

.category-card:hover {
    transform: translateY(-5px);
}

.category-card-inner {
    transition: all 0.2s;
    border: 1px solid #e9ecef !important;
}

.category-card:hover .category-card-inner {
    border-color: #0d6efd !important;
    box-shadow: 0 8px 16px rgba(13, 110, 253, 0.15) !important;
}

.category-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e7f1ff 0%, #cfe2ff 100%);
    transition: all 0.2s;
}

.category-card:hover .category-icon-wrapper {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.category-icon {
    font-size: 1.8rem;
    color: #0d6efd;
    transition: all 0.2s;
}

.category-card:hover .category-icon {
    color: white;
}

.category-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    transition: color 0.2s;
}

.category-card:hover .category-name {
    color: #0d6efd;
}

.category-count {
    font-size: 0.75rem;
}

@media (max-width: 576px) {
    .category-icon-wrapper {
        width: 50px;
        height: 50px;
    }
    .category-icon {
        font-size: 1.5rem;
    }
    .category-name {
        font-size: 0.75rem;
    }
    .category-count {
        font-size: 0.65rem;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>