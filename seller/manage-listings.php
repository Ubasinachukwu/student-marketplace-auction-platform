<?php
// marketplace/seller/manage-listings.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// =============================================
// FIX: Define constants if not defined in config
// =============================================
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 20);
}
if (!defined('LISTING_IMAGES_PATH')) {
    define('LISTING_IMAGES_PATH', __DIR__ . '/../assets/uploads/listings/');
}
if (!defined('DEFAULT_AUCTION_DURATION_DAYS')) {
    define('DEFAULT_AUCTION_DURATION_DAYS', 7);
}

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// =============================================
// RELIST FEATURE: Reset sold/ended listing back to active
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['relist_id'])) {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/manage-listings.php');
    }

    $relistId = (int)$_POST['relist_id'];
    $relistDuration = isset($_POST['relist_duration']) ? (int)$_POST['relist_duration'] : 7;

    try {
        // Verify listing belongs to seller
        $listing = $db->fetchOne(
            "SELECT * FROM listings WHERE id = ? AND seller_id = ?",
            [$relistId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or you do not own it');
        }

        // Only allow relisting for sold/ended/cancelled
        if (!in_array($listing['status'], ['sold', 'ended', 'cancelled'])) {
            throw new Exception('Only sold, ended, or cancelled listings can be relisted');
        }

        $db->beginTransaction();

        // Build update data
        $updateData = [
            'status' => 'active',
            'views_count' => 0, // Reset view count
            'watch_count' => 0, // Reset watch count
            'created_at' => date('Y-m-d H:i:s'), // Move to top
        ];

        // If auction, reset auction dates
        if (in_array($listing['sale_type'], ['auction', 'hybrid'])) {
            $updateData['auction_start'] = date('Y-m-d H:i:s');
            $updateData['auction_end'] = date('Y-m-d H:i:s', strtotime("+{$relistDuration} days"));
            $updateData['current_bid'] = $listing['starting_bid']; // Reset to starting bid
        }

        // Build SQL
        $setClause = [];
        $params = [];
        foreach ($updateData as $key => $value) {
            $setClause[] = "{$key} = ?";
            $params[] = $value;
        }
        $params[] = $relistId;
        $params[] = $userId;

        $db->update(
            "UPDATE listings SET " . implode(', ', $setClause) . " WHERE id = ? AND seller_id = ?",
            $params
        );

        // Clear old bids for auction (mark as retracted)
        if (in_array($listing['sale_type'], ['auction', 'hybrid'])) {
            $db->update(
                "UPDATE bids SET is_retracted = 1, is_winning = 0 WHERE listing_id = ?",
                [$relistId]
            );
        }

        $db->commit();

        logActivity($userId, 'relist', 'Relisted listing: ' . $listing['title']);

        setSuccess('Listing relisted successfully! It is now active.');
        redirect(SITE_URL . '/seller/manage-listings.php?status=active');

    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        setError('Failed to relist: ' . $e->getMessage());
        error_log('Relist Error: ' . $e->getMessage());
        redirect(SITE_URL . '/seller/manage-listings.php');
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/manage-listings.php');
    }

    $action = sanitize($_POST['bulk_action']);
    $listingIds = isset($_POST['listing_ids']) ? $_POST['listing_ids'] : [];

    if (empty($listingIds)) {
        setError('Please select at least one listing');
        redirect(SITE_URL . '/seller/manage-listings.php');
    }

    $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
    $params = array_merge($listingIds, [$userId]);

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'activate':
                $db->update(
                    "UPDATE listings SET status = 'active' WHERE id IN ($placeholders) AND seller_id = ?",
                    $params
                );
                $message = 'Listings activated successfully';
                break;
                
            case 'deactivate':
                $db->update(
                    "UPDATE listings SET status = 'draft' WHERE id IN ($placeholders) AND seller_id = ?",
                    $params
                );
                $message = 'Listings deactivated successfully';
                break;
                
            case 'delete':
                // Get images to delete
                $images = $db->fetchAll(
                    "SELECT image_url FROM listing_images WHERE listing_id IN ($placeholders)",
                    $listingIds
                );
                foreach ($images as $img) {
                    $filePath = LISTING_IMAGES_PATH . $img['image_url'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                $db->delete(
                    "DELETE FROM listings WHERE id IN ($placeholders) AND seller_id = ?",
                    $params
                );
                $message = 'Listings deleted successfully';
                break;
                
            default:
                throw new Exception('Invalid action');
        }

        $db->commit();
        setSuccess($message);

    } catch (Exception $e) {
        $db->rollback();
        setError('Failed to perform action: ' . $e->getMessage());
        error_log('Bulk Action Error: ' . $e->getMessage());
    }

    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Handle single listing action (via GET)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = sanitize($_GET['action']);
    $listingId = (int)$_GET['id'];

    if (!Session::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/manage-listings.php');
    }

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'activate':
                $db->update(
                    "UPDATE listings SET status = 'active' WHERE id = ? AND seller_id = ?",
                    [$listingId, $userId]
                );
                setSuccess('Listing activated successfully');
                break;
                
            case 'deactivate':
                $db->update(
                    "UPDATE listings SET status = 'draft' WHERE id = ? AND seller_id = ?",
                    [$listingId, $userId]
                );
                setSuccess('Listing deactivated successfully');
                break;
                
            case 'delete':
                // Get images to delete
                $images = $db->fetchAll(
                    "SELECT image_url FROM listing_images WHERE listing_id = ?",
                    [$listingId]
                );
                foreach ($images as $img) {
                    $filePath = LISTING_IMAGES_PATH . $img['image_url'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                $db->delete(
                    "DELETE FROM listings WHERE id = ? AND seller_id = ?",
                    [$listingId, $userId]
                );
                setSuccess('Listing deleted successfully');
                break;
                
            default:
                throw new Exception('Invalid action');
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollback();
        setError('Failed to perform action: ' . $e->getMessage());
        error_log('Single Action Error: ' . $e->getMessage());
    }

    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$saleType = isset($_GET['sale_type']) ? sanitize($_GET['sale_type']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["l.seller_id = ?"];
$params = [$userId];

if (!empty($status)) {
    $whereConditions[] = "l.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($saleType)) {
    $whereConditions[] = "l.sale_type = ?";
    $params[] = $saleType;
}

if ($categoryFilter > 0) {
    // Include subcategories
    $whereConditions[] = "(l.category_id = ? OR l.category_id IN (SELECT id FROM categories WHERE parent_id = ?))";
    $params[] = $categoryFilter;
    $params[] = $categoryFilter;
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
           c.name as category_name,
           c.slug as category_slug,
           pc.name as parent_category_name,
           pc.slug as parent_category_slug,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM bids WHERE listing_id = l.id AND is_retracted = 0) as bid_count,
           (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id AND is_retracted = 0) as highest_bid,
           (SELECT COUNT(*) FROM orders WHERE listing_id = l.id) as order_count
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    LEFT JOIN categories pc ON c.parent_id = pc.id
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$listings = $db->fetchAll($query, $queryParams);

// Get status counts for filters
$statusCounts = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM listings WHERE seller_id = ? GROUP BY status",
    [$userId]
);
$statusCountMap = [];
foreach ($statusCounts as $sc) {
    $statusCountMap[$sc['status']] = $sc['count'];
}
$totalCount = array_sum($statusCountMap);

// Get categories for filter dropdown
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order"
);

$csrfToken = Session::generateCSRFToken();
$pageJS = 'manage-listings.js';
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

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-grid"></i> My Listings</h1>
                <div>
                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New Listing
                    </a>
                </div>
            </div>

            <!-- Status Quick Filters -->
            <div class="mb-4">
                <div class="btn-group flex-wrap" role="group">
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-outline-secondary <?php echo empty($status) ? 'active' : ''; ?>">
                        All (<?php echo $totalCount; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?status=active" class="btn btn-outline-success <?php echo $status == 'active' ? 'active' : ''; ?>">
                        Active (<?php echo $statusCountMap['active'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?status=draft" class="btn btn-outline-secondary <?php echo $status == 'draft' ? 'active' : ''; ?>">
                        Drafts (<?php echo $statusCountMap['draft'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?status=sold" class="btn btn-outline-danger <?php echo $status == 'sold' ? 'active' : ''; ?>">
                        Sold (<?php echo $statusCountMap['sold'] ?? 0; ?>)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?status=ended" class="btn btn-outline-warning <?php echo $status == 'ended' ? 'active' : ''; ?>">
                        Ended (<?php echo $statusCountMap['ended'] ?? 0; ?>)
                    </a>
                    <?php if (($statusCountMap['cancelled'] ?? 0) > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?status=cancelled" class="btn btn-outline-dark <?php echo $status == 'cancelled' ? 'active' : ''; ?>">
                            Cancelled (<?php echo $statusCountMap['cancelled'] ?? 0; ?>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Banner -->
            <?php if (($statusCountMap['sold'] ?? 0) > 0 || ($statusCountMap['ended'] ?? 0) > 0): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-lightbulb"></i>
                    <strong>Tip:</strong> You can <strong>relist</strong> sold or ended items with one click using the <i class="bi bi-arrow-repeat"></i> button. This reactivates the listing without creating a new one.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search listings..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
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
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Listings Table -->
            <?php if (empty($listings)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-grid display-1 text-muted"></i>
                    <h3 class="mt-3">No listings found</h3>
                    <p class="text-muted">Start selling by creating your first listing.</p>
                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Listing
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="<?php echo SITE_URL; ?>/seller/manage-listings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listings as $listing): 
                                    // Determine which category to display (subcategory or parent)
                                    $displayCategory = $listing['category_name'] ?? 'Uncategorized';
                                    $displaySlug = $listing['category_slug'] ?? 'other';
                                    
                                    // If it's a subcategory, show parent > sub
                                    if (!empty($listing['parent_category_name'])) {
                                        $displayCategory = $listing['parent_category_name'] . ' › ' . $listing['category_name'];
                                    }
                                    
                                    // Can this listing be relisted?
                                    $canRelist = in_array($listing['status'], ['sold', 'ended', 'cancelled']);
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="listing_ids[]" class="row-select" value="<?php echo $listing['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                <div class="ms-2">
                                                    <div class="fw-bold">
                                                        <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars($listing['title']); ?>
                                                        </a>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo formatDate($listing['created_at']); ?>
                                                        <?php if (isset($listing['views_count']) && $listing['views_count'] > 0): ?>
                                                            <span class="ms-2"><i class="bi bi-eye"></i> <?php echo $listing['views_count']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi <?php echo getCategoryIcon($displaySlug); ?> text-primary me-1"></i>
                                                <small><?php echo htmlspecialchars($displayCategory); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php echo formatNaira($listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid']); ?>
                                            </strong>
                                            <?php if ($listing['sale_type'] != 'fixed' && isset($listing['bid_count']) && $listing['bid_count'] > 0): ?>
                                                <br>
                                                <small class="text-muted"><?php echo $listing['bid_count']; ?> bids</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $listing['sale_type'] == 'auction' ? 'warning' : ($listing['sale_type'] == 'hybrid' ? 'info' : 'secondary'); ?>">
                                                <?php echo ucfirst($listing['sale_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeClass($listing['status']); ?>">
                                                <?php echo ucfirst($listing['status']); ?>
                                            </span>
                                            <?php if (isset($listing['is_featured']) && $listing['is_featured']): ?>
                                                <br>
                                                <span class="badge bg-primary"><i class="bi bi-star"></i> Featured</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($listing['order_count']) && $listing['order_count'] > 0): ?>
                                                <div class="text-success">
                                                    <i class="bi bi-box"></i> <?php echo $listing['order_count']; ?> orders
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($listing['sale_type'] != 'fixed' && !empty($listing['auction_end'])): ?>
                                                <div class="text-muted small">
                                                    <?php if (strtotime($listing['auction_end']) > time()): ?>
                                                        <i class="bi bi-clock"></i> <?php echo timeRemaining($listing['auction_end']); ?>
                                                    <?php else: ?>
                                                        <span class="text-danger">Ended</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo SITE_URL; ?>/seller/edit-listing.php?id=<?php echo $listing['id']; ?>" 
                                                   class="btn btn-outline-primary"
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" 
                                                   target="_blank" 
                                                   class="btn btn-outline-secondary"
                                                   title="Preview">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($listing['status'] == 'draft'): ?>
                                                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?action=activate&id=<?php echo $listing['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" 
                                                       class="btn btn-outline-success"
                                                       title="Activate"
                                                       onclick="return confirm('Activate this listing?')">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                <?php elseif ($listing['status'] == 'active'): ?>
                                                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?action=deactivate&id=<?php echo $listing['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" 
                                                       class="btn btn-outline-secondary"
                                                       title="Deactivate"
                                                       onclick="return confirm('Deactivate this listing?')">
                                                        <i class="bi bi-pause"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- RELIST BUTTON - Only shows for sold/ended/cancelled -->
                                                <?php if ($canRelist): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-info relist-btn"
                                                            title="Relist this item"
                                                            data-listing-id="<?php echo $listing['id']; ?>"
                                                            data-listing-title="<?php echo htmlspecialchars($listing['title']); ?>"
                                                            data-sale-type="<?php echo $listing['sale_type']; ?>">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($listing['status'] != 'sold'): ?>
                                                    <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php?action=delete&id=<?php echo $listing['id']; ?>&csrf_token=<?php echo $csrfToken; ?>" 
                                                       class="btn btn-outline-danger"
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this listing? This cannot be undone.')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="row mt-3 align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" name="bulk_action" style="width: auto;">
                                    <option value="">Bulk Actions</option>
                                    <option value="activate">Activate</option>
                                    <option value="deactivate">Deactivate</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Apply
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($totalPages > 1): ?>
                                <?php 
                                $queryParams = $_GET;
                                unset($queryParams['page']);
                                $baseUrl = SITE_URL . '/seller/manage-listings.php?' . http_build_query($queryParams);
                                echo paginate($page, $totalPages, $baseUrl);
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- RELIST MODAL -->
<!-- ============================================= -->
<div class="modal fade" id="relistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo SITE_URL; ?>/seller/manage-listings.php" id="relistForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="relist_id" id="relistListingId" value="">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-repeat"></i> Relist Item
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Relist <strong id="relistListingTitle"></strong>?</p>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <small>Relisting will:
                            <ul class="mb-0 mt-1">
                                <li>Set the listing back to <strong>active</strong></li>
                                <li>Reset view and watch counts</li>
                                <li>Move the listing to the top of your list</li>
                                <li id="relistAuctionNote" style="display: none;">Reset the auction with a fresh start date</li>
                            </ul>
                        </small>
                    </div>

                    <div class="mb-3" id="relistDurationSection" style="display: none;">
                        <label for="relist_duration" class="form-label">Auction Duration</label>
                        <select class="form-select" name="relist_duration" id="relist_duration">
                            <option value="3">3 Days</option>
                            <option value="5">5 Days</option>
                            <option value="7" selected>7 Days</option>
                            <option value="10">10 Days</option>
                            <option value="14">14 Days</option>
                        </select>
                        <small class="text-muted">How long should the new auction run?</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="bi bi-arrow-repeat"></i> Relist Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.checked = this.checked;
        });
    });

    // Relist button
    document.querySelectorAll('.relist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const listingId = this.dataset.listingId;
            const listingTitle = this.dataset.listingTitle;
            const saleType = this.dataset.saleType;
            
            // Set modal values
            document.getElementById('relistListingId').value = listingId;
            document.getElementById('relistListingTitle').textContent = listingTitle;
            
            // Show auction duration for auction/hybrid
            const durationSection = document.getElementById('relistDurationSection');
            const auctionNote = document.getElementById('relistAuctionNote');
            
            if (saleType === 'auction' || saleType === 'hybrid') {
                durationSection.style.display = 'block';
                auctionNote.style.display = 'list-item';
            } else {
                durationSection.style.display = 'none';
                auctionNote.style.display = 'none';
            }
            
            // Show modal
            new bootstrap.Modal(document.getElementById('relistModal')).show();
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>