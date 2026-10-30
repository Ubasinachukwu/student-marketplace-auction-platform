<?php
// marketplace/includes/admin-sidebar.php
// Admin Sidebar

$currentPage = basename($_SERVER['PHP_SELF']);
$adminStats = $adminStats ?? [
    'pending_users'    => 0,
    'pending_listings' => 0,
    'open_disputes'    => 0,
    'pending_payouts'  => 0,
    'pending_sellers'  => 0,
    'pending_reports'  => 0,
];

// Load counts from DB (once per request)
if (empty($adminStats['_loaded'])) {
    global $db;
    if ($db) {
        try {
            $adminStats['pending_users'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE email_verified = 0"
            )['count'] ?? 0);

            $adminStats['pending_listings'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM listings WHERE status = 'draft'"
            )['count'] ?? 0);

            $adminStats['open_disputes'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM disputes WHERE status IN ('open','under_review')"
            )['count'] ?? 0);

            $adminStats['pending_payouts'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM transactions WHERE type = 'payout' AND status = 'pending'"
            )['count'] ?? 0);

            $adminStats['pending_sellers'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE is_seller = 1 AND seller_status = 'pending'"
            )['count'] ?? 0);

            $adminStats['pending_reports'] = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'"
            )['count'] ?? 0);

            $adminStats['_loaded'] = true;
        } catch (Exception $e) {
            error_log('Admin sidebar stats error: ' . $e->getMessage());
        }
    }
}
?>

<!-- Admin Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block admin-sidebar-wrapper">
    <div class="position-sticky pt-4 sidebar-custom">
        
        <!-- Branding -->
        <div class="px-4 mb-4 d-flex align-items-center gap-2 text-dark fw-bold fs-5">
            <i class="bi bi-shop text-success"></i> Admin<span class="text-success">.</span>
        </div>

        <ul class="nav flex-column px-2">

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/users.php">
                    <i class="bi bi-people"></i> Users
                    <?php if ($adminStats['pending_users'] > 0): ?>
                        <span class="badge bg-warning float-end rounded-pill"><?php echo $adminStats['pending_users']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'pending-sellers.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/pending-sellers.php">
                    <i class="bi bi-person-badge"></i> Sellers
                    <?php if ($adminStats['pending_sellers'] > 0): ?>
                        <span class="badge bg-warning float-end rounded-pill"><?php echo $adminStats['pending_sellers']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'listings.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/listings.php">
                    <i class="bi bi-box-seam"></i> Listings
                    <?php if ($adminStats['pending_listings'] > 0): ?>
                        <span class="badge bg-info float-end rounded-pill"><?php echo $adminStats['pending_listings']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'orders.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/orders.php">
                    <i class="bi bi-cart"></i> Orders
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'transactions.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/transactions.php">
                    <i class="bi bi-credit-card"></i> Transactions
                    <?php if ($adminStats['pending_payouts'] > 0): ?>
                        <span class="badge bg-success float-end rounded-pill"><?php echo $adminStats['pending_payouts']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/listing-reports.php">
                    <i class="bi bi-flag"></i> Reports
                    <?php if (!empty($adminStats['pending_reports']) && $adminStats['pending_reports'] > 0): ?>
                        <span class="badge bg-danger float-end rounded-pill"><?php echo $adminStats['pending_reports']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item mt-4">
                <span class="text-muted small fw-bold px-3 text-uppercase" style="font-size: 0.7rem;">System</span>
            </li>

            <li class="nav-item mt-1">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link sidebar-link <?php echo $currentPage == 'admin-logs.php' ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>/admin/admin-logs.php">
                    <i class="bi bi-shield-check"></i> Logs
                </a>
            </li>

            <li class="nav-item mt-5 pb-4">
                <a class="nav-link text-danger fw-bold bg-danger bg-opacity-10 rounded-3 text-center" 
                   href="<?php echo SITE_URL; ?>/auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>

        </ul>
    </div>
</div>