<?php
// marketplace/seller/payout-settings.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Get current settings
$profile = $db->fetchOne(
    "SELECT payout_method, payout_details FROM seller_profiles WHERE user_id = ?",
    [$userId]
);

// Bank transfer is the only supported payout method.
// Existing 'paypal' rows are preserved — we just don't offer the option
// when saving new settings.
$payoutMethod = 'bank';
$payoutDetails = $profile['payout_details'] ? json_decode($profile['payout_details'], true) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/payout-settings.php');
    }

    $details = [];
    $details['bank_name']      = sanitize($_POST['bank_name']      ?? '');
    $details['account_name']   = sanitize($_POST['account_name']   ?? '');
    $details['account_number'] = sanitize($_POST['account_number'] ?? '');
    $details['routing_number'] = sanitize($_POST['routing_number'] ?? '');
    $details['swift_code']     = sanitize($_POST['swift_code']     ?? '');

    if (empty($details['bank_name']) || empty($details['account_name']) || empty($details['account_number'])) {
        setError('Please fill in all required bank details');
        redirect(SITE_URL . '/seller/payout-settings.php');
    }

    try {
        $db->update(
            "UPDATE seller_profiles SET payout_method = 'bank', payout_details = ? WHERE user_id = ?",
            [json_encode($details), $userId]
        );

        setSuccess('Payout settings updated successfully');
        redirect(SITE_URL . '/seller/payout-settings.php');

    } catch (Exception $e) {
        setError('Failed to update settings: ' . $e->getMessage());
        error_log('Payout Settings Error: ' . $e->getMessage());
        redirect(SITE_URL . '/seller/payout-settings.php');
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'payout-settings.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="bi bi-gear"></i> Payout Settings</h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Configure how you want to receive payments from your sales.</p>

                            <div class="alert alert-info">
                                <i class="bi bi-bank"></i>
                                <strong>Bank Transfer</strong> is the supported payout method. Payments are remitted to your Nigerian bank account.
                            </div>

                            <form method="POST" action="<?php echo SITE_URL; ?>/seller/payout-settings.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="payout_method" value="bank">

                                <div id="bankFields" class="form-section">
                                    <h6 class="section-title"><i class="bi bi-bank"></i> Bank Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="bank_name" class="form-label">Bank Name <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="bank_name" 
                                                   name="bank_name" 
                                                   value="<?php echo htmlspecialchars($payoutDetails['bank_name'] ?? ''); ?>"
                                                   placeholder="e.g., GTBank, First Bank, Zenith">
                                        </div>
                                        <div class="col-md-12">
                                            <label for="account_name" class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="account_name" 
                                                   name="account_name" 
                                                   value="<?php echo htmlspecialchars($payoutDetails['account_name'] ?? ''); ?>"
                                                   placeholder="Full name on the account">
                                        </div>
                                        <div class="col-md-12">
                                            <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="account_number" 
                                                   name="account_number" 
                                                   value="<?php echo htmlspecialchars($payoutDetails['account_number'] ?? ''); ?>"
                                                   placeholder="10-digit NUBAN account number">
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Your bank details are used only for payouts and are stored securely.</small>
                                </div>

                                <!-- Payout Summary -->
                                <div class="card bg-light mt-4">
                                    <div class="card-body">
                                        <h6><i class="bi bi-info-circle"></i> Payout Information</h6>
                                        <ul class="text-muted small mb-0">
                                            <li>Payouts are processed automatically when orders are completed.</li>
                                            <li>Minimum payout amount: ₦10.00</li>
                                            <li>Payouts are typically processed within 2-5 business days.</li>
                                            <li>You'll receive a notification when a payout is initiated.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Settings
                                    </button>
                                    <a href="<?php echo SITE_URL; ?>/seller/dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Current Payout Method Display -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-check-circle"></i> Current Payout Method</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="display-6 me-3">
                                    <i class="bi bi-bank text-success"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Bank Transfer</div>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($payoutDetails['bank_name'] ?? 'Not set'); ?> - 
                                        <?php echo htmlspecialchars($payoutDetails['account_name'] ?? 'Not set'); ?>
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