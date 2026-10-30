<?php
// marketplace/auth/verify-email.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

// Force session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
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
$userName = '';

// Get verification parameters
$code = $_GET['code'] ?? '';
$email = $_GET['email'] ?? '';

// =============================================
// VALIDATE VERIFICATION LINK
// =============================================
if (empty($code) || empty($email)) {
    setError('Invalid verification link.');
    redirect(SITE_URL . '/auth/login.php');
}

$user = $db->fetchOne(
    "SELECT id, email, full_name, email_verified, verification_code FROM users WHERE email = ? AND verification_code = ?",
    [$email, $code]
);

if (!$user) {
    setError('Invalid verification link. Please contact support.');
    redirect(SITE_URL . '/auth/login.php');
}

if ($user['email_verified']) {
    setInfo('Your email is already verified. You can login now.');
    redirect(SITE_URL . '/auth/login.php?already_verified=1');
}

// =============================================
// PROCESS VERIFICATION
// =============================================
try {
    // Update user as verified
    $db->update(
        "UPDATE users SET email_verified = 1, verification_code = NULL, email_verified_at = NOW() WHERE id = ?",
        [$user['id']]
    );

    // Log verification
    if (function_exists('logActivity')) {
        logActivity($user['id'], 'email_verify', 'Email verified successfully');
    }

    // Send welcome email via SMTP
    $userName = $user['full_name'];

    $welcomeHtml = renderEmailTemplate('welcome', [
        'USER_NAME'   => $userName,
        'VERIFY_LINK' => SITE_URL . '/auth/login.php',
    ]);

    sendEmail($email, 'Welcome to ' . SITE_NAME, $welcomeHtml, $userName);

    setSuccess('Email verified successfully! You can now login.');
    redirect(SITE_URL . '/auth/login.php?verified=1');

} catch (Exception $e) {
    error_log('Verification Error: ' . $e->getMessage());
    setError('Verification failed. Please try again or contact support.');
    redirect(SITE_URL . '/auth/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0"><i class="bi bi-envelope-check"></i> Email Verification</h4>
                    </div>
                    <div class="card-body text-center p-5">
                        <div id="verificationProcessing">
                            <div class="mb-4">
                                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="visually-hidden">Verifying...</span>
                                </div>
                            </div>
                            <h5>Verifying your email...</h5>
                            <p class="text-muted">Please wait while we confirm your email address.</p>
                            <div class="progress mt-3" style="height: 3px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%;"></div>
                            </div>
                        </div>

                        <div id="verificationSuccess" style="display: none;">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h5>Email Verified! ✅</h5>
                            <p class="text-muted">Your email has been verified successfully.</p>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary mt-3">
                                <i class="bi bi-box-arrow-in-right"></i> Login Now
                            </a>
                        </div>

                        <div id="verificationError" style="display: none;">
                            <div class="mb-4">
                                <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                            </div>
                            <h5>Verification Failed ❌</h5>
                            <p class="text-muted" id="errorMessage">The verification link is invalid or expired.</p>
                            <a href="<?php echo SITE_URL; ?>/auth/forgot-password.php" class="btn btn-warning mt-2">
                                <i class="bi bi-envelope"></i> Resend Verification
                            </a>
                            <br>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-outline-secondary mt-2">
                                <i class="bi bi-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo SITE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const processing = document.getElementById('verificationProcessing');
        const success = document.getElementById('verificationSuccess');
        const error = document.getElementById('verificationError');
        
        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('code');
        const email = urlParams.get('email');
        
        if (!code || !email) {
            processing.style.display = 'none';
            error.style.display = 'block';
            document.getElementById('errorMessage').textContent = 'Invalid verification link. Please check your email.';
            return;
        }
    });
    </script>
</body>
</html>