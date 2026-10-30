<?php
// marketplace/auth/register.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

if (Session::isLoggedIn()) {
    redirect(SITE_URL . '/');
}

$db = db();
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token. Please try again.');
        redirect(SITE_URL . '/auth/register.php');
    }

    $formData = [
        'email'    => trim((string)($_POST['email'] ?? '')),
        'full_name'=> trim((string)($_POST['full_name'] ?? '')),
        'phone'    => trim((string)($_POST['phone'] ?? '')),
        'state'    => trim((string)($_POST['state'] ?? '')),
        'lga'      => trim((string)($_POST['lga'] ?? '')),
        'address'  => trim((string)($_POST['address'] ?? '')),
        'password' => (string)($_POST['password'] ?? ''),
        'password_confirm' => (string)($_POST['password_confirm'] ?? ''),
    ];

    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$formData['email']]);
        if ($existing) $errors['email'] = 'This email is already registered';
    }

    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Name must be at least 2 characters';
    }

    if (!empty($formData['state']) && !isValidNigerianState($formData['state'])) {
        $errors['state'] = 'Please select a valid Nigerian state';
    }
    if (!empty($formData['state']) && !empty($formData['lga'])) {
        if (!isValidLGA($formData['state'], $formData['lga'])) {
            $errors['lga'] = 'Please select a valid LGA for the chosen state';
        }
    }

    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $formData['password'])) {
        $errors['password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $formData['password'])) {
        $errors['password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $formData['password'])) {
        $errors['password'] = 'Password must contain at least one number';
    }

    if ($formData['password'] !== $formData['password_confirm']) {
        $errors['password_confirm'] = 'Passwords do not match';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $passwordHash = hashPassword($formData['password']);
            $verificationCode = generateToken(32);

            $userId = $db->insert(
                "INSERT INTO users (email, password_hash, full_name, user_type, is_seller, seller_status, phone, state, lga, address, verification_code) 
                 VALUES (?, ?, ?, 'buyer', 0, 'none', ?, ?, ?, ?, ?)",
                [
                    $formData['email'],
                    $passwordHash,
                    $formData['full_name'],
                    $formData['phone'],
                    $formData['state'] ?: null,
                    $formData['lga'] ?: null,
                    $formData['address'],
                    $verificationCode
                ]
            );

            $db->commit();

            // =============================================
            // SEND WELCOME + VERIFICATION EMAIL
            // =============================================
            $verifyLink = SITE_URL . '/auth/verify-email.php?code=' . urlencode($verificationCode) . '&email=' . urlencode($formData['email']);

            $welcomeHtml = renderEmailTemplate('welcome', [
                'USER_NAME'   => $formData['full_name'],
                'VERIFY_LINK' => $verifyLink,
            ]);

            if (empty($welcomeHtml)) {
                // Fallback if the template is missing
                $welcomeHtml = '<h2>Welcome to ' . SITE_NAME . '!</h2>'
                             . '<p>Hi ' . htmlspecialchars($formData['full_name']) . ',</p>'
                             . '<p>Please verify your email by clicking the link below:</p>'
                             . '<p><a href="' . $verifyLink . '">Verify Email</a></p>'
                             . '<p>Or paste this into your browser:</p>'
                             . '<p>' . $verifyLink . '</p>';
            }

            $emailResult = sendEmail(
                $formData['email'],
                'Welcome to ' . SITE_NAME . ' — Please verify your email',
                $welcomeHtml,
                $formData['full_name']
            );

            if (!$emailResult['success']) {
                error_log('Welcome email failed for ' . $formData['email'] . ': ' . ($emailResult['message'] ?? 'unknown'));
            }

            // Auto-login
            Session::set('user_id', $userId);
            Session::set('user_type', 'buyer');
            Session::set('full_name', $formData['full_name']);
            Session::set('email', $formData['email']);
            Session::set('email_verified', false);
            Session::regenerateId();
            Session::regenerateCSRFToken();

            setSuccess('Welcome to ' . SITE_NAME . '! Check your email for the verification link. You can start buying right away.');
            redirect(SITE_URL . '/buyer/dashboard.php');

        } catch (Exception $e) {
            if ($db->getConnection()->inTransaction()) $db->rollback();
            error_log('Registration Error: ' . $e->getMessage());
            setError('Registration failed. Please try again.');
        }
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'auth.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-person-plus"></i> Create Account</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Create your account to buy and sell on <?php echo SITE_NAME; ?>.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-exclamation-triangle"></i> Please fix the following:</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="registerForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                   id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>"
                                   required minlength="2"
                                   placeholder="Enter your full name">
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" 
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                   required placeholder="you@example.com">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                   placeholder="+234 801 234 5678">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="state" class="form-label">State of Residence</label>
                                <select class="form-select <?php echo isset($errors['state']) ? 'is-invalid' : ''; ?>" 
                                        id="state" name="state" onchange="loadLGAs(this.value)">
                                    <?php echo getStateOptions($formData['state'] ?? '', 'Select State'); ?>
                                </select>
                                <?php if (isset($errors['state'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['state']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="lga" class="form-label">Local Government Area(LGA)</label>
                                <select class="form-select <?php echo isset($errors['lga']) ? 'is-invalid' : ''; ?>" 
                                        id="lga" name="lga">
                                    <?php echo getLGAOptions($formData['state'] ?? '', $formData['lga'] ?? '', 'Select LGA'); ?>
                                </select>
                                <?php if (isset($errors['lga'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['lga']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Street Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"
                                      placeholder="e.g., 12 Broad Street, Victoria Island"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" 
                                       required minlength="8"
                                       placeholder="Min 8 chars, 1 upper, 1 lower, 1 number">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>" 
                                       id="password_confirm" name="password_confirm" 
                                       required placeholder="Confirm your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errors['password_confirm'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['password_confirm']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" target="_blank">Terms of Service</a> and 
                                    <a href="#" target="_blank">Privacy Policy</a> <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>

                        <div class="text-center mt-3">
                            <p class="text-muted">
                                Already have an account? <a href="<?php echo SITE_URL; ?>/auth/login.php">Login here</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const nigeriaStatesLGAs = <?php echo getStatesLGAsJson(); ?>;

function loadLGAs(state) {
    const lgaSelect = document.getElementById('lga');
    const currentLGA = '<?php echo htmlspecialchars($formData['lga'] ?? ''); ?>';
    lgaSelect.innerHTML = '<option value="">Select LGA</option>';
    if (!state || !nigeriaStatesLGAs[state]) return;
    nigeriaStatesLGAs[state].forEach(function(lga) {
        const option = document.createElement('option');
        option.value = lga;
        option.textContent = lga;
        if (lga === currentLGA) option.selected = true;
        lgaSelect.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.getElementById('state');
    if (stateSelect && stateSelect.value) loadLGAs(stateSelect.value);

    ['togglePassword', 'togglePasswordConfirm'].forEach(function(id) {
        const btn = document.getElementById(id);
        if (!btn) return;
        const inputId = id === 'togglePassword' ? 'password' : 'password_confirm';
        const input = document.getElementById(inputId);
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>