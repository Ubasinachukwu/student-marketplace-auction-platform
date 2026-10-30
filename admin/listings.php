<?php
// marketplace/admin/listings.php

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
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$saleType = isset($_GET['sale_type']) ? sanitize($_GET['sale_type']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $whereConditions[] = "l.status = ?";
    $params[] = $status;
}

if ($category > 0) {
    $whereConditions[] = "l.category_id = ?";
    $params[] = $category;
}

if (!empty($saleType)) {
    $whereConditions[] = "l.sale_type = ?";
    $params[] = $saleType;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM listings l $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get listings
$query = "
    SELECT l.*, 
           u.full_name as seller_name,
           u.email as seller_email,
           c.name as category_name,
           c.slug as category_slug,
           pc.name as parent_category_name,
           pc.slug as parent_category_slug,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
           (SELECT COUNT(*) FROM reports WHERE listing_id = l.id) as report_count
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    LEFT JOIN categories c ON l.category_id = c.id
    LEFT JOIN categories pc ON c.parent_id = pc.id
    $whereClause
    ORDER BY 
        CASE WHEN l.status = 'draft' THEN 0 ELSE 1 END,
        l.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$listings = $db->fetchAll($query, $queryParams);

// Get categories for filter
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE is_active = 1 ORDER BY name"
);

// Get status counts
$statusCounts = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM listings GROUP BY status"
);
$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['status']] = $sc['count'];
}

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
                <h1 class="h2"><i class="bi bi-grid"></i> Listing Moderation</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total listings</span>
                </div>
            </div>

            <!-- Status Quick Filters -->
            <div class="mb-4">
                <div class="btn-group flex-wrap" role="group">
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php" class="btn btn-outline-secondary <?php echo empty($status) ? 'active' : ''; ?>">
                        All (<?php echo array_sum($statusCountMap); ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php?status=draft" class="btn btn-outline-warning <?php echo $status == 'draft' ? 'active' : ''; ?>">
                        Drafts (<?php echo $statusCountMap['draft'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php?status=active" class="btn btn-outline-success <?php echo $status == 'active' ? 'active' : ''; ?>">
                        Active (<?php echo $statusCountMap['active'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php?status=sold" class="btn btn-outline-danger <?php echo $status == 'sold' ? 'active' : ''; ?>">
                        Sold (<?php echo $statusCountMap['sold'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php?status=ended" class="btn btn-outline-info <?php echo $status == 'ended' ? 'active' : ''; ?>">
                        Ended (<?php echo $statusCountMap['ended'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php?status=cancelled" class="btn btn-outline-secondary <?php echo $status == 'cancelled' ? 'active' : ''; ?>">
                        Cancelled (<?php echo $statusCountMap['cancelled'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/listings.php" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Title or description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sale Type</label>
                            <select class="form-select" name="sale_type">
                                <option value="">All Types</option>
                                <option value="fixed" <?php echo $saleType == 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                <option value="auction" <?php echo $saleType == 'auction' ? 'selected' : ''; ?>>Auction</option>
                                <option value="hybrid" <?php echo $saleType == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/listings.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Listings -->
            <?php if (empty($listings)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-grid display-1 text-muted"></i>
                    <h3 class="mt-3">No listings found</h3>
                    <p class="text-muted">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($listings as $listing): 
                        // Determine category display with parent
                        $displayCategory = $listing['category_name'] ?? 'Uncategorized';
                        $displaySlug = $listing['category_slug'] ?? 'other';
                        if (!empty($listing['parent_category_name'])) {
                            $displayCategory = $listing['parent_category_name'] . ' › ' . $listing['category_name'];
                        }
                    ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100">
                                <?php if ($listing['status'] == 'draft'): ?>
                                    <div class="position-absolute top-0 start-0 m-2 z-1">
                                        <span class="badge bg-warning fs-6">Draft</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($listing['report_count'] > 0): ?>
                                    <div class="position-absolute top-0 end-0 m-2 z-1">
                                        <span class="badge bg-danger">
                                            <i class="bi bi-flag"></i> <?php echo $listing['report_count']; ?> reports
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($listing['title']); ?>"
                                     style="height: 200px; object-fit: cover;">
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="card-title text-truncate"><?php echo htmlspecialchars($listing['title']); ?></h5>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($listing['status']); ?>">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="card-text small text-muted">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($listing['seller_name']); ?>
                                        <br>
                                        <i class="bi <?php echo getCategoryIcon($displaySlug); ?>"></i> <?php echo htmlspecialchars($displayCategory); ?>
                                        <?php if ($listing['sale_type'] != 'fixed'): ?>
                                            <br>
                                            <i class="bi bi-gavel"></i> <?php echo ucfirst($listing['sale_type']); ?>
                                            <?php if ($listing['bid_count'] > 0): ?>
                                                (<?php echo $listing['bid_count']; ?> bids)
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold"><?php echo formatNaira($listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid']); ?></span>
                                            <?php if ($listing['views_count'] > 0): ?>
                                                <br><small class="text-muted"><i class="bi bi-eye"></i> <?php echo $listing['views_count']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($listing['status'] == 'draft'): ?>
                                                <button class="btn btn-sm btn-success moderation-btn" 
                                                        data-listing-id="<?php echo $listing['id']; ?>" 
                                                        data-action="approve"
                                                        data-title="<?php echo htmlspecialchars($listing['title']); ?>">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger moderation-btn" 
                                                        data-listing-id="<?php echo $listing['id']; ?>" 
                                                        data-action="reject"
                                                        data-title="<?php echo htmlspecialchars($listing['title']); ?>">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($listing['status'] == 'active'): ?>
                                                <button class="btn btn-sm btn-warning moderation-btn" 
                                                        data-listing-id="<?php echo $listing['id']; ?>" 
                                                        data-action="feature"
                                                        data-title="<?php echo htmlspecialchars($listing['title']); ?>">
                                                    <i class="bi bi-star"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger moderation-btn" 
                                                    data-listing-id="<?php echo $listing['id']; ?>" 
                                                    data-action="delete"
                                                    data-title="<?php echo htmlspecialchars($listing['title']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
                        $baseUrl = SITE_URL . '/admin/listings.php?' . http_build_query($queryParams);
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
    // Moderation actions
    document.querySelectorAll('.moderation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const listingId = this.dataset.listingId;
            const action = this.dataset.action;
            const title = this.dataset.title;
            
            if (action === 'approve') {
                if (!confirm(`Approve listing "${title}"?`)) return;
            } else if (action === 'reject') {
                const reason = prompt('Please provide a reason for rejecting this listing:');
                if (reason === null) return;
                if (reason.trim() === '') {
                    alert('Please provide a reason for rejection.');
                    return;
                }
                performModeration(listingId, action, reason);
                return;
            } else if (action === 'feature') {
                if (!confirm(`Feature "${title}"? This will highlight the listing.`)) return;
            } else if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete "${title}"? This cannot be undone!`)) return;
            }
            
            performModeration(listingId, action);
        });
    });

    function performModeration(listingId, action, reason = '') {
        const data = {
            listing_id: listingId,
            action: action,
            csrf_token: '<?php echo Session::generateCSRFToken(); ?>'
        };
        
        if (reason) {
            data.reason = reason;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/listings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to perform action');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>