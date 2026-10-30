<?php
// marketplace/admin/fees.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;
$success = false;
$errors = [];

// Get current settings
$settings = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_group = 'fees'"
);

$feeSettings = [];
foreach ($settings as $setting) {
    $feeSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/admin/fees.php');
    }

    $fees = [
        'platform_fee_percentage' => isset($_POST['platform_fee_percentage']) ? (float)$_POST['platform_fee_percentage'] : 0,
        'auction_listing_fee' => isset($_POST['auction_listing_fee']) ? (float)$_POST['auction_listing_fee'] : 0,
        'reserve_price_fee' => isset($_POST['reserve_price_fee']) ? (float)$_POST['reserve_price_fee'] : 0,
        'free_shipping_threshold' => isset($_POST['free_shipping_threshold']) ? (float)$_POST['free_shipping_threshold'] : 0,
        'return_policy_days' => isset($_POST['return_policy_days']) ? (int)$_POST['return_policy_days'] : 14,
        'payment_window_hours' => isset($_POST['payment_window_hours']) ? (int)$_POST['payment_window_hours'] : 48,
        'auto_extend_minutes' => isset($_POST['auto_extend_minutes']) ? (int)$_POST['auto_extend_minutes'] : 2,
    ];

    // Validate
    if ($fees['platform_fee_percentage'] < 0 || $fees['platform_fee_percentage'] > 100) {
        $errors['platform_fee_percentage'] = 'Fee must be between 0 and 100';
    }
    if ($fees['auction_listing_fee'] < 0) {
        $errors['auction_listing_fee'] = 'Fee cannot be negative';
    }
    if ($fees['reserve_price_fee'] < 0) {
        $errors['reserve_price_fee'] = 'Fee cannot be negative';
    }
    if ($fees['free_shipping_threshold'] < 0) {
        $errors['free_shipping_threshold'] = 'Threshold cannot be negative';
    }
    if ($fees['return_policy_days'] < 1) {
        $errors['return_policy_days'] = 'Must be at least 1 day';
    }
    if ($fees['payment_window_hours'] < 1) {
        $errors['payment_window_hours'] = 'Must be at least 1 hour';
    }
    if ($fees['auto_extend_minutes'] < 1) {
        $errors['auto_extend_minutes'] = 'Must be at least 1 minute';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            foreach ($fees as $key => $value) {
                $db->update(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                    [$value, $key]
                );
                
                // If no rows updated, insert
                if ($db->rowCount() == 0) {
                    $db->insert(
                        "INSERT INTO settings (setting_key, setting_value, setting_group) 
                         VALUES (?, ?, 'fees')",
                        [$key, $value]
                    );
                }
            }

            // Log admin action
            $db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, 'update_fees', 'settings', 0, 'Fee configuration updated')",
                [Session::getUserId()]
            );

            $db->commit();
            setSuccess('Fee settings updated successfully');
            redirect(SITE_URL . '/admin/fees.php');

        } catch (Exception $e) {
            $db->rollback();
            setError('Failed to update fees: ' . $e->getMessage());
        }
    }
}

// Refresh settings after update
$settings = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_group = 'fees'"
);
$feeSettings = [];
foreach ($settings as $setting) {
    $feeSettings[$setting['setting_key']] = $setting['setting_value'];
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
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-percent"></i> Fee Configuration</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <p class="text-muted">Configure platform fees and charges. All changes take effect immediately.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo SITE_URL; ?>/admin/fees.php" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="row g-4">
                            <!-- Platform Fees -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6 class="section-title">Platform Fees</h6>
                                    
                                    <div class="mb-3">
                                        <label for="platform_fee_percentage" class="form-label">
                                            Platform Fee Percentage
                                        </label>
                                        <div class="input-group">
                                            <input type="number" 
                                                   class="form-control fee-input" 
                                                   id="platform_fee_percentage" 
                                                   name="platform_fee_percentage" 
                                                   value="<?php echo $feeSettings['platform_fee_percentage'] ?? 10; ?>"
                                                   step="0.5"
                                                   min="0"
                                                   max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Percentage charged on each successful sale</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="auction_listing_fee" class="form-label">
                                            Auction Listing Fee
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control fee-input" 
                                                   id="auction_listing_fee" 
                                                   name="auction_listing_fee" 
                                                   value="<?php echo $feeSettings['auction_listing_fee'] ?? 500.00; ?>"
                                                   step="0.10"
                                                   min="0">
                                        </div>
                                        <small class="text-muted">Fee charged when creating an auction listing</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="reserve_price_fee" class="form-label">
                                            Reserve Price Fee
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control fee-input" 
                                                   id="reserve_price_fee" 
                                                   name="reserve_price_fee" 
                                                   value="<?php echo $feeSettings['reserve_price_fee'] ?? 1000.00; ?>"
                                                   step="0.10"
                                                   min="0">
                                        </div>
                                        <small class="text-muted">Additional fee for using reserve price in auctions</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping & Policies -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h6 class="section-title">Shipping & Policies</h6>
                                    
                                    <div class="mb-3">
                                        <label for="free_shipping_threshold" class="form-label">
                                            Free Shipping Threshold
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control fee-input" 
                                                   id="free_shipping_threshold" 
                                                   name="free_shipping_threshold" 
                                                   value="<?php echo $feeSettings['free_shipping_threshold'] ?? 50000.00; ?>"
                                                   step="1"
                                                   min="0">
                                        </div>
                                        <small class="text-muted">Orders above this amount qualify for free shipping</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="return_policy_days" class="form-label">
                                            Return Policy (Days)
                                        </label>
                                        <input type="number" 
                                               class="form-control fee-input" 
                                               id="return_policy_days" 
                                               name="return_policy_days" 
                                               value="<?php echo $feeSettings['return_policy_days'] ?? 14; ?>"
                                               min="1"
                                               max="90">
                                        <small class="text-muted">Number of days buyers have to return items</small>
                                    </div>
                                </div>

                                <div class="form-section mt-3">
                                    <h6 class="section-title">Auction Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label for="payment_window_hours" class="form-label">
                                            Payment Window (Hours)
                                        </label>
                                        <input type="number" 
                                               class="form-control fee-input" 
                                               id="payment_window_hours" 
                                               name="payment_window_hours" 
                                               value="<?php echo $feeSettings['payment_window_hours'] ?? 48; ?>"
                                               min="1"
                                               max="168">
                                        <small class="text-muted">Time winner has to complete payment after auction ends</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="auto_extend_minutes" class="form-label">
                                            Auto-Extend (Minutes)
                                        </label>
                                        <input type="number" 
                                               class="form-control fee-input" 
                                               id="auto_extend_minutes" 
                                               name="auto_extend_minutes" 
                                               value="<?php echo $feeSettings['auto_extend_minutes'] ?? 2; ?>"
                                               min="1"
                                               max="10">
                                        <small class="text-muted">Minutes to extend auction when bid placed near end</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <span class="save-indicator text-success me-3"></span>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Fee Preview -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calculator"></i> Fee Preview (on ₦100,000 sale)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Fixed Price Item</h6>
                                    <div class="h3 text-primary"><?php echo formatNaira(100000); ?></div>
                                    <div class="text-muted small">
                                        Platform Fee: <?php echo formatNaira(100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100); ?><br>
                                        Seller Gets: <?php echo formatNaira(100000 - (100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Auction Item</h6>
                                    <div class="h3 text-warning"><?php echo formatNaira(100000); ?></div>
                                    <div class="text-muted small">
                                        Listing Fee: <?php echo formatNaira($feeSettings['auction_listing_fee'] ?? 500.00); ?><br>
                                        Platform Fee: <?php echo formatNaira(100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100); ?><br>
                                        Seller Gets: <?php echo formatNaira(100000 - (100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100) - ($feeSettings['auction_listing_fee'] ?? 500.00)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>With Reserve Price</h6>
                                    <div class="h3 text-danger"><?php echo formatNaira(100000); ?></div>
                                    <div class="text-muted small">
                                        Reserve Fee: <?php echo formatNaira($feeSettings['reserve_price_fee'] ?? 1000.00); ?><br>
                                        Platform Fee: <?php echo formatNaira(100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100); ?><br>
                                        Seller Gets: <?php echo formatNaira(100000 - (100000 * ($feeSettings['platform_fee_percentage'] ?? 10) / 100) - ($feeSettings['reserve_price_fee'] ?? 1000.00)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>