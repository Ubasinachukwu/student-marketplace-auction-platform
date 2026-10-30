<?php
// marketplace/buyer/browse.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$userId = Session::getUserId();

// Get filter parameters
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$subcategory = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$saleType = isset($_GET['sale_type']) ? sanitize($_GET['sale_type']) : '';
$condition = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["l.status = 'active'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($subcategory > 0) {
    $whereConditions[] = "l.category_id = ?";
    $params[] = $subcategory;
} elseif ($category > 0) {
    $subCategories = $db->fetchAll(
        "SELECT id FROM categories WHERE parent_id = ? OR id = ?",
        [$category, $category]
    );
    $catIds = array_column($subCategories, 'id');
    if (!empty($catIds)) {
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $whereConditions[] = "l.category_id IN ($placeholders)";
        $params = array_merge($params, $catIds);
    }
}

if ($minPrice > 0) {
    $whereConditions[] = "(l.price >= ? OR l.current_bid >= ?)";
    $params[] = $minPrice;
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $whereConditions[] = "(l.price <= ? OR l.current_bid <= ?)";
    $params[] = $maxPrice;
    $params[] = $maxPrice;
}

if (!empty($saleType)) {
    $whereConditions[] = "l.sale_type = ?";
    $params[] = $saleType;
}

if (!empty($condition)) {
    $whereConditions[] = "l.condition = ?";
    $params[] = $condition;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

switch($sort) {
    case 'price_low':    $orderBy = "COALESCE(l.price, l.current_bid) ASC"; break;
    case 'price_high':   $orderBy = "COALESCE(l.price, l.current_bid) DESC"; break;
    case 'ending_soon':  $orderBy = "l.auction_end ASC"; break;
    case 'popular':      $orderBy = "l.views_count DESC"; break;
    default:             $orderBy = "l.created_at DESC";
}

$countQuery = "SELECT COUNT(*) as total FROM listings l $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

$query = "
    SELECT l.*, 
           u.full_name as seller_name,
           u.id as seller_id,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
           (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as highest_bid
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$listings = $db->fetchAll($query, $queryParams);

// Get the user's cart state (for showing "In Cart" badges)
$userCartSellerId = null;
$userCartListingIds = [];
if (Session::isLoggedIn()) {
    $userCart = $db->fetchOne("SELECT id, seller_id FROM carts WHERE user_id = ?", [$userId]);
    if ($userCart) {
        $userCartSellerId = (int)$userCart['seller_id'];
        $cartItems = $db->fetchAll(
            "SELECT listing_id FROM cart_items WHERE cart_id = ?",
            [$userCart['id']]
        );
        $userCartListingIds = array_column($cartItems, 'listing_id');
    }
}

// Get parent categories with listing counts
$categories = $db->fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM listings l 
             WHERE l.category_id = c.id AND l.status = 'active') as direct_count,
            (SELECT COUNT(*) FROM listings l 
             JOIN categories sub ON l.category_id = sub.id 
             WHERE sub.parent_id = c.id AND l.status = 'active') as sub_count
     FROM categories c 
     WHERE c.parent_id IS NULL AND c.is_active = 1 
     ORDER BY c.sort_order, c.name"
);

foreach ($categories as &$cat) {
    $cat['total_count'] = ($cat['direct_count'] ?? 0) + ($cat['sub_count'] ?? 0);
}
unset($cat);

$allSubcategories = $db->fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM listings l 
             WHERE l.category_id = c.id AND l.status = 'active') as listing_count
     FROM categories c 
     WHERE c.parent_id IS NOT NULL AND c.is_active = 1 
     ORDER BY c.parent_id, c.sort_order, c.name"
);

$subcategoriesByParent = [];
foreach ($allSubcategories as $sub) {
    $subcategoriesByParent[$sub['parent_id']][] = $sub;
}

$subCategories = [];
if ($category > 0) {
    $subCategories = $db->fetchAll(
        "SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order",
        [$category]
    );
}

$pageJS = 'search.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-lg-9 ms-auto">

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">
                        <?php if ($search): ?>
                            Results for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php else: ?>
                            All Items
                        <?php endif; ?>
                    </h4>
                    <small class="text-muted"><?php echo $totalItems; ?> items found</small>
                </div>
                <div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary view-toggle active" data-view="grid">
                            <i class="bi bi-grid"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary view-toggle" data-view="list">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters Bar (Mobile Friendly) -->
            <div class="card mb-4 d-lg-none">
                <div class="card-body">
                    <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i> Show Filters
                    </button>
                    <div class="collapse mt-3" id="filterCollapse">
                        <form method="GET" action="<?php echo SITE_URL; ?>/buyer/browse.php" id="filterForm">
                            <div class="mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="q" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search items...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="categorySelectMobile">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['total_count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Price Range</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="min_price" 
                                               placeholder="Min" value="<?php echo $minPrice ?: ''; ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control" name="max_price" 
                                               placeholder="Max" value="<?php echo $maxPrice ?: ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sale Type</label>
                                <select class="form-select" name="sale_type">
                                    <option value="">All Types</option>
                                    <option value="fixed" <?php echo $saleType == 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                    <option value="auction" <?php echo $saleType == 'auction' ? 'selected' : ''; ?>>Auction</option>
                                    <option value="hybrid" <?php echo $saleType == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Apply Filters
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Filters Sidebar + Results -->
            <div class="row">
                <div class="col-lg-3 d-none d-lg-block">
                    <div class="filter-sidebar">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Categories</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="category-nav">
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" 
                                       class="category-nav-item <?php echo $category == 0 ? 'active' : ''; ?>">
                                        <i class="bi bi-collection"></i>
                                        <span>All Categories</span>
                                    </a>
                                    
                                    <?php foreach ($categories as $cat): ?>
                                        <div class="category-group">
                                            <a href="<?php echo SITE_URL; ?>/buyer/browse.php?category=<?php echo $cat['id']; ?>" 
                                               class="category-nav-item <?php echo $category == $cat['id'] ? 'active' : ''; ?>"
                                               data-bs-toggle="collapse"
                                               data-bs-target="#subcat-<?php echo $cat['id']; ?>">
                                                <i class="bi <?php echo getCategoryIcon($cat['slug']); ?>"></i>
                                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                                <span class="badge bg-secondary ms-auto"><?php echo $cat['total_count']; ?></span>
                                                <?php if (isset($subcategoriesByParent[$cat['id']])): ?>
                                                    <i class="bi bi-chevron-down ms-1 small"></i>
                                                <?php endif; ?>
                                            </a>
                                            
                                            <?php if (isset($subcategoriesByParent[$cat['id']])): ?>
                                                <div class="collapse <?php echo $category == $cat['id'] ? 'show' : ''; ?>" 
                                                     id="subcat-<?php echo $cat['id']; ?>">
                                                    <div class="subcategory-nav">
                                                        <?php foreach ($subcategoriesByParent[$cat['id']] as $sub): ?>
                                                            <a href="<?php echo SITE_URL; ?>/buyer/browse.php?subcategory=<?php echo $sub['id']; ?>" 
                                                               class="subcategory-nav-item <?php echo $subcategory == $sub['id'] ? 'active' : ''; ?>">
                                                                <i class="bi <?php echo getCategoryIcon($sub['slug']); ?>"></i>
                                                                <span><?php echo htmlspecialchars($sub['name']); ?></span>
                                                                <span class="badge bg-light text-dark ms-auto"><?php echo $sub['listing_count']; ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="<?php echo SITE_URL; ?>/buyer/browse.php" id="filterFormDesktop">
                                    <?php if ($category > 0): ?>
                                        <input type="hidden" name="category" value="<?php echo $category; ?>">
                                    <?php endif; ?>
                                    <?php if ($subcategory > 0): ?>
                                        <input type="hidden" name="subcategory" value="<?php echo $subcategory; ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Search</label>
                                        <div class="input-group">
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="q" 
                                                   value="<?php echo htmlspecialchars($search); ?>"
                                                   placeholder="Search items...">
                                            <button class="btn btn-outline-primary" type="submit">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Price Range (₦)</label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" 
                                                       class="form-control" 
                                                       name="min_price" 
                                                       placeholder="Min"
                                                       value="<?php echo $minPrice ?: ''; ?>"
                                                       min="0"
                                                       step="0.01">
                                            </div>
                                            <div class="col-6">
                                                <input type="number" 
                                                       class="form-control" 
                                                       name="max_price" 
                                                       placeholder="Max"
                                                       value="<?php echo $maxPrice ?: ''; ?>"
                                                       min="0"
                                                       step="0.01">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Sale Type</label>
                                        <select class="form-select" name="sale_type">
                                            <option value="">All Types</option>
                                            <option value="fixed" <?php echo $saleType == 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                            <option value="auction" <?php echo $saleType == 'auction' ? 'selected' : ''; ?>>Auction</option>
                                            <option value="hybrid" <?php echo $saleType == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Condition</label>
                                        <select class="form-select" name="condition">
                                            <option value="">All Conditions</option>
                                            <option value="new" <?php echo $condition == 'new' ? 'selected' : ''; ?>>New</option>
                                            <option value="like_new" <?php echo $condition == 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                            <option value="good" <?php echo $condition == 'good' ? 'selected' : ''; ?>>Good</option>
                                            <option value="fair" <?php echo $condition == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                            <option value="poor" <?php echo $condition == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Sort By</label>
                                        <select class="form-select" name="sort">
                                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                            <option value="ending_soon" <?php echo $sort == 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                                            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Apply Filters
                                    </button>
                                    
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-outline-secondary w-100 mt-2">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div class="col-lg-9">
                    <?php if ($category > 0 || $subcategory > 0 || !empty($saleType) || !empty($condition) || $minPrice > 0 || $maxPrice > 0): ?>
                        <div class="mb-3">
                            <small class="text-muted me-2">Active filters:</small>
                            <?php if ($category > 0): 
                                $catName = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$category]);
                            ?>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($catName['name'] ?? ''); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($subcategory > 0): 
                                $subName = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$subcategory]);
                            ?>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($subName['name'] ?? ''); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($saleType)): ?>
                                <span class="badge bg-warning text-dark"><?php echo ucfirst($saleType); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($condition)): ?>
                                <span class="badge bg-secondary"><?php echo getConditionLabel($condition); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($listings)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-search display-1 text-muted"></i>
                            <h3 class="mt-3">No items found</h3>
                            <p class="text-muted">Try adjusting your filters or search terms.</p>
                            <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="row g-4 listing-grid" id="listingGrid">
                            <?php foreach ($listings as $listing): 
                                $price = $listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid'];
                                $isAuction = in_array($listing['sale_type'], ['auction', 'hybrid']);
                                $isEnded = $isAuction && !empty($listing['auction_end']) && strtotime($listing['auction_end']) < time();
                                $isEndingSoon = $isAuction && !$isEnded && $listing['auction_end'] && 
                                               strtotime($listing['auction_end']) < strtotime('+24 hours');
                                $isFixedPrice = $listing['sale_type'] === 'fixed';
                                $canAddToCart = $isFixedPrice 
                                             && Session::isLoggedIn() 
                                             && $userId != $listing['seller_id'];
                                $inCart = in_array($listing['id'], $userCartListingIds);
                                $hasSellerConflict = $userCartSellerId !== null 
                                                  && $userCartSellerId !== (int)$listing['seller_id'];
                                $showLiveBadge = $isAuction && !$isEnded;
                            ?>
                                <div class="col-md-6 col-xl-4 listing-item">
                                    <div class="card h-100 shadow-sm border-0">
                                        
                                        <!-- LIVE BADGE (Clean, No Lights) -->
                                        <?php if ($showLiveBadge): ?>
                                            <div class="position-absolute top-0 start-0 m-2 z-1">
                                                <span class="badge bg-danger px-2 py-1 shadow-sm">
                                                    <i class="bi bi-broadcast"></i> LIVE
                                                </span>
                                            </div>
                                        <?php elseif ($inCart): ?>
                                            <div class="position-absolute top-0 end-0 m-2 z-1">
                                                <span class="badge bg-success px-2 py-1 shadow-sm">
                                                    <i class="bi bi-cart-check"></i> In Cart
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="position-relative overflow-hidden rounded-top" style="height: 200px;">
                                            <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                                 class="w-100 h-100" 
                                                 alt="<?php echo htmlspecialchars($listing['title']); ?>"
                                                 style="object-fit: cover;">
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title text-truncate-2 mb-0 fw-bold">
                                                    <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" class="text-dark text-decoration-none">
                                                        <?php echo htmlspecialchars($listing['title']); ?>
                                                    </a>
                                                </h5>
                                                <?php if (Session::isLoggedIn() && $userId != $listing['seller_id']): ?>
                                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm watch-btn" 
                                                            data-listing-id="<?php echo $listing['id']; ?>">
                                                        <i class="bi bi-heart text-danger"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="text-muted small mb-1">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($listing['seller_name']); ?>
                                            </p>
                                            
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-tag"></i> <?php echo getConditionLabel($listing['condition']); ?>
                                                <?php if ($isAuction && $listing['bid_count'] > 0): ?>
                                                    <span class="ms-2"><i class="bi bi-people"></i> <?php echo $listing['bid_count']; ?> bids</span>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                                <div>
                                                    <span class="price-tag fw-bold fs-5 <?php echo $isAuction ? 'text-primary' : 'text-success'; ?>">
                                                        <?php echo formatNaira($price); ?>
                                                    </span>
                                                    <?php if ($isAuction): ?>
                                                        <br><small class="text-danger fw-bold"><i class="bi bi-clock"></i> <?php echo timeRemaining($listing['auction_end']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <?php if ($canAddToCart && !$inCart): ?>
                                                        <button class="btn btn-sm btn-outline-success add-to-cart-btn" 
                                                                data-listing-id="<?php echo $listing['id']; ?>"
                                                                data-conflict="<?php echo $hasSellerConflict ? '1' : '0'; ?>"
                                                                title="<?php echo $hasSellerConflict ? 'Add (will replace cart)' : 'Add to Cart'; ?>">
                                                            <i class="bi bi-cart-plus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" class="btn btn-sm btn-dark px-3 rounded-pill">
                                                        View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="mt-4">
                                <?php 
                                $queryParams = $_GET;
                                unset($queryParams['page']);
                                $baseUrl = SITE_URL . '/buyer/browse.php?' . http_build_query($queryParams);
                                echo paginate($page, $totalPages, $baseUrl);
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles for Category Navigation -->
<style>
.category-nav { padding: 0; }
.category-nav-item {
    display: flex; align-items: center; padding: 10px 15px;
    color: #333; text-decoration: none;
    border-bottom: 1px solid #eee; transition: all 0.2s;
    font-size: 0.9rem;
}
.category-nav-item:hover { background-color: #f8f9fa; color: #0d6efd; }
.category-nav-item.active {
    background-color: #e7f1ff; color: #0d6efd;
    font-weight: 600; border-left: 3px solid #0d6efd;
}
.category-nav-item i:first-child {
    margin-right: 8px; font-size: 1.1rem;
    width: 20px; text-align: center;
}
.subcategory-nav {
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
}
.subcategory-nav-item {
    display: flex; align-items: center;
    padding: 8px 15px 8px 35px;
    color: #666; text-decoration: none;
    border-bottom: 1px solid #eee;
    transition: all 0.2s; font-size: 0.85rem;
}
.subcategory-nav-item:last-child { border-bottom: none; }
.subcategory-nav-item:hover { background-color: #e9ecef; color: #0d6efd; }
.subcategory-nav-item.active {
    background-color: #cfe2ff; color: #0d6efd; font-weight: 600;
}
.subcategory-nav-item i {
    margin-right: 6px; font-size: 0.9rem;
    width: 16px; text-align: center;
}
.category-nav-item .badge, .subcategory-nav-item .badge {
    font-size: 0.7rem; padding: 2px 6px;
}
@media (max-width: 991.98px) { .category-nav { display: none; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // View toggle
    // =============================================
    const viewToggles = document.querySelectorAll('.view-toggle');
    const listingGrid = document.getElementById('listingGrid');
    
    if (viewToggles.length && listingGrid) {
        viewToggles.forEach(btn => {
            btn.addEventListener('click', function() {
                viewToggles.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.dataset.view;
                if (view === 'list') {
                    listingGrid.className = 'row g-3 listing-list';
                    listingGrid.querySelectorAll('.listing-item').forEach(item => {
                        item.className = 'col-12 listing-item';
                    });
                } else {
                    listingGrid.className = 'row g-4 listing-grid';
                    listingGrid.querySelectorAll('.listing-item').forEach(item => {
                        item.className = 'col-md-6 col-xl-4 listing-item';
                    });
                }
            });
        });
    }

    // =============================================
    // Watchlist toggle
    // =============================================
    document.querySelectorAll('.watch-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const listingId = this.dataset.listingId;
            const icon = this.querySelector('i');
            
            fetch('<?php echo SITE_URL; ?>/api/common/watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'listing_id=' + encodeURIComponent(listingId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        icon.className = 'bi bi-heart-fill text-danger';
                        this.classList.add('active');
                    } else {
                        icon.className = 'bi bi-heart text-danger';
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
    });

    // =============================================
    // ADD TO CART (browse cards)
    // =============================================
    const csrfToken = <?php echo json_encode(Session::generateCSRFToken()); ?>;
    const siteUrl   = <?php echo json_encode(SITE_URL); ?>;

    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const listingId = this.dataset.listingId;
            const hasConflict = this.dataset.conflict === '1';
            const card = this.closest('.card');

            if (hasConflict) {
                if (!confirm('⚠ Your cart contains items from another seller.\n\nReplace your cart with this item?')) {
                    return;
                }
            }

            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i>';

            fetch(siteUrl + '/api/cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'listing_id=' + encodeURIComponent(listingId)
                    + '&replace=' + (hasConflict ? '1' : '0')
                    + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.cart-badge');
                    const cartLink = document.querySelector('.cart-link');
                    if (badge) badge.textContent = data.count;
                    else if (cartLink && data.count > 0) {
                        const span = document.createElement('span');
                        span.className = 'badge bg-danger cart-badge';
                        span.textContent = data.count;
                        cartLink.appendChild(span);
                    }

                    this.innerHTML = '<i class="bi bi-check"></i>';
                    setTimeout(() => {
                        this.innerHTML = '<i class="bi bi-cart-check"></i>';
                        this.disabled = false;
                    }, 800);
                } else if (data.conflict) {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                    if (confirm('⚠ Your cart contains items from ' + (data.current_seller || 'another seller') +
                               '.\n\nReplace with this item?')) {
                        fetch(siteUrl + '/api/cart/add.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: 'listing_id=' + encodeURIComponent(listingId)
                                + '&replace=1'
                                + '&csrf_token=' + encodeURIComponent(csrfToken)
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
                                this.innerHTML = '<i class="bi bi-cart-check"></i>';
                                this.disabled = true;
                            } else {
                                alert(res.message || 'Failed to add to cart');
                            }
                        })
                        .catch(() => alert('An error occurred'));
                    }
                } else {
                    alert(data.message || 'Failed to add to cart');
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred. Please try again.');
                this.disabled = false;
                this.innerHTML = originalHtml;
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>