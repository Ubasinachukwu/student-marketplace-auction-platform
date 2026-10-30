<?php
// marketplace/buyer/watchlist.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Handle remove from watchlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $listingId = (int)$_GET['remove'];
    
    $db->delete(
        "DELETE FROM watchlist WHERE user_id = ? AND listing_id = ?",
        [$userId, $listingId]
    );
    
    // Decrement watch count
    $db->update(
        "UPDATE listings SET watch_count = watch_count - 1 WHERE id = ?",
        [$listingId]
    );
    
    setSuccess('Item removed from watchlist');
    redirect(SITE_URL . '/buyer/watchlist.php');
}

// Get watchlist items
$watchlist = $db->fetchAll(
    "SELECT w.*, 
            l.id as listing_id,
            l.title,
            l.description,
            l.price,
            l.current_bid,
            l.sale_type,
            l.auction_end,
            l.status,
            l.condition,
            l.views_count,
            l.created_at as listing_created,
            u.full_name as seller_name,
            u.id as seller_id,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM watchlist w
     JOIN listings l ON w.listing_id = l.id
     JOIN users u ON l.seller_id = u.id
     WHERE w.user_id = ?
     ORDER BY w.created_at DESC",
    [$userId]
);

// Get watchlist count
$watchCount = count($watchlist);

$pageJS = 'watchlist.js';
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
                <h1><i class="bi bi-heart"></i> My Watchlist</h1>
                <span class="text-muted"><?php echo $watchCount; ?> items</span>
            </div>

            <?php if (empty($watchlist)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-heart display-1 text-muted"></i>
                    <h3 class="mt-3">Your watchlist is empty</h3>
                    <p class="text-muted">Start saving items you're interested in!</p>
                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">
                        <i class="bi bi-search"></i> Browse Items
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($watchlist as $item): 
                        $price = $item['sale_type'] == 'fixed' ? $item['price'] : $item['current_bid'];
                        $isAuction = in_array($item['sale_type'], ['auction', 'hybrid']);
                        $isEnded = $isAuction && strtotime($item['auction_end']) < time();
                        $isEndingSoon = $isAuction && !$isEnded && strtotime($item['auction_end']) < strtotime('+24 hours');
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <?php if ($isEndingSoon): ?>
                                    <div class="position-absolute top-0 start-0 m-2 z-1">
                                        <span class="badge bg-danger">
                                            <i class="bi bi-clock"></i> Ending Soon
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($isAuction): ?>
                                    <div class="position-absolute top-0 end-0 m-2 z-1">
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-gavel"></i> Auction
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($item['status'] == 'sold'): ?>
                                    <div class="position-absolute top-0 start-0 m-2 z-1">
                                        <span class="badge bg-success">Sold</span>
                                    </div>
                                <?php endif; ?>

                                <img src="<?php echo getImageUrl($item['primary_image'] ?? null, 'listing'); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                                     style="height: 200px; object-fit: cover;">
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title text-truncate-2 mb-0">
                                            <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $item['listing_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </h5>
                                        <a href="<?php echo SITE_URL; ?>/buyer/watchlist.php?remove=<?php echo $item['listing_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Remove this item from watchlist?')">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    </div>
                                    
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($item['seller_name']); ?>
                                    </p>
                                    
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-tag"></i> <?php echo getConditionLabel($item['condition']); ?>
                                        <?php if ($isAuction): ?>
                                            <span class="ms-2"><i class="bi bi-clock"></i> 
                                                <?php echo $isEnded ? 'Ended' : timeRemaining($item['auction_end']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="price-tag"><?php echo formatNaira($price); ?></span>
                                            <?php if ($isAuction && !$isEnded): ?>
                                                <br><small class="text-muted">Current bid</small>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $item['listing_id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            View
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <small class="text-muted">
                                        Added <?php echo timeAgo($item['created_at']); ?>
                                        <?php if ($item['views_count'] > 0): ?>
                                            • <i class="bi bi-eye"></i> <?php echo $item['views_count']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>