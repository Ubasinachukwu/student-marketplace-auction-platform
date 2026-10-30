<?php
// marketplace/includes/sidebar.php
// Centralized Admin Sidebar - Include this in all admin pages
?>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block bg-dark text-white sidebar" style="min-height: 80vh;">
    <div class="position-sticky pt-3">
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-secondary">
            <span>Admin Panel</span>
        </h6>
        
        <ul class="nav flex-column">
            
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/dashboard.php'); ?>">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            
            <!-- Users -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/users.php'); ?>">
                    <i class="bi bi-people"></i> Users
                    <?php if (isset($stats['unverified_users']) && $stats['unverified_users'] > 0): ?>
                        <span class="badge bg-warning float-end"><?php echo $stats['unverified_users']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Listings -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'listings.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/listings.php'); ?>">
                    <i class="bi bi-grid"></i> Listings
                    <?php if (isset($stats['pending_listings']) && $stats['pending_listings'] > 0): ?>
                        <span class="badge bg-warning float-end"><?php echo $stats['pending_listings']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Categories -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/categories.php'); ?>">
                    <i class="bi bi-tags"></i> Categories
                </a>
            </li>
            
            <!-- Orders -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/orders.php'); ?>">
                    <i class="bi bi-box"></i> Orders
                </a>
            </li>
            
            <!-- Disputes -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'disputes.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/disputes.php'); ?>">
                    <i class="bi bi-exclamation-triangle"></i> Disputes
                    <?php if (isset($stats['open_disputes']) && $stats['open_disputes'] > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $stats['open_disputes']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Transactions -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/transactions.php'); ?>">
                    <i class="bi bi-currency-dollar"></i> Transactions
                </a>
            </li>
            
            <!-- Fees -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'fees.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/fees.php'); ?>">
                    <i class="bi bi-percent"></i> Fees
                </a>
            </li>
            
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/reports.php'); ?>">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            
            <!-- Settings -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/settings.php'); ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            
            <!-- Audit Logs -->
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'admin-logs.php' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin/admin-logs.php'); ?>">
                    <i class="bi bi-clock-history"></i> Audit Logs
                </a>
            </li>
            
            <!-- Logout -->
            <li class="nav-item mt-3">
                <a class="nav-link text-white border-top pt-3" 
                   href="<?php echo base_url('auth/logout.php'); ?>">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
            
        </ul>
    </div>
</div>