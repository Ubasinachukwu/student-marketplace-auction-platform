<?php
// marketplace/admin/user-detail.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    setError('Invalid user ID');
    redirect(SITE_URL . '/admin/users.php');
}

// Get user details
$user = $db->fetchOne(
    "SELECT u.*, 
            sp.store_name,
            sp.store_description,
            sp.payout_method,
            sp.payout_details,
            sp.total_sales,
            sp.total_earnings,
            sp.rating_avg,
            sp.is_verified as seller_verified,
            (SELECT COUNT(*) FROM listings WHERE seller_id = u.id) as total_listings,
            (SELECT COUNT(*) FROM listings WHERE seller_id = u.id AND status = 'active') as active_listings,
            (SELECT COUNT(*) FROM orders WHERE buyer_id = u.id) as total_purchases,
            (SELECT COUNT(*) FROM orders WHERE seller_id = u.id) as total_sales_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE buyer_id = u.id AND payment_status = 'paid') as total_spent,
            (SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE seller_id = u.id AND payment_status = 'paid') as total_earned
     FROM users u
     LEFT JOIN seller_profiles sp ON u.id = sp.user_id
     WHERE u.id = ?",
    [$userId]
);

if (!$user) {
    setError('User not found');
    redirect(SITE_URL . '/admin/users.php');
}

// Get user's recent orders (as buyer)
$buyerOrders = $db->fetchAll(
    "SELECT o.*, l.title as listing_title
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     WHERE o.buyer_id = ?
     ORDER BY o.created_at DESC
     LIMIT 10",
    [$userId]
);

// Get user's recent orders (as seller)
$sellerOrders = $db->fetchAll(
    "SELECT o.*, l.title as listing_title, u.full_name as buyer_name
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON o.buyer_id = u.id
     WHERE o.seller_id = ?
     ORDER BY o.created_at DESC
     LIMIT 10",
    [$userId]
);

// Get user's listings
$listings = $db->fetchAll(
    "SELECT l.*,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
     FROM listings l
     WHERE l.seller_id = ?
     ORDER BY l.created_at DESC
     LIMIT 10",
    [$userId]
);

// Get user's reviews
$reviews = $db->fetchAll(
    "SELECT r.*, u.full_name as reviewer_name
     FROM reviews r
     JOIN users u ON r.reviewer_id = u.id
     WHERE r.target_user_id = ?
     ORDER BY r.created_at DESC
     LIMIT 10",
    [$userId]
);

$csrfToken = Session::generateCSRFToken();
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
                <h1 class="h2"><i class="bi bi-person"></i> User Details</h1>
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <!-- User Profile -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <img src="<?php echo getProfileImageUrl($user['profile_pic'] ?? null); ?>" 
                                 class="rounded-circle img-fluid" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                            <h4 class="mt-3"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                            <div>
                                <span class="badge bg-<?php echo $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'seller' ? 'warning' : 'info'); ?> fs-6">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                                <?php if ($user['user_type'] == 'seller' && $user['seller_verified']): ?>
                                    <span class="badge bg-success fs-6">Verified Seller</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($user['user_type'] != 'admin'): ?>
                                <div class="mt-3">
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge bg-success">Email Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Email Not Verified</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small">Phone</div>
                                    <div><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Joined</div>
                                    <div><?php echo formatDateTime($user['created_at']); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Last Login</div>
                                    <div><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Email Verified</div>
                                    <div><?php echo $user['email_verified'] ? '<span class="text-success"><i class="bi bi-check-circle"></i> Verified</span>' : '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Not Verified</span>'; ?></div>
                                </div>

                                <!-- ============================================= -->
                                <!-- LOCATION SECTION - State, LGA, Address         -->
                                <!-- ============================================= -->
                                <div class="col-md-6">
                                    <div class="text-muted small"><i class="bi bi-geo-alt"></i> State</div>
                                    <div>
                                        <?php if (!empty($user['state'])): ?>
                                            <i class="bi bi-geo-alt-fill text-primary"></i>
                                            <strong><?php echo htmlspecialchars($user['state']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small"><i class="bi bi-geo-alt"></i> Local Government Area (LGA)</div>
                                    <div>
                                        <?php if (!empty($user['lga'])): ?>
                                            <i class="bi bi-geo-fill text-primary"></i>
                                            <strong><?php echo htmlspecialchars($user['lga']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small"><i class="bi bi-house-door"></i> Street Address</div>
                                    <div>
                                        <?php if (!empty($user['address'])): ?>
                                            <?php echo nl2br(htmlspecialchars($user['address'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Seller Information -->
                                <?php if ($user['user_type'] == 'seller'): ?>
                                    <div class="col-md-12">
                                        <hr>
                                        <h6><i class="bi bi-shop"></i> Seller Information</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="text-muted small">Store Name</div>
                                                <div><?php echo htmlspecialchars($user['store_name'] ?? 'Not set'); ?></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-muted small">Total Sales</div>
                                                <div><?php echo $user['total_sales'] ?? 0; ?></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-muted small">Rating</div>
                                                <div>
                                                    <?php if ($user['rating_avg'] > 0): ?>
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                        <?php echo number_format($user['rating_avg'], 1); ?>
                                                    <?php else: ?>
                                                        No ratings yet
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small">Total Earned</div>
                                                <div class="text-success fw-bold"><?php echo formatNaira($user['total_earned'] ?? 0); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted small">Payout Method</div>
                                                <div><?php echo ucfirst($user['payout_method'] ?? 'Not set'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                                <div class="stat-number"><?php echo $user['total_listings'] ?? 0; ?></div>
                                <div>Listings</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-grid"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $user['total_purchases'] ?? 0; ?></div>
                                <div>Purchases</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-cart"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($user['total_spent'] ?? 0); ?></div>
                                <div>Total Spent</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $user['total_sales_orders'] ?? 0; ?></div>
                                <div>Sales</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-tag"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="userTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#listings">
                        <i class="bi bi-grid"></i> Listings
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#purchases">
                        <i class="bi bi-cart"></i> Purchases
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sales">
                        <i class="bi bi-tag"></i> Sales
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews">
                        <i class="bi bi-star"></i> Reviews
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Listings Tab -->
                <div class="tab-pane fade show active" id="listings">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($listings)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-grid display-4 text-muted"></i>
                                    <p class="text-muted">No listings found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Price</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($listings as $listing): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo getImageUrl($listing['primary_image'] ?? null, 'listing'); ?>" 
                                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                            <div class="ms-2"><?php echo htmlspecialchars($listing['title']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo formatNaira($listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid']); ?></td>
                                                    <td><?php echo ucfirst($listing['sale_type']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($listing['status']); ?>">
                                                            <?php echo ucfirst($listing['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($listing['created_at']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $listing['id']; ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
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

                <!-- Purchases Tab -->
                <div class="tab-pane fade" id="purchases">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($buyerOrders)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-cart display-4 text-muted"></i>
                                    <p class="text-muted">No purchases found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Item</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($buyerOrders as $order): ?>
                                                <tr>
                                                    <td><?php echo $order['order_number']; ?></td>
                                                    <td><?php echo htmlspecialchars($order['listing_title']); ?></td>
                                                    <td><?php echo formatNaira($order['total_amount']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                            <?php echo ucfirst($order['order_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
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

                <!-- Sales Tab -->
                <div class="tab-pane fade" id="sales">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($sellerOrders)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-tag display-4 text-muted"></i>
                                    <p class="text-muted">No sales found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Item</th>
                                                <th>Buyer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sellerOrders as $order): ?>
                                                <tr>
                                                    <td><?php echo $order['order_number']; ?></td>
                                                    <td><?php echo htmlspecialchars($order['listing_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                                    <td><?php echo formatNaira($order['total_amount']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                            <?php echo ucfirst($order['order_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
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

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($reviews)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-star display-4 text-muted"></i>
                                    <p class="text-muted">No reviews found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Reviewer</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reviews as $review): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($review['reviewer_name']); ?></td>
                                                    <td>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($review['review_text']); ?></td>
                                                    <td><?php echo formatDate($review['created_at']); ?></td>
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
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>