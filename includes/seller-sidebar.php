<?php
// marketplace/includes/seller-sidebar.php

$currentPage = basename($_SERVER['PHP_SELF']);

$stats = $stats ?? [
    'active_listings'   => 0,
    'pending_orders'    => 0,
    'unread_messages'   => 0,
    'new_reviews'       => 0
];

$storeName = 'My Store';
if (Session::isLoggedIn()) {
    $userRow = Session::getUser();
    if ($userRow && !empty($userRow['full_name'])) {
        $storeName = $userRow['full_name'] . "'s Store";
    }
}
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar" style="min-height: 80vh;">
    <div class="position-sticky pt-3">

        <div class="px-3 mb-3 text-center">
            <h6 class="fw-bold">🏪 <?php echo htmlspecialchars($storeName); ?></h6>
            <small class="text-muted">⭐ <?php echo number_format($stats['rating'] ?? 0, 1); ?> (<?php echo $stats['total_reviews'] ?? 0; ?> reviews)</small>
        </div>

        <hr>

        <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
            <span>Seller Dashboard</span>
        </h6>

        <ul class="nav flex-column">

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'create-listing.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/create-listing.php">
                    <i class="bi bi-plus-circle"></i> Create Listing
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'manage-listings.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/manage-listings.php">
                    <i class="bi bi-grid"></i> My Listings
                    <?php if (!empty($stats['active_listings'])): ?>
                        <span class="badge bg-success float-end"><?php echo $stats['active_listings']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/orders.php">
                    <i class="bi bi-box"></i> Orders
                    <?php if (!empty($stats['pending_orders'])): ?>
                        <span class="badge bg-warning float-end"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'messages.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/messages.php">
                    <i class="bi bi-envelope"></i> Messages
                    <?php if (!empty($stats['unread_messages'])): ?>
                        <span class="badge bg-danger float-end"><?php echo $stats['unread_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'reviews.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/seller/reviews.php">
                    <i class="bi bi-star"></i> Reviews
                    <?php if (!empty($stats['new_reviews'])): ?>
                        <span class="badge bg-info float-end"><?php echo $stats['new_reviews']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/settings.php?tab=store">
                    <i class="bi bi-gear"></i> Store Settings
                </a>
            </li>

            <hr>

            <li class="nav-item">
                <a class="nav-link text-primary" href="<?php echo SITE_URL; ?>/buyer/dashboard.php">
                    <i class="bi bi-cart"></i> Switch to Buying
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>

        </ul>
    </div>
</div>