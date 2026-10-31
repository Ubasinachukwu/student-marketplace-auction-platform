<?php
// marketplace/buyer/settings.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token.');
        redirect(SITE_URL . '/buyer/settings.php');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_profile') {
            require __DIR__ . '/../api/user/update-profile.php';
        } elseif ($action === 'change_password') {
            require __DIR__ . '/../api/user/change-password.php';
        } elseif ($action === 'update_store') {
            require __DIR__ . '/../api/user/update-store.php';
        }
    } catch (Exception $e) {
        error_log('Settings error: ' . $e->getMessage());
        setError('An error occurred. Please try again.');
        redirect(SITE_URL . '/buyer/settings.php');
    }
}

// Load fresh user data
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Load seller profile if user is a seller
$sellerProfile = null;
if ((int)$user['is_seller'] === 1) {
    $sellerProfile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$userId]);
}

$activeTab = $_GET['tab'] ?? 'profile';
if (!in_array($activeTab, ['profile', 'password', 'store'], true)) {
    $activeTab = 'profile';
}

$csrfToken = Session::generateCSRFToken();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-gear"></i> Account Settings</h1>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" 
           href="<?php echo SITE_URL; ?>/buyer/settings.php?tab=profile">
            <i class="bi bi-person"></i> Profile
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'password' ? 'active' : ''; ?>" 
           href="<?php echo SITE_URL; ?>/buyer/settings.php?tab=password">
            <i class="bi bi-lock"></i> Password
        </a>
    </li>
    <?php if ((int)$user['is_seller'] === 1 && $user['seller_status'] === 'active'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'store' ? 'active' : ''; ?>" 
               href="<?php echo SITE_URL; ?>/buyer/settings.php?tab=store">
                <i class="bi bi-shop"></i> Store
            </a>
        </li>
    <?php endif; ?>
</ul>

            <!-- PROFILE TAB -->
            <?php if ($activeTab === 'profile'): ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <!-- Avatar -->
                            <div class="mb-4 text-center">
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="<?php echo getProfileImageUrl($user['profile_pic']); ?>" 
                                         class="rounded-circle mb-3" 
                                         style="width: 120px; height: 120px; object-fit: cover;"
                                         alt="Profile picture">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                                         style="width: 120px; height: 120px; font-size: 3rem; font-weight: 700;">
                                        <?php echo strtoupper(substr($user['full_name'] ?: 'U', 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <label for="profile_pic" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-camera"></i> Change Picture
                                    </label>
                                    <input type="file" id="profile_pic" name="profile_pic" 
                                           accept="image/*" class="d-none">
                                    <small class="text-muted d-block mt-1">JPG, PNG or WEBP, max 5MB</small>
                                </div>
                            </div>

                            <!-- Basic info -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email (cannot change)</label>
                                    <input type="email" class="form-control" disabled
                                           value="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                           placeholder="+234 801 234 5678">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">State</label>
                                    <select class="form-select" id="state" name="state" onchange="loadLGAs(this.value)">
                                        <?php echo getStateOptions($user['state'] ?? '', 'Select State'); ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">LGA</label>
                                    <select class="form-select" id="lga" name="lga">
                                        <?php echo getLGAOptions($user['state'] ?? '', $user['lga'] ?? '', 'Select LGA'); ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Street Address</label>
                                    <input type="text" name="address" class="form-control"
                                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <!-- PASSWORD TAB -->
            <?php elseif ($activeTab === 'password'): ?>
                <div class="card" style="max-width: 500px;">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required minlength="8">
                                <small class="text-muted">Min 8 characters, 1 uppercase, 1 lowercase, 1 number</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-lock"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

            <!-- STORE TAB (sellers only) -->
            <?php elseif ($activeTab === 'store'): ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="update_store">

                            <div class="mb-3">
                                <label class="form-label">Store Name</label>
                                <input type="text" name="store_name" class="form-control" maxlength="100"
                                       value="<?php echo htmlspecialchars($sellerProfile['store_name'] ?? ($user['full_name'] . "'s Store")); ?>">
                                <small class="text-muted">This appears on your listings</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Store Description</label>
                                <textarea name="store_description" class="form-control" rows="4"
                                          maxlength="500" placeholder="Tell buyers about your store..."><?php echo htmlspecialchars($sellerProfile['store_description'] ?? ''); ?></textarea>
                            </div>

                            <hr>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payout Method</label>
                                    <select name="payout_method" class="form-select">
                                        <option value="bank" <?php echo ($sellerProfile['payout_method'] ?? '') === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                                       
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payout Details</label>
                                    <textarea name="payout_details" class="form-control" rows="2"
                                        placeholder="Bank: 0123456789, GTBank&#10;"><?php echo htmlspecialchars($sellerProfile['payout_details'] ?? ''); ?></textarea>
                                    <small class="text-muted">Only visible to admins</small>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Save Store Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
const nigeriaStatesLGAs = <?php echo getStatesLGAsJson(); ?>;

const savedBuyerLGA = <?php echo json_encode(
    $user['lga'] ?? '',
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
); ?>;

function loadLGAs(state, selectedLGA = '') {
    const lgaSelect = document.getElementById('lga');

    if (!lgaSelect) {
        return;
    }

    lgaSelect.innerHTML = '<option value="">Select LGA</option>';

    if (
        !state ||
        !Object.prototype.hasOwnProperty.call(nigeriaStatesLGAs, state)
    ) {
        return;
    }

    nigeriaStatesLGAs[state].forEach(function(lga) {
        const option = document.createElement('option');

        option.value = lga;
        option.textContent = lga;

        if (lga === selectedLGA) {
            option.selected = true;
        }

        lgaSelect.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {

    // Restore the user's saved LGA when the profile tab loads.
    const stateSelect = document.getElementById('state');

    if (stateSelect && stateSelect.value) {
        loadLGAs(stateSelect.value, savedBuyerLGA);
    }

    // Profile picture preview.
    const profilePictureInput = document.getElementById('profile_pic');

    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function(ev) {
                const avatar = document.querySelector('.rounded-circle');

                if (avatar && avatar.tagName === 'IMG') {
                    avatar.src = ev.target.result;
                }
            };

            reader.readAsDataURL(file);
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>