<?php
// marketplace/admin/listing-moderation.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listingId) {
    setError('Invalid listing ID');
    redirect(base_url('admin/listings.php'));
}

// Get listing details
$listing = $db->fetchOne(
    "SELECT l.*, 
            u.full_name as seller_name,
            u.email as seller_email,
            u.phone as seller_phone,
            u.user_type as seller_type,
            u.is_active as seller_active,
            u.email_verified as seller_verified,
            sp.store_name,
            sp.is_verified as seller_verified_badge,
            sp.rating_avg as seller_rating,
            sp.total_sales,
            c.name as category_name,
            (SELECT COUNT(*) FROM reports WHERE listing_id = l.id) as report_count,
            (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
            (SELECT COUNT(*) FROM orders WHERE listing_id = l.id) as order_count
     FROM listings l
     JOIN users u ON l.seller_id = u.id
     LEFT JOIN seller_profiles sp ON u.id = sp.user_id
     LEFT JOIN categories c ON l.category_id = c.id
     WHERE l.id = ?",
    [$listingId]
);

if (!$listing) {
    setError('Listing not found');
    redirect(base_url('admin/listings.php'));
}

// Get listing images
$images = $db->fetchAll(
    "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order",
    [$listingId]
);

// Get bid history (if auction)
$bidHistory = [];
if (in_array($listing['sale_type'], ['auction', 'hybrid'])) {
    $bidHistory = $db->fetchAll(
        "SELECT b.*, u.full_name as bidder_name, u.email as bidder_email
         FROM bids b
         JOIN users u ON b.buyer_id = u.id
         WHERE b.listing_id = ?
         ORDER BY b.bid_amount DESC
         LIMIT 20",
        [$listingId]
    );
}

// Get seller's other listings
$otherListings = $db->fetchAll(
    "SELECT l.*,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     WHERE l.seller_id = ? AND l.id != ?
     ORDER BY l.created_at DESC
     LIMIT 5",
    [$listing['seller_id'], $listingId]
);

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(base_url('admin/listing-moderation.php?id=' . $listingId));
    }

    $action = sanitize($_POST['action']);
    $reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : '';

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'approve':
                $db->update(
                    "UPDATE listings SET status = 'active' WHERE id = ?",
                    [$listingId]
                );
                
                // Notify seller
                $db->insert(
                    "INSERT INTO notifications (user_id, type, title, message, link) 
                     VALUES (?, 'listing', 'Listing Approved', 'Your listing "' . $listing['title'] . '" has been approved and is now live!', '" . base_url('seller/manage-listings.php') . "')",
                    [$listing['seller_id']]
                );
                
                $message = 'Listing approved successfully';
                break;
                
            case 'reject':
                $db->update(
                    "UPDATE listings SET status = 'cancelled' WHERE id = ?",
                    [$listingId]
                );
                
                // Notify seller with reason
                $reasonText = $reason ? " Reason: {$reason}" : '';
                $db->insert(
                    "INSERT INTO notifications (user_id, type, title, message, link) 
                     VALUES (?, 'listing', 'Listing Rejected', 'Your listing "' . $listing['title'] . '" has been rejected.' . $reasonText, '" . base_url('seller/manage-listings.php') . "')",
                    [$listing['seller_id']]
                );
                
                $message = 'Listing rejected successfully';
                break;
                
            case 'feature':
                $current = $listing['is_featured'] ? 0 : 1;
                $db->update(
                    "UPDATE listings SET is_featured = ? WHERE id = ?",
                    [$current, $listingId]
                );
                $message = 'Listing ' . ($current ? 'featured' : 'unfeatured') . ' successfully';
                break;
                
            case 'delete':
                // Delete images
                $imagesToDelete = $db->fetchAll("SELECT image_url FROM listing_images WHERE listing_id = ?", [$listingId]);
                foreach ($imagesToDelete as $img) {
                    $filePath = LISTING_IMAGES_PATH . $img['image_url'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                $db->delete("DELETE FROM listings WHERE id = ?", [$listingId]);
                $message = 'Listing deleted successfully';
                break;
                
            default:
                throw new Exception('Invalid action');
        }

        // Log admin action
        $db->insert(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
             VALUES (?, ?, 'listing', ?, ?)",
            [Session::getUserId(), $action, $listingId, $message]
        );

        $db->commit();
        
        if ($action === 'delete') {
            setSuccess($message);
            redirect(base_url('admin/listings.php'));
        } else {
            setSuccess($message);
            redirect(base_url('admin/listing-moderation.php?id=' . $listingId));
        }

    } catch (Exception $e) {
        $db->rollback();
        setError('Failed to perform action: ' . $e->getMessage());
        redirect(base_url('admin/listing-moderation.php?id=' . $listingId));
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-grid"></i> Listing Moderation</h1>
                <div>
                    <a href="<?php echo base_url('admin/listings.php'); ?>" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Listings
                    </a>
                </div>
            </div>

            <!-- Status Banner -->
            <?php if ($listing['status'] == 'pending'): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-clock"></i> This listing is pending review.
                </div>
            <?php elseif ($listing['status'] == 'active'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> This listing is active.
                </div>
            <?php elseif ($listing['status'] == 'cancelled'): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i> This listing has been cancelled.
                </div>
            <?php elseif ($listing['status'] == 'sold'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-box"></i> This listing has been sold.
                </div>
            <?php endif; ?>

            <?php if ($listing['report_count'] > 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-flag"></i> This listing has been reported <?php echo $listing['report_count']; ?> time(s).
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Listing Details -->
                <div class="col-lg-8">
                    <!-- Images -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-images"></i> Images</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($images)): ?>
                                <p class="text-muted">No images</p>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach ($images as $img): ?>
                                        <div class="col-md-3 col-4">
                                            <img src="<?php echo getImageUrl($img['image_url'], 'listing'); ?>" 
                                                 class="img-fluid rounded" 
                                                 style="height: 150px; object-fit: cover; width: 100%;">
                                            <?php if ($img['is_primary']): ?>
                                                <span class="badge bg-primary mt-1">Primary</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Listing Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Listing Information</h6>
                        </div>
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($listing['title']); ?></h3>
                            
                            <div class="row g-3 mt-2">
                                <div class="col-md-4">
                                    <div class="text-muted small">Category</div>
                                    <div><?php echo htmlspecialchars($listing['category_name'] ?? 'Uncategorized'); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Condition</div>
                                    <div><?php echo getConditionLabel($listing['condition']); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Sale Type</div>
                                    <div><?php echo ucfirst($listing['sale_type']); ?></div>
                                </div>
                                <?php if ($listing['sale_type'] == 'fixed'): ?>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Price</div>
                                        <div class="h4 text-primary"><?php echo formatPrice($listing['price']); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Starting Bid</div>
                                        <div><?php echo formatPrice($listing['starting_bid']); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Current Bid</div>
                                        <div class="h4 text-primary"><?php echo formatPrice($listing['current_bid']); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Auction Ends</div>
                                        <div><?php echo formatDateTime($listing['auction_end']); ?></div>
                                    </div>
                                    <?php if ($listing['reserve_price']): ?>
                                        <div class="col-md-4">
                                            <div class="text-muted small">Reserve Price</div>
                                            <div><?php echo formatPrice($listing['reserve_price']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($listing['buy_it_now_price']): ?>
                                        <div class="col-md-4">
                                            <div class="text-muted small">Buy Now Price</div>
                                            <div><?php echo formatPrice($listing['buy_it_now_price']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3">
                                <div class="text-muted small">Description</div>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bid History (Auction) -->
                    <?php if (!empty($bidHistory)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Bid History (<?php echo count($bidHistory); ?> bids)</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Bidder</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bidHistory as $bid): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($bid['bidder_name']); ?></td>
                                                    <td><strong><?php echo formatPrice($bid['bid_amount']); ?></strong></td>
                                                    <td>
                                                        <?php if ($bid['is_proxy_bid']): ?>
                                                            <span class="badge bg-info">Proxy</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Manual</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatDateTime($bid['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Seller Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-person"></i> Seller Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-circle display-6 text-muted"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($listing['seller_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($listing['seller_email']); ?></div>
                                    <?php if ($listing['seller_phone']): ?>
                                        <div class="text-muted small"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($listing['seller_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <div class="text-muted">Seller Type</div>
                                    <div><?php echo ucfirst($listing['seller_type']); ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted">Verified</div>
                                    <div>
                                        <?php if ($listing['seller_verified_badge']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted">Total Sales</div>
                                    <div><?php echo $listing['total_sales'] ?? 0; ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted">Rating</div>
                                    <div>
                                        <?php if ($listing['seller_rating'] > 0): ?>
                                            <i class="bi bi-star-fill text-warning"></i>
                                            <?php echo number_format($listing['seller_rating'], 1); ?>
                                        <?php else: ?>
                                            No ratings
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="<?php echo base_url('admin/user-detail.php?id=' . $listing['seller_id']); ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-eye"></i> View Seller Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Other Listings by Seller -->
                    <?php if (!empty($otherListings)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-grid"></i> Other Listings by Seller</h6>
                            </div>
                            <div class="card-body p-2">
                                <?php foreach ($otherListings as $item): ?>
                                    <div class="d-flex align-items-center mb-2 p-2 border-bottom">
                                        <img src="<?php echo getImageUrl($item['primary_image'] ?? null, 'listing'); ?>" 
                                             style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                        <div class="ms-2 flex-grow-1">
                                            <a href="<?php echo base_url('admin/listing-moderation.php?id=' . $item['id']); ?>" class="text-decoration-none small">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                            <div class="text-muted small"><?php echo ucfirst($item['status']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Moderation Actions -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-gear"></i> Moderation Actions</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($listing['status'] == 'pending'): ?>
                                <form method="POST" action="" class="d-grid gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Approve Listing
                                    </button>
                                </form>
                                <form method="POST" action="" class="d-grid gap-2 mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="mb-2">
                                        <label class="form-label small">Rejection Reason</label>
                                        <input type="text" class="form-control form-control-sm" name="reason" placeholder="Optional reason...">
                                    </div>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-x-circle"></i> Reject Listing
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($listing['status'] == 'active'): ?>
                                <form method="POST" action="" class="d-grid gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="feature">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-star"></i> 
                                        <?php echo $listing['is_featured'] ? 'Unfeature' : 'Feature'; ?> Listing
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($listing['status'] != 'sold' && $listing['status'] != 'cancelled'): ?>
                                <form method="POST" action="" class="d-grid gap-2 mt-2" 
                                      onsubmit="return confirm('Are you sure you want to delete this listing? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Delete Listing
                                    </button>
                                </form>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="<?php echo base_url('buyer/listing-detail.php?id=' . $listingId); ?>" target="_blank" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-eye"></i> View as Buyer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>