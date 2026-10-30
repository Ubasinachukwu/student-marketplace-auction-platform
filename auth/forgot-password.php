<?php
// marketplace/auth/forgot-password.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (Session::isLoggedIn()) {
    $userType = Session::getUserType();
    if ($userType === 'admin') redirect(SITE_URL . '/admin/dashboard.php');
    elseif ($userType === 'seller') redirect(SITE_URL . '/seller/dashboard.php');
    else redirect(SITE_URL . '/buyer/dashboard.php');
}

$db = db();
$error = '';
$success = false;
$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token. Please try again.');
        Session::regenerateCSRFToken();
        redirect(SITE_URL . '/auth/forgot-password.php');
    }

    $email = trim((string)($_POST['email'] ?? ''));

    if (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $user = $db->fetchOne(
            "SELECT id, email, full_name FROM users WHERE email = ?",
            [$email]
        );

        if ($user) {
            $resetToken = generateToken(32);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db->update(
                "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
                [$resetToken, $expires, $user['id']]
            );

            $resetLink = SITE_URL . '/auth/reset-password.php?token=' . urlencode($resetToken) . '&email=' . urlencode($email);

            // Try template first; fall back to inline HTML
            $resetHtml = renderEmailTemplate('password-reset', [
                'USER_NAME'  => $user['full_name'],
                'RESET_LINK' => $resetLink,
            ]);

            if (empty($resetHtml)) {
                $resetHtml = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #0d6efd;">Password Reset Request</h2>
                        <p>Hi ' . htmlspecialchars($user['full_name']) . ',</p>
                        <p>We received a request to reset your password. Click the button below to reset it:</p>
                        <p style="text-align: center;">
                            <a href="' . $resetLink . '" 
                               style="display: inline-block; padding: 12px 24px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                                Reset Password
                            </a>
                        </p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p style="word-break: break-all;"><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                        <p><strong>This link will expire in 1 hour.</strong></p>
                        <p>If you didn\'t request this, please ignore this email.</p>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                        <p style="color: #6c757d; font-size: 12px;">
                            Best regards,<br>' . SITE_NAME . ' Team
                        </p>
                    </div>
                ';
            }

            $sendResult = sendEmail(
                $email,
                'Password Reset — ' . SITE_NAME,
                $resetHtml,
                $user['full_name']
            );

            if (!$sendResult['success']) {
                error_log('Password reset email failed for ' . $email . ': ' . ($sendResult['message'] ?? 'unknown'));
            }

            if (function_exists('logActivity')) {
                logActivity($user['id'], 'password_reset_request', 'Password reset requested');
            }
        }

        // Always show the same message (don't reveal whether the email exists)
        $success = true;
        $message = 'If an account exists with this email, you will receive a password reset link.';
        error_log("Password reset requested for: " . $email . " at " . date('Y-m-d H:i:s'));
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'auth.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-key"></i> Reset Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Return to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>

                        <form method="POST" action="" id="forgotPasswordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($email); ?>"
                                           required autofocus
                                           placeholder="you@example.com">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 py-2">
                                <i class="bi bi-envelope"></i> Send Reset Link
                            </button>

                            <div class="text-center mt-3">
                                <p class="text-muted">
                                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="text-decoration-none">
                                        <i class="bi bi-arrow-left"></i> Back to Login
                                    </a>
                                </p>
                                <p class="text-muted small">
                                    Don't have an account? <a href="<?php echo SITE_URL; ?>/auth/register.php" class="text-decoration-none">Register here</a>
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('email');

    if (form && emailInput) {
        emailInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.submit();
            }
        });
    }

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const isValid = email.length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (email.length > 0 && !isValid) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>