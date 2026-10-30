<?php
// marketplace/buyer/listing-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$userId = Session::getUserId();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listingId) {
    setError('Invalid listing ID');
    redirect(SITE_URL . '/buyer/browse.php');
}

// Get listing + seller info
$listing = $db->fetchOne(
    "SELECT l.*, 
            u.id as seller_id,
            u.full_name as seller_name,
            u.email as seller_email,
            u.phone as seller_phone,
            u.profile_pic as seller_profile_pic,
            u.email_verified as seller_verified,
            sp.store_name,
            sp.store_description,
            sp.rating_avg as seller_rating,
            sp.total_sales,
            sp.is_verified as seller_verified_badge,
            (SELECT AVG(rating) FROM reviews WHERE target_user_id = u.id AND review_type = 'buyer_to_seller') as seller_avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE target_user_id = u.id AND review_type = 'buyer_to_seller') as seller_review_count,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     JOIN users u ON l.seller_id = u.id
     LEFT JOIN seller_profiles sp ON u.id = sp.user_id
     WHERE l.id = ?",
    [$listingId]
);

if (!$listing) {
    setError('Listing not found');
    redirect(SITE_URL . '/buyer/browse.php');
}

$correctSlug = !empty($listing['slug']) ? $listing['slug'] : slugify($listing['title']);
$urlSlug     = $correctSlug; 

// Increment view count
$isOwner = Session::isLoggedIn() && ((int)$userId === (int)$listing['seller_id']);
if (!$isOwner) {
    $db->update("UPDATE listings SET views_count = views_count + 1 WHERE id = ?", [$listingId]);
}

// Get all images
$images = $db->fetchAll(
    "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id",
    [$listingId]
);

// Bid history (auctions only)
$bidHistory = [];
if (in_array($listing['sale_type'], ['auction', 'hybrid'], true)) {
    $bidHistory = $db->fetchAll(
        "SELECT b.*, u.full_name as bidder_name 
         FROM bids b
         JOIN users u ON b.buyer_id = u.id
         WHERE b.listing_id = ? AND b.is_retracted = 0
         ORDER BY b.bid_amount DESC, b.created_at DESC
         LIMIT 20",
        [$listingId]
    );
}

// User's own bid
$userBid = null;
if (Session::isLoggedIn()) {
    $userBid = $db->fetchOne(
        "SELECT * FROM bids WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0 ORDER BY bid_amount DESC LIMIT 1",
        [$listingId, $userId]
    );
}

// Watchlist
$inWatchlist = false;
if (Session::isLoggedIn() && !$isOwner) {
    $watchCheck = $db->fetchOne("SELECT id FROM watchlist WHERE user_id = ? AND listing_id = ?", [$userId, $listingId]);
    $inWatchlist = !empty($watchCheck);
}

// Cart status
$inCart = false;
$cartHasDifferentSeller = false;
if (Session::isLoggedIn() && !$isOwner) {
    $userCart = $db->fetchOne("SELECT id, seller_id FROM carts WHERE user_id = ?", [$userId]);
    if ($userCart) {
        if ((int)$userCart['seller_id'] === (int)$listing['seller_id']) {
            $cartItem = $db->fetchOne("SELECT id FROM cart_items WHERE cart_id = ? AND listing_id = ?", [$userCart['id'], $listingId]);
            $inCart = !empty($cartItem);
        } else {
            $cartHasDifferentSeller = true;
        }
    }
}

// Similar items
$similarItems = $db->fetchAll(
    "SELECT l.*, (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l WHERE l.category_id = ? AND l.id != ? AND l.status = 'active' ORDER BY RAND() LIMIT 4",
    [$listing['category_id'], $listingId]
);

// Seller's other listings
$sellerListings = $db->fetchAll(
    "SELECT l.*, (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l WHERE l.seller_id = ? AND l.id != ? AND l.status = 'active' ORDER BY RAND() LIMIT 4",
    [$listing['seller_id'], $listingId]
);

$isAuction     = in_array($listing['sale_type'], ['auction', 'hybrid'], true);
$isFixed       = $listing['sale_type'] === 'fixed';
$hasBuyNow     = $listing['sale_type'] === 'hybrid' && $listing['buy_it_now_price'] > 0;
$isEnded       = $isAuction && strtotime($listing['auction_end']) < time();
$isEndingSoon  = $isAuction && !$isEnded && !empty($listing['auction_end']) && strtotime($listing['auction_end']) <= strtotime('+1 hour');
$isReserveMet  = $isAuction && $listing['reserve_price'] && $listing['current_bid'] >= $listing['reserve_price'];
$canBid        = $isAuction && !$isEnded && $listing['status'] === 'active' && Session::isLoggedIn() && !$isOwner;
$canBuyNow     = ($isFixed || $hasBuyNow) && $listing['status'] === 'active' && Session::isLoggedIn() && !$isOwner;
$canAddToCart  = $isFixed && $listing['status'] === 'active' && Session::isLoggedIn() && !$isOwner;

// Report button visibility
$canReport = Session::isLoggedIn() && !$isOwner && $listing['status'] === 'active';
$alreadyReported = false;
if ($canReport) {
    $existingReport = $db->fetchOne("SELECT id FROM reports WHERE listing_id = ? AND reported_by = ? AND status = 'pending' LIMIT 1", [$listingId, $userId]);
    $alreadyReported = !empty($existingReport);
}

// Meta tags
$displayPrice = $isFixed ? (float)$listing['price'] : (float)($listing['current_bid'] ?? $listing['starting_bid']);
$pageTitle       = $listing['title'] . ' — ' . formatNaira($displayPrice) . ' | ' . SITE_NAME;
$pageDescription = mb_substr(preg_replace('/\s+/', ' ', strip_tags($listing['description'])), 0, 155);
$pageImage       = getImageUrl($listing['primary_image'] ?? null, 'listing');

$pageJS = 'bid.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/buyer/browse.php">Browse</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($listing['title']); ?></li>
                </ol>
            </nav>

            <!-- Owner notice -->
            <?php if ($isOwner): ?>
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-info-circle"></i> <strong>You're viewing your own listing.</strong> Buy and bid options are disabled.
                    </div>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil"></i> Manage This Listing
                    </a>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="row g-4">
                <!-- Image Gallery (Clean, No Lights) -->
                <div class="col-lg-6">
                    <div class="position-relative border rounded-3 overflow-hidden shadow-sm">
                        <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                             id="mainImage"
                             class="img-fluid w-100" 
                             alt="<?php echo htmlspecialchars($listing['title']); ?>"
                             style="max-height: 500px; object-fit: contain; background: #f8f9fa;">

                        <?php if ($isAuction && $isEnded): ?>
                            <div class="position-absolute top-0 start-0 m-3">
                                <span class="badge bg-danger fs-6 p-2"><i class="bi bi-clock"></i> Auction Ended</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($listing['status'] == 'sold'): ?>
                            <div class="position-absolute top-0 start-0 m-3">
                                <span class="badge bg-success fs-6 p-2"><i class="bi bi-check-circle"></i> Sold</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($images)): ?>
                        <div class="gallery-thumbnails mt-3">
                            <?php foreach ($images as $img): ?>
                                <img src="<?php echo getImageUrl($img['image_url'], 'listing'); ?>" 
                                     class="<?php echo $img['is_primary'] ? 'active' : ''; ?>"
                                     alt="Thumbnail"
                                     onclick="changeImage(this.src)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Listing Info -->
                <div class="col-lg-6">
                    <h1 class="display-6"><?php echo htmlspecialchars($listing['title']); ?></h1>

                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <span class="badge bg-secondary"><?php echo getConditionLabel($listing['condition']); ?></span>
                        <?php if ($isAuction): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-gavel"></i> Auction</span>
                            <?php if ($listing['reserve_price']): ?>
                                <span class="badge bg-info">Reserve: <?php echo $isReserveMet ? 'Met ✅' : 'Not Met ❌'; ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($hasBuyNow): ?>
                            <span class="badge bg-success">Buy Now Available</span>
                        <?php endif; ?>
                        <?php if ($listing['is_featured']): ?>
                            <span class="badge bg-primary"><i class="bi bi-star"></i> Featured</span>
                        <?php endif; ?>
                    </div>

                    <!-- Price -->
                    <div class="mb-3">
                        <?php if ($isFixed): ?>
                            <div class="display-4 fw-bold text-primary"><?php echo formatNaira($listing['price']); ?></div>
                        <?php elseif ($isAuction): ?>
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <div class="text-muted small">Current Bid</div>
                                    <div class="display-4 fw-bold text-primary" id="liveCurrentBid">
                                        <?php echo formatNaira($listing['current_bid'] ?? $listing['starting_bid']); ?>
                                    </div>
                                </div>
                                <?php if ($hasBuyNow): ?>
                                    <div class="vr"></div>
                                    <div>
                                        <div class="text-muted small">Buy Now Price</div>
                                        <div class="h4 fw-bold text-success"><?php echo formatNaira($listing['buy_it_now_price']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isEnded && $listing['status'] == 'active'): ?>
                                <div class="mt-2">
                                    <div class="text-muted small">Time Remaining</div>
                                    <div class="auction-timer fs-4" id="auctionTimer" data-end="<?php echo $listing['auction_end']; ?>">Loading...</div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2">
                                <span class="text-muted small"><i class="bi bi-people"></i> <span id="liveBidCount"><?php echo count($bidHistory); ?></span> bids placed</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 mb-4">
                        <?php if ($listing['status'] == 'active'): ?>
                            <?php if ($isOwner): ?>
                                <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-outline-primary btn-lg"><i class="bi bi-pencil"></i> Manage This Listing</a>
                                <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?action=edit&id=<?php echo $listing['id']; ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil-square"></i> Edit Details</a>
                            <?php else: ?>
                                <?php if ($isFixed || $hasBuyNow): ?>
                                    <?php if (Session::isLoggedIn()): ?>
                                        <a href="<?php echo SITE_URL; ?>/buyer/checkout.php?listing_id=<?php echo $listing['id']; ?>&type=buynow" class="btn btn-success btn-lg">
                                            <i class="bi bi-cart"></i> Buy Now <?php echo $isFixed ? '— ' . formatNaira($listing['price']) : ''; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo SITE_URL; ?>/auth/login.php?redirect=<?php echo urlencode('/buyer/listing-detail.php?id=' . $listingId . '&slug=' . $correctSlug); ?>" class="btn btn-success btn-lg">
                                            <i class="bi bi-box-arrow-in-right"></i> Login to Buy
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($canAddToCart): ?>
                                    <?php if ($inCart): ?>
                                        <a href="<?php echo SITE_URL; ?>/buyer/cart.php" class="btn btn-outline-success btn-lg"><i class="bi bi-check-circle"></i> In Cart — View Cart</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-success btn-lg add-to-cart-btn" data-listing-id="<?php echo $listing['id']; ?>" data-conflict="<?php echo $cartHasDifferentSeller ? '1' : '0'; ?>">
                                            <i class="bi bi-cart-plus"></i> Add to Cart <?php echo $cartHasDifferentSeller ? '(Will Replace)' : ''; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($isAuction && !$isEnded): ?>
                                    <?php if ($canBid): ?>
                                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#bidModal">
                                            <i class="bi bi-gavel"></i> Place Bid
                                        </button>
                                        <?php if ($userBid): ?>
                                            <div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> Your current bid: <strong><?php echo formatNaira($userBid['bid_amount']); ?></strong></div>
                                        <?php endif; ?>
                                    <?php elseif (!Session::isLoggedIn()): ?>
                                        <a href="<?php echo SITE_URL; ?>/auth/login.php?redirect=<?php echo urlencode('/buyer/listing-detail.php?id=' . $listingId . '&slug=' . $correctSlug); ?>" class="btn btn-primary btn-lg">
                                            <i class="bi bi-box-arrow-in-right"></i> Login to Bid
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($isAuction && $isEnded): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> This auction has ended.
                                    <?php if ($listing['status'] == 'active' && $listing['current_bid'] >= $listing['reserve_price']): ?>
                                        <br>The item has been sold to the highest bidder.
                                    <?php elseif ($listing['status'] == 'active' && $listing['reserve_price'] && $listing['current_bid'] < $listing['reserve_price']): ?>
                                        <br>The reserve price was not met.
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (Session::isLoggedIn() && !$isOwner): ?>
                                <button class="btn btn-outline-danger watch-btn <?php echo $inWatchlist ? 'active' : ''; ?>" data-listing-id="<?php echo $listing['id']; ?>">
                                    <i class="bi bi-heart<?php echo $inWatchlist ? '-fill' : ''; ?>"></i> <?php echo $inWatchlist ? 'Remove from' : 'Add to'; ?> Watchlist
                                </button>
                            <?php endif; ?>

                            <?php if ($canReport && !$alreadyReported): ?>
                                <button class="btn btn-link text-muted btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#reportListingModal"><i class="bi bi-flag"></i> Report this listing</button>
                            <?php elseif ($alreadyReported): ?>
                                <small class="text-muted d-block mt-2"><i class="bi bi-flag-fill text-warning"></i> You reported this listing. Our team is reviewing it.</small>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($listing['status'] == 'sold'): ?>
                            <div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> This item has been sold.</div>
                        <?php endif; ?>
                    </div>

                    <!-- 🚨 LIVE BIDDING BOX (Moved here for the lecturer) -->
                    <?php if ($isAuction && !empty($bidHistory)): ?>
                        <div class="card mb-3 border-primary shadow-sm">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="bi bi-broadcast"></i> Live Bidding</h6>
                                <span class="badge bg-light text-primary">Updates instantly</span>
                            </div>
                            <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                                <div class="list-group list-group-flush" id="liveBidHistory">
                                    <?php foreach ($bidHistory as $bid): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($bid['bidder_name']); ?></strong>
                                                <?php if ($bid['is_proxy_bid']): ?>
                                                    <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem;">Proxy</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-success"><?php echo formatNaira($bid['bid_amount']); ?></span>
                                                <small class="text-muted ms-2"><?php echo timeAgo($bid['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Seller Info -->
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-person"></i> Seller Information</h6>
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <img src="<?php echo getProfileImageUrl($listing['seller_profile_pic'] ?? null); ?>" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <div>
                                        <strong><?php echo htmlspecialchars($listing['seller_name']); ?></strong>
                                        <?php if ($listing['seller_verified_badge']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>
                                        <?php endif; ?>
                                        <?php if ($isOwner): ?><span class="badge bg-info">You</span><?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-star<?php echo $listing['seller_rating'] > 0 ? '-fill text-warning' : ''; ?>"></i>
                                        <?php echo $listing['seller_rating'] ? number_format($listing['seller_rating'], 1) : 'No ratings yet'; ?> 
                                        (<?php echo $listing['seller_review_count'] ?? 0; ?> reviews)
                                    </div>
                                    <?php if ($listing['store_name']): ?>
                                        <div class="text-muted small"><i class="bi bi-shop"></i> <?php echo htmlspecialchars($listing['store_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (Session::isLoggedIn() && !$isOwner): ?>
                                        <a href="<?php echo SITE_URL; ?>/buyer/messages.php?new=1&user=<?php echo $listing['seller_id']; ?>&listing=<?php echo $listing['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-envelope"></i> Contact
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description & Details -->
            <div class="row mt-4 g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5>Description</h5>
                            <div class="mt-3"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></div>
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Condition</div>
                                    <div><?php echo getConditionLabel($listing['condition']); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Listed</div>
                                    <div><?php echo formatDate($listing['created_at']); ?></div>
                                </div>
                                <?php if ($isAuction): ?>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Auction End</div>
                                        <div><?php echo formatDateTime($listing['auction_end']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar cards -->
                <div class="col-lg-4">
                    <?php if (!empty($sellerListings)): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-header"><h6 class="mb-0"><i class="bi bi-shop"></i> More from this Seller</h6></div>
                            <div class="card-body p-2">
                                <?php foreach ($sellerListings as $item): ?>
                                    <div class="d-flex align-items-center mb-2 p-2 border-bottom">
                                        <img src="<?php echo getImageUrl($item['primary_image'] ?? null, 'listing'); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" alt="">
                                        <div class="ms-2 flex-grow-1">
                                            <a href="<?php echo listingUrl($item); ?>" class="text-decoration-none small"><?php echo htmlspecialchars($item['title']); ?></a>
                                            <div class="text-primary fw-bold small"><?php echo formatNaira($item['sale_type'] == 'fixed' ? $item['price'] : $item['current_bid']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($similarItems)): ?>
                        <div class="card shadow-sm">
                            <div class="card-header"><h6 class="mb-0"><i class="bi bi-tags"></i> Similar Items</h6></div>
                            <div class="card-body p-2">
                                <?php foreach ($similarItems as $item): ?>
                                    <div class="d-flex align-items-center mb-2 p-2 border-bottom">
                                        <img src="<?php echo getImageUrl($item['primary_image'] ?? null, 'listing'); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" alt="">
                                        <div class="ms-2 flex-grow-1">
                                            <a href="<?php echo listingUrl($item); ?>" class="text-decoration-none small"><?php echo htmlspecialchars($item['title']); ?></a>
                                            <div class="text-primary fw-bold small"><?php echo formatNaira($item['sale_type'] == 'fixed' ? $item['price'] : $item['current_bid']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bid Modal -->
<?php if ($canBid): ?>
<div class="modal fade" id="bidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="bidForm">
                <div class="modal-header">
                    <h5 class="modal-title">Place a Bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Bid</label>
                        <div class="h4 text-primary"><?php echo formatNaira($listing['current_bid'] ?? $listing['starting_bid']); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your Bid Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₦</span>
                            <input type="number" class="form-control" name="bid_amount" id="bidAmount" step="<?php echo $listing['bid_increment'] ?? 1; ?>" min="<?php echo ($listing['current_bid'] ?? $listing['starting_bid']) + ($listing['bid_increment'] ?? 1); ?>" required>
                        </div>
                        <small class="text-muted">Minimum bid: <?php echo formatNaira(($listing['current_bid'] ?? $listing['starting_bid']) + ($listing['bid_increment'] ?? 1)); ?></small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="proxyBid" name="proxy_bid">
                            <label class="form-check-label" for="proxyBid">Set Maximum Bid (Proxy Bidding)</label>
                        </div>
                        <small class="text-muted d-block">We'll automatically bid for you up to your maximum amount.</small>
                    </div>
                    <div class="mb-3" id="proxyBidSection" style="display: none;">
                        <label class="form-label">Maximum Bid Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₦</span>
                            <input type="number" class="form-control" name="max_proxy_amount" id="maxProxyAmount" min="<?php echo ($listing['current_bid'] ?? $listing['starting_bid']) + ($listing['bid_increment'] ?? 1); ?>">
                        </div>
                    </div>
                    <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-gavel"></i> Place Bid</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function changeImage(src) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.gallery-thumbnails img').forEach(img => {
        img.classList.toggle('active', img.src === src);
    });
}

document.querySelector('.watch-btn')?.addEventListener('click', function() {
    const listingId = this.dataset.listingId;
    fetch('<?php echo SITE_URL; ?>/api/common/watchlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'listing_id=' + encodeURIComponent(listingId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                this.innerHTML = '<i class="bi bi-heart-fill"></i> Remove from Watchlist';
                this.classList.add('active');
            } else {
                this.innerHTML = '<i class="bi bi-heart"></i> Add to Watchlist';
                this.classList.remove('active');
            }
        } else {
            alert(data.message || 'Please login to add to watchlist');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

document.getElementById('proxyBid')?.addEventListener('change', function() {
    document.getElementById('proxyBidSection').style.display = this.checked ? 'block' : 'none';
});

document.getElementById('bidForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?php echo SITE_URL; ?>/api/auction/place-bid.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bid placed successfully!');
            location.reload();
        } else {
            alert(data.message || 'Failed to place bid. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

document.querySelector('.add-to-cart-btn')?.addEventListener('click', function() {
    const btn = this;
    const listingId = btn.dataset.listingId;
    const hasConflict = btn.dataset.conflict === '1';

    if (hasConflict && !confirm('⚠ Your cart contains items from another seller.\n\nReplace your cart with this item?')) return;

    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';

    fetch('<?php echo SITE_URL; ?>/api/cart/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'listing_id=' + encodeURIComponent(listingId) + '&replace=' + (hasConflict ? '1' : '0') + '&csrf_token=' + encodeURIComponent('<?php echo Session::generateCSRFToken(); ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.cart-badge');
            const cartLink = document.querySelector('.cart-link');
            if (badge) { badge.textContent = data.count; }
            else if (cartLink && data.count > 0) {
                const span = document.createElement('span');
                span.className = 'badge bg-danger cart-badge';
                span.textContent = data.count;
                cartLink.appendChild(span);
            }
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Added to Cart';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to Cart'; }, 1500);
        } else if (data.conflict) {
            btn.disabled = false; btn.innerHTML = originalHtml;
            if (confirm('⚠ Your cart contains items from ' + (data.current_seller || 'another seller') + '.\n\nReplace with this item?')) {
                fetch('<?php echo SITE_URL; ?>/api/cart/add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'listing_id=' + encodeURIComponent(listingId) + '&replace=1&csrf_token=' + encodeURIComponent('<?php echo Session::generateCSRFToken(); ?>')
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const badge = document.querySelector('.cart-badge');
                        const cartLink = document.querySelector('.cart-link');
                        if (badge) badge.textContent = res.count;
                        else if (cartLink && res.count > 0) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-danger cart-badge';
                            span.textContent = res.count;
                            cartLink.appendChild(span);
                        }
                        btn.innerHTML = '<i class="bi bi-check-circle"></i> Added to Cart';
                        setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to Cart'; }, 1500);
                    } else {
                        alert(res.message || 'Failed to add to cart');
                        btn.disabled = false; btn.innerHTML = originalHtml;
                    }
                })
                .catch(() => { alert('An error occurred'); btn.disabled = false; btn.innerHTML = originalHtml; });
            }
        } else {
            alert(data.message || 'Failed to add to cart');
            btn.disabled = false; btn.innerHTML = originalHtml;
        }
    })
    .catch(err => {
        console.error(err); alert('An error occurred. Please try again.');
        btn.disabled = false; btn.innerHTML = originalHtml;
    });
});

<?php if ($isAuction && !$isEnded && $listing['status'] == 'active'): ?>
const endTime = new Date('<?php echo $listing['auction_end']; ?>'.replace(' ', 'T')).getTime();
const timerElement = document.getElementById('auctionTimer');

function updateTimer() {
    const now = new Date().getTime();
    const distance = endTime - now;
    if (distance <= 0) { timerElement.innerHTML = '<span class="text-danger">Auction Ended</span>'; return; }
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    let display = '';
    if (days > 0) display += days + 'd ';
    display += String(hours).padStart(2, '0') + 'h ' + String(minutes).padStart(2, '0') + 'm ' + String(seconds).padStart(2, '0') + 's';
    timerElement.textContent = display;
}
updateTimer();
setInterval(updateTimer, 1000);
<?php endif; ?>

// =============================================
// LIVE BID POLLING (every 5 seconds)
// =============================================
<?php if ($isAuction && !$isEnded): ?>
(function() {
    const listingId  = <?php echo (int)$listing['id']; ?>;
    const liveUrl    = '<?php echo SITE_URL; ?>/api/auction/live-bid.php?listing_id=' + listingId;
    let lastBidRaw   = <?php echo json_encode((float)($listing['current_bid'] ?? $listing['starting_bid'])); ?>;
    let lastBidCount = <?php echo count($bidHistory); ?>;

    function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'bid-toast';
        t.innerHTML = '<i class="bi bi-bell-fill"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 5000);
    }

    function pollLiveBid() {
        fetch(liveUrl + '&t=' + Date.now(), { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Current bid
                const bidEl = document.getElementById('liveCurrentBid');
                if (bidEl && data.current_bid_raw !== lastBidRaw) {
                    bidEl.textContent = data.current_bid;
                    bidEl.classList.remove('price-flash');
                    void bidEl.offsetWidth;
                    bidEl.classList.add('price-flash');
                    if (data.current_bid_raw > lastBidRaw) showToast('New bid: ' + data.current_bid + '!');
                    lastBidRaw = data.current_bid_raw;
                }

                // Bid count
                const countEl = document.getElementById('liveBidCount');
                if (countEl && data.total_bids !== lastBidCount) {
                    countEl.textContent = data.total_bids;
                    lastBidCount = data.total_bids;
                }

                // Live Bid History Box
                const histEl = document.getElementById('liveBidHistory');
                if (histEl && data.latest_bids.length) {
                    histEl.innerHTML = data.latest_bids.map(b => `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong>${b.name}</strong>
                                ${b.proxy ? '<span class="badge bg-info text-dark ms-1" style="font-size:0.65rem;">Proxy</span>' : ''}
                            </div>
                            <div>
                                <span class="fw-bold text-success">${b.amount}</span>
                                <small class="text-muted ms-2">${b.time}</small>
                            </div>
                        </div>
                    `).join('');
                }
            })
            .catch(err => console.warn('Live bid poll error:', err));
    }

    setInterval(pollLiveBid, 5000);
    setTimeout(pollLiveBid, 1500);
})();
<?php endif; ?>

// ---- Report listing modal ----
document.getElementById('reportListingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const original = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

    fetch('<?php echo SITE_URL; ?>/api/listing/report.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Report submitted. Thank you.');
            location.reload();
        } else {
            alert(data.message || 'Failed to submit report');
            submitBtn.disabled = false;
            submitBtn.innerHTML = original;
        }
    })
    .catch(err => {
        console.error(err); alert('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = original;
    });
});
</script>

<!-- Report Listing Modal -->
<?php if ($canReport && !$alreadyReported): ?>
<div class="modal fade" id="reportListingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reportListingForm">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-flag"></i> Report this Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                    <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Report only genuine problems. False reports may lead to account restrictions.</div>
                    <div class="mb-3">
                        <label for="report_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="report_reason" name="reason" required>
                            <option value="">Select a reason...</option>
                            <option value="scam_or_fraud">Scam or fraudulent listing</option>
                            <option value="prohibited_item">Prohibited or illegal item</option>
                            <option value="wrong_category">Wrong category</option>
                            <option value="misleading_description">Misleading description or photos</option>
                            <option value="duplicate_listing">Duplicate listing</option>
                            <option value="offensive_content">Offensive content</option>
                            <option value="other">Other (please describe)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="report_description" class="form-label">Additional details <span class="text-muted">(required if "Other")</span></label>
                        <textarea class="form-control" id="report_description" name="description" rows="4" maxlength="1000" placeholder="Explain briefly what's wrong with this listing..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-flag"></i> Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>