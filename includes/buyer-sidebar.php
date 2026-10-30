<?php
// marketplace/includes/buyer-sidebar.php

$currentPage = basename($_SERVER['PHP_SELF']);

$stats = $stats ?? [
    'watchlist_count'  => 0,
    'active_orders'    => 0,
    'unread_messages'  => 0
];

// Unread messages count
$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    global $db;
    if ($db) {
        $result = $db->fetchOne(
            "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0",
            [$_SESSION['user_id']]
        );
        $unreadCount = (int)($result['count'] ?? 0);
    }
}

// Cart count
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    global $db;
    if ($db) {
        $row = $db->fetchOne(
            "SELECT COUNT(ci.id) as c 
             FROM carts c 
             LEFT JOIN cart_items ci ON ci.cart_id = c.id 
             WHERE c.user_id = ?",
            [$_SESSION['user_id']]
        );
        $cartCount = (int)($row['c'] ?? 0);
    }
}
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar" style="min-height: 80vh;">
    <div class="position-sticky pt-3">

        <ul class="nav flex-column">

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/dashboard.php">
                    <i class="bi bi-house"></i> Overview
                </a>
            </li>

            <?php if (Session::isSeller()): ?>
                <li class="nav-item">
                    <a class="nav-link border-top pt-3 mt-3 text-success fw-bold" 
                       href="<?php echo SITE_URL; ?>/seller/dashboard.php">
                        <i class="bi bi-shop"></i> Seller Dashboard
                    </a>
                </li>
            <?php elseif (Session::isPendingSeller()): ?>
                <li class="nav-item">
                    <a class="nav-link border-top pt-3 mt-3 text-warning" 
                       href="<?php echo SITE_URL; ?>/buyer/become-seller.php">
                        <i class="bi bi-hourglass-split"></i> Seller Approval Pending
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link border-top pt-3 mt-3 text-success" 
                       href="<?php echo SITE_URL; ?>/buyer/become-seller.php">
                        <i class="bi bi-shop"></i> Become a Seller
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'browse.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/browse.php">
                    <i class="bi bi-search"></i> Browse Items
                </a>
            </li>

            <!-- CART — NEW -->
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/cart.php">
                    <i class="bi bi-cart"></i> My Cart
                    <?php if ($cartCount > 0): ?>
                        <span class="badge bg-success float-end"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'watchlist.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/watchlist.php">
                    <i class="bi bi-heart"></i> Watchlist
                    <?php if (!empty($stats['watchlist_count']) && $stats['watchlist_count'] > 0): ?>
                        <span class="badge bg-primary float-end"><?php echo $stats['watchlist_count']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/orders.php">
                    <i class="bi bi-box"></i> My Orders
                    <?php if (!empty($stats['active_orders']) && $stats['active_orders'] > 0): ?>
                        <span class="badge bg-warning float-end"><?php echo $stats['active_orders']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'messages.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/messages.php">
                    <i class="bi bi-envelope"></i> Messages
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'reviews.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/reviews.php">
                    <i class="bi bi-star"></i> My Reviews
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/buyer/settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link border-top pt-3 mt-3 text-danger" 
                   href="<?php echo SITE_URL; ?>/auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>

        </ul>
    </div>
</div>