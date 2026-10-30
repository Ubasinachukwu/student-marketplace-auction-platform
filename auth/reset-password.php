<?php
// marketplace/auth/reset-password.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// =============================================
// FIX: Force session start before any checks
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If logged in, redirect to dashboard
if (Session::isLoggedIn()) {
    $userType = Session::getUserType();
    if ($userType === 'admin') redirect(SITE_URL . '/admin/dashboard.php');
    elseif ($userType === 'seller') redirect(SITE_URL . '/seller/dashboard.php');
    else redirect(SITE_URL . '/buyer/dashboard.php');
}

$db = db();
$error = '';
$success = false;
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// =============================================
// VALIDATE TOKEN
// =============================================
if (empty($token) || empty($email)) {
    setError('Invalid password reset link.');
    redirect(SITE_URL . '/auth/forgot-password.php');
}

// Verify token
$user = $db->fetchOne(
    "SELECT id, email, full_name, reset_token, reset_expires FROM users 
     WHERE email = ? AND reset_token = ? AND reset_expires > NOW()",
    [$email, $token]
);

if (!$user) {
    setError('Invalid or expired password reset link. Please request a new one.');
    redirect(SITE_URL . '/auth/forgot-password.php');
}

// =============================================
// PROCESS PASSWORD RESET
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token. Please try again.');
        Session::regenerateCSRFToken();
        redirect(SITE_URL . '/auth/reset-password.php?token=' . $token . '&email=' . urlencode($email));
    }

    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validate password
    if (empty($password)) {
        $error = 'Password is required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match';
    } else {
        // Hash new password
        $passwordHash = hashPassword($password);
        
        // Update password and clear reset tokens
        $db->update(
            "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$passwordHash, $user['id']]
        );

        // Log password reset
        logActivity($user['id'], 'password_reset', 'Password reset successfully');

        setSuccess('Password reset successfully! You can now login with your new password.');
        redirect(SITE_URL . '/auth/login.php?reset=1');
    }
}

// Generate CSRF token
$csrfToken = Session::generateCSRFToken();

// Page specific JS
$pageJS = 'auth.js';

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-key"></i> Set New Password</h4>
                </div>
                <div class="card-body">
                    <!-- User Greeting -->
                    <p class="text-muted">
                        <i class="bi bi-person-circle"></i> Hi <?php echo htmlspecialchars($user['full_name']); ?>, 
                        please enter your new password.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="resetPasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required
                                       minlength="8"
                                       placeholder="Min 8 characters with uppercase, lowercase, number">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Must contain at least 8 characters, one uppercase, one lowercase, and one number.</small>
                        </div>

                        <!-- Password Strength Bar -->
                        <div class="mb-3">
                            <div class="progress" style="height: 5px;">
                                <div id="passwordStrength" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <small id="passwordStrengthText" class="text-muted">Password strength: </small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       required
                                       placeholder="Confirm your new password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatchFeedback" class="invalid-feedback" style="display: none;">Passwords do not match</div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-check-circle"></i> Reset Password
                        </button>

                        <div class="text-center mt-3">
                            <p class="text-muted">
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left"></i> Back to Login
                                </a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- ADDITIONAL JAVASCRIPT FOR RESET PASSWORD -->
<!-- ============================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // =============================================
    // CONFIRM PASSWORD TOGGLE
    // =============================================
    const toggleConfirmBtn = document.getElementById('togglePasswordConfirm');
    const confirmPasswordInput = document.getElementById('password_confirm');
    
    if (toggleConfirmBtn && confirmPasswordInput) {
        toggleConfirmBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                confirmPasswordInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
            confirmPasswordInput.focus();
        });
    }

    // =============================================
    // PASSWORD MATCH VALIDATION (Real-time)
    // =============================================
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('password_confirm');
    const matchFeedback = document.getElementById('passwordMatchFeedback');

    if (passwordField && confirmField && matchFeedback) {
        function checkPasswordMatch() {
            if (confirmField.value.length > 0) {
                if (passwordField.value !== confirmField.value) {
                    confirmField.classList.add('is-invalid');
                    matchFeedback.style.display = 'block';
                    confirmField.setCustomValidity('Passwords do not match');
                } else {
                    confirmField.classList.remove('is-invalid');
                    matchFeedback.style.display = 'none';
                    confirmField.setCustomValidity('');
                }
            } else {
                confirmField.classList.remove('is-invalid');
                matchFeedback.style.display = 'none';
                confirmField.setCustomValidity('');
            }
        }

        passwordField.addEventListener('input', checkPasswordMatch);
        confirmField.addEventListener('input', checkPasswordMatch);
    }

    // =============================================
    // PASSWORD STRENGTH METER (from auth.js)
    // =============================================
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');

    if (passwordField && strengthBar && strengthText) {
        passwordField.addEventListener('input', function() {
            const password = this.value;
            let score = 0;

            // Length check
            if (password.length >= 8) score += 1;
            if (password.length >= 12) score += 1;

            // Complexity checks
            if (/[a-z]/.test(password)) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^a-zA-Z0-9]/.test(password)) score += 1;

            // Calculate percentage
            const percentage = Math.min((score / 6) * 100, 100);
            
            // Update bar
            strengthBar.style.width = percentage + '%';
            
            // Update colors and text
            let color, label;
            if (percentage < 20) {
                color = '#dc3545';
                label = 'Very Weak';
            } else if (percentage < 40) {
                color = '#ffc107';
                label = 'Weak';
            } else if (percentage < 60) {
                color = '#fd7e14';
                label = 'Fair';
            } else if (percentage < 80) {
                color = '#0d6efd';
                label = 'Good';
            } else {
                color = '#198754';
                label = 'Strong';
            }

            strengthBar.style.background = color;
            strengthText.textContent = 'Password strength: ' + label;
            strengthText.style.color = color;
        });
    }

    // =============================================
    // AUTO-SUBMIT ON ENTER KEY (Accessibility)
    // =============================================
    const form = document.getElementById('resetPasswordForm');
    if (form) {
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.submit();
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>