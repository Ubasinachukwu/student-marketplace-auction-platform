<?php
// marketplace/seller/settings.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/settings.php');
    }

    $section = sanitize($_POST['section'] ?? 'profile');
    $errors = [];

    try {
        $db->beginTransaction();

        if ($section === 'profile') {
            $fullName = sanitize($_POST['full_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $state = sanitize($_POST['state'] ?? '');
            $lga = sanitize($_POST['lga'] ?? '');
            $address = sanitize($_POST['address'] ?? '');

            if (empty($fullName)) {
                $errors[] = 'Full name is required';
            }

            if (!empty($state) && !isValidNigerianState($state)) {
                $errors[] = 'Please select a valid Nigerian state';
            }

            if (!empty($state) && !empty($lga) && !isValidLGA($state, $lga)) {
                $errors[] = 'Please select a valid LGA for the chosen state';
            }

            if (!empty($errors)) {
                throw new Exception(implode('<br>', $errors));
            }

            $db->update(
                "UPDATE users 
                 SET full_name = ?, phone = ?, state = ?, lga = ?, address = ?
                 WHERE id = ?",
                [$fullName, $phone, $state ?: null, $lga ?: null, $address, $userId]
            );

            Session::set('full_name', $fullName);
            logActivity($userId, 'profile_update', 'Seller profile updated');

            $message = 'Profile updated successfully!';

        } elseif ($section === 'store') {
            $storeName = sanitize($_POST['store_name'] ?? '');
            $storeDescription = sanitize($_POST['store_description'] ?? '');

            if (empty($storeName)) {
                $errors[] = 'Store name is required';
            }

            if (!empty($errors)) {
                throw new Exception(implode('<br>', $errors));
            }

            // Update or insert seller profile
            $exists = $db->fetchOne(
                "SELECT id FROM seller_profiles WHERE user_id = ?",
                [$userId]
            );

            if ($exists) {
                $db->update(
                    "UPDATE seller_profiles 
                     SET store_name = ?, store_description = ?
                     WHERE user_id = ?",
                    [$storeName, $storeDescription, $userId]
                );
            } else {
                $db->insert(
                    "INSERT INTO seller_profiles (user_id, store_name, store_description) 
                     VALUES (?, ?, ?)",
                    [$userId, $storeName, $storeDescription]
                );
            }

            logActivity($userId, 'store_update', 'Store details updated');

            $message = 'Store details updated successfully!';

        } elseif ($section === 'password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $user = $db->fetchOne(
                "SELECT password_hash FROM users WHERE id = ?",
                [$userId]
            );

            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            }

            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $newPassword)) {
                $errors[] = 'Password must contain at least one uppercase letter';
            } elseif (!preg_match('/[a-z]/', $newPassword)) {
                $errors[] = 'Password must contain at least one lowercase letter';
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $errors[] = 'Password must contain at least one number';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            }

            if (!empty($errors)) {
                throw new Exception(implode('<br>', $errors));
            }

            $newHash = hashPassword($newPassword);
            $db->update(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [$newHash, $userId]
            );

            logActivity($userId, 'password_change', 'Seller password changed');

            $message = 'Password updated successfully!';

        } elseif ($section === 'payout') {
            $payoutMethod = sanitize($_POST['payout_method'] ?? 'bank');
            $payoutDetails = [];

            if ($payoutMethod === 'bank') {
                $payoutDetails['bank_name'] = sanitize($_POST['bank_name'] ?? '');
                $payoutDetails['account_name'] = sanitize($_POST['account_name'] ?? '');
                $payoutDetails['account_number'] = sanitize($_POST['account_number'] ?? '');
            } elseif ($payoutMethod === 'paystack') {
                $payoutDetails['paystack_email'] = sanitize($_POST['paystack_email'] ?? '');
            }

            $db->update(
                "UPDATE seller_profiles 
                 SET payout_method = ?, payout_details = ?
                 WHERE user_id = ?",
                [$payoutMethod, json_encode($payoutDetails), $userId]
            );

            $message = 'Payout settings updated successfully!';
        }

        $db->commit();
        setSuccess($message);
        redirect(SITE_URL . '/seller/settings.php?section=' . $section);

    } catch (Exception $e) {
        $db->rollback();
        setError($e->getMessage());
        redirect(SITE_URL . '/seller/settings.php?section=' . $section);
    }
}

// Get user data
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);

// Get seller profile
$sellerProfile = $db->fetchOne(
    "SELECT * FROM seller_profiles WHERE user_id = ?",
    [$userId]
);

$currentSection = isset($_GET['section']) ? sanitize($_GET['section']) : 'profile';
$csrfToken = Session::generateCSRFToken();
$pageJS = 'settings.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-gear"></i> Seller Settings</h1>
            </div>

            <div class="row">
                <!-- Settings Menu -->
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <div class="list-group list-group-flush">
                            <a href="<?php echo SITE_URL; ?>/seller/settings.php?section=profile" 
                               class="list-group-item list-group-item-action <?php echo $currentSection == 'profile' ? 'active' : ''; ?>">
                                <i class="bi bi-person"></i> Profile Information
                            </a>
                            <a href="<?php echo SITE_URL; ?>/seller/settings.php?section=store" 
                               class="list-group-item list-group-item-action <?php echo $currentSection == 'store' ? 'active' : ''; ?>">
                                <i class="bi bi-shop"></i> Store Details
                            </a>
                            <a href="<?php echo SITE_URL; ?>/seller/settings.php?section=password" 
                               class="list-group-item list-group-item-action <?php echo $currentSection == 'password' ? 'active' : ''; ?>">
                                <i class="bi bi-lock"></i> Change Password
                            </a>
                            <a href="<?php echo SITE_URL; ?>/seller/settings.php?section=payout" 
                               class="list-group-item list-group-item-action <?php echo $currentSection == 'payout' ? 'active' : ''; ?>">
                                <i class="bi bi-cash-coin"></i> Payout Settings
                            </a>
                        </div>
                    </div>

                    <!-- Profile Summary Card -->
                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <img src="<?php echo getProfileImageUrl($user['profile_pic'] ?? null); ?>" 
                                 class="rounded-circle mb-2" 
                                 style="width: 80px; height: 80px; object-fit: cover;">
                            <h6 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($user['email']); ?></small>
                            <?php if (!empty($sellerProfile['store_name'])): ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-shop"></i> <?php echo htmlspecialchars($sellerProfile['store_name']); ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($user['state']) && !empty($user['lga'])): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($user['lga']); ?>, 
                                    <?php echo htmlspecialchars($user['state']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Settings Form -->
                <div class="col-md-9">
                    <?php if ($currentSection == 'profile'): ?>
                        <!-- Profile Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo SITE_URL; ?>/seller/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="section" value="profile">

                                    <div class="row g-3">
                                        <!-- Full Name -->
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">
                                                Full Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="full_name" 
                                                   name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                   required>
                                        </div>

                                        <!-- Email (Read-only) -->
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                                   readonly
                                                   disabled>
                                            <small class="text-muted">Email cannot be changed</small>
                                        </div>

                                        <!-- Phone -->
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                   placeholder="+234 801 234 5678">
                                        </div>

                                        <!-- State -->
                                        <div class="col-md-6">
                                            <label for="state" class="form-label">State of Residence</label>
                                            <select class="form-select" 
                                                    id="state" 
                                                    name="state"
                                                    onchange="loadLGAs(this.value)">
                                                <?php echo getStateOptions($user['state'] ?? '', 'Select State'); ?>
                                            </select>
                                        </div>

                                        <!-- LGA -->
                                        <div class="col-md-6">
                                            <label for="lga" class="form-label">Local Government Area (LGA)</label>
                                            <select class="form-select" id="lga" name="lga">
                                                <?php echo getLGAOptions($user['state'] ?? '', $user['lga'] ?? '', 'Select LGA'); ?>
                                            </select>
                                        </div>

                                        <!-- Address -->
                                        <div class="col-12">
                                            <label for="address" class="form-label">Business Address</label>
                                            <textarea class="form-control" 
                                                      id="address" 
                                                      name="address" 
                                                      rows="3"
                                                      placeholder="e.g., 12 Broad Street, Victoria Island"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                            <small class="text-muted">Your business/store address</small>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($currentSection == 'store'): ?>
                        <!-- Store Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shop"></i> Store Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo SITE_URL; ?>/seller/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="section" value="store">

                                    <div class="mb-3">
                                        <label for="store_name" class="form-label">
                                            Store Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="store_name" 
                                               name="store_name" 
                                               value="<?php echo htmlspecialchars($sellerProfile['store_name'] ?? $user['full_name'] . "'s Store"); ?>"
                                               required>
                                        <small class="text-muted">This is what buyers will see</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="store_description" class="form-label">Store Description</label>
                                        <textarea class="form-control" 
                                                  id="store_description" 
                                                  name="store_description" 
                                                  rows="4"
                                                  placeholder="Tell buyers about your store..."><?php echo htmlspecialchars($sellerProfile['store_description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Save Store Details
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($currentSection == 'password'): ?>
                        <!-- Password Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-lock"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo SITE_URL; ?>/seller/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="section" value="password">

                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> 
                                        Choose a strong password with at least 8 characters, one uppercase, one lowercase, and one number.
                                    </div>

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">
                                            Current Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="current_password" 
                                                   name="current_password" 
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">
                                            New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="new_password" 
                                                   name="new_password" 
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">
                                            Confirm New Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div id="passwordMatchFeedback" class="invalid-feedback" style="display: none;">
                                            Passwords do not match
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($currentSection == 'payout'): ?>
                        <!-- Payout Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Payout Settings</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Configure how you want to receive your earnings.</p>

                                <form method="POST" action="<?php echo SITE_URL; ?>/seller/settings.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="section" value="payout">

                                    <?php 
                                    $currentMethod = $sellerProfile['payout_method'] ?? 'bank';
                                    $currentDetails = !empty($sellerProfile['payout_details']) 
                                        ? json_decode($sellerProfile['payout_details'], true) 
                                        : [];
                                    ?>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <div class="form-check card p-3 <?php echo $currentMethod == 'bank' ? 'border-primary' : ''; ?>">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="payout_method" 
                                                       id="payout_bank" 
                                                       value="bank"
                                                       <?php echo $currentMethod == 'bank' ? 'checked' : ''; ?>
                                                       onchange="togglePayoutMethod('bank')">
                                                <label class="form-check-label w-100" for="payout_bank">
                                                    <i class="bi bi-bank fs-3 text-primary"></i>
                                                    <div><strong>Bank Transfer</strong></div>
                                                    <small class="text-muted">Nigerian bank account</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check card p-3 <?php echo $currentMethod == 'paystack' ? 'border-primary' : ''; ?>">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="payout_method" 
                                                       id="payout_paystack" 
                                                       value="paystack"
                                                       <?php echo $currentMethod == 'paystack' ? 'checked' : ''; ?>
                                                       onchange="togglePayoutMethod('paystack')">
                                                <label class="form-check-label w-100" for="payout_paystack">
                                                    <i class="bi bi-credit-card fs-3 text-success"></i>
                                                    <div><strong>Paystack</strong></div>
                                                    <small class="text-muted">Card/Bank payouts</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bank Fields -->
                                    <div id="bankFields" style="display: <?php echo $currentMethod == 'bank' ? 'block' : 'none'; ?>;">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label for="bank_name" class="form-label">Bank Name</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="bank_name" 
                                                       name="bank_name" 
                                                       value="<?php echo htmlspecialchars($currentDetails['bank_name'] ?? ''); ?>"
                                                       placeholder="e.g., GTBank, First Bank, Access Bank">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="account_name" class="form-label">Account Name</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="account_name" 
                                                       name="account_name" 
                                                       value="<?php echo htmlspecialchars($currentDetails['account_name'] ?? ''); ?>"
                                                       placeholder="Name on account">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="account_number" class="form-label">Account Number</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="account_number" 
                                                       name="account_number" 
                                                       value="<?php echo htmlspecialchars($currentDetails['account_number'] ?? ''); ?>"
                                                       placeholder="10-digit NUBAN">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paystack Fields -->
                                    <div id="paystackFields" style="display: <?php echo $currentMethod == 'paystack' ? 'block' : 'none'; ?>;">
                                        <div class="mb-3">
                                            <label for="paystack_email" class="form-label">Paystack Email</label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="paystack_email" 
                                                   name="paystack_email" 
                                                   value="<?php echo htmlspecialchars($currentDetails['paystack_email'] ?? ''); ?>"
                                                   placeholder="your@paystack.com">
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Save Payout Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const nigeriaStatesLGAs = <?php echo getStatesLGAsJson(); ?>;

function loadLGAs(state) {
    const lgaSelect = document.getElementById('lga');
    const currentLGA = '<?php echo htmlspecialchars($user['lga'] ?? ''); ?>';
    
    lgaSelect.innerHTML = '<option value="">Select LGA</option>';
    
    if (!state || !nigeriaStatesLGAs[state]) {
        return;
    }
    
    nigeriaStatesLGAs[state].forEach(function(lga) {
        const option = document.createElement('option');
        option.value = lga;
        option.textContent = lga;
        if (lga === currentLGA) {
            option.selected = true;
        }
        lgaSelect.appendChild(option);
    });
}

function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const btn = input.nextElementSibling;
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function togglePayoutMethod(method) {
    document.getElementById('bankFields').style.display = method === 'bank' ? 'block' : 'none';
    document.getElementById('paystackFields').style.display = method === 'paystack' ? 'block' : 'none';
    
    document.querySelectorAll('.form-check.card').forEach(c => c.classList.remove('border-primary'));
    document.getElementById('payout_' + method).closest('.form-check.card').classList.add('border-primary');
}

document.addEventListener('DOMContentLoaded', function() {
    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    const feedback = document.getElementById('passwordMatchFeedback');

    if (newPass && confirmPass && feedback) {
        function checkMatch() {
            if (confirmPass.value.length > 0 && newPass.value !== confirmPass.value) {
                confirmPass.classList.add('is-invalid');
                feedback.style.display = 'block';
            } else {
                confirmPass.classList.remove('is-invalid');
                feedback.style.display = 'none';
            }
        }
        newPass.addEventListener('input', checkMatch);
        confirmPass.addEventListener('input', checkMatch);
    }

    const stateSelect = document.getElementById('state');
    if (stateSelect && stateSelect.value) {
        loadLGAs(stateSelect.value);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>