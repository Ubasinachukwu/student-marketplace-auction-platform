<?php
// marketplace/auth/login.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Session is already started by Session::init() inside session.php.
// Do NOT call session_start() again.

// =============================================
// If already logged in, redirect to correct dashboard.
// IMPORTANT: only redirect when the session is COMPLETE
// (user_id AND user_type both present). A half-broken
// session with user_id but no user_type would otherwise
// cause an infinite loop with the dashboard pages.
// =============================================
if (Session::isLoggedIn() && Session::getUserType()) {
    $userType = Session::getUserType();
    if ($userType === 'admin') {
        redirect(SITE_URL . '/admin/dashboard.php');
    } elseif ($userType === 'seller') {
        redirect(SITE_URL . '/seller/dashboard.php');
    } else {
        redirect(SITE_URL . '/buyer/dashboard.php');
    }
    exit();
}

// If session is half-broken, clear it so the login form shows.
if (Session::isLoggedIn() && !Session::getUserType()) {
    Session::destroy();
}

$db = db();
$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log('CSRF Token Mismatch in login.php');
        error_log('Session Token: ' . ($_SESSION['csrf_token'] ?? 'NOT SET'));
        error_log('Post Token: ' . ($_POST['csrf_token'] ?? 'NOT SET'));

        setError('Invalid security token. Please try again.');
        Session::regenerateCSRFToken();
        redirect(SITE_URL . '/auth/login.php');
    }

    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($email)) {
        $error = 'Email is required';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } else {
        // Check if user exists
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            $error = 'Invalid email or password';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been deactivated. Please contact support.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password';
        } else {
            // Login successful — set session data
            Session::set('user_id',       $user['id']);
            Session::set('user_type',     $user['user_type']);
            Session::set('full_name',     $user['full_name']);
            Session::set('email',         $user['email']);
            Session::set('email_verified',(bool)$user['email_verified']);
            Session::set('is_seller',     (int)($user['is_seller'] ?? 0));
            Session::set('seller_status', $user['seller_status'] ?? 'none');

            Session::regenerateId();
            Session::regenerateCSRFToken();

            // Update last login
            $db->update(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );

            // Remember me (30 days)
            if ($remember) {
                $token   = generateToken(64);
                $expires = time() + (86400 * 30);
                setcookie(
                    'remember_token',
                    $token,
                    $expires,
                    '/',
                    '',
                    isset($_SERVER['HTTPS']),
                    true
                );
            }

            setSuccess('Welcome back, ' . $user['full_name'] . '!');

            // Redirect based on user type
            if ($user['user_type'] === 'admin') {
                redirect(SITE_URL . '/admin/dashboard.php');
            } elseif ($user['user_type'] === 'seller') {
                redirect(SITE_URL . '/seller/dashboard.php');
            } else {
                $redirect = Session::get('redirect_after_login');
                if ($redirect) {
                    Session::remove('redirect_after_login');
                    redirect($redirect);
                } else {
                    redirect(SITE_URL . '/buyer/dashboard.php');
                }
            }
        }
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
                    <h4 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Login</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php echo displayFlashMessages(); ?>

                    <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Registration successful! Please verify your email and login.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['verified']) && $_GET['verified'] == 1): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Email verified successfully! You can now login.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['expired']) && $_GET['expired'] == 1): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-clock"></i> Your session has expired. Please login again.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       value="<?php echo htmlspecialchars($email); ?>"
                                       required
                                       autofocus
                                       placeholder="you@example.com">
                            </div>
                        </div>

                        <!-- Password with Eye Toggle -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       required
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/auth/forgot-password.php" class="text-decoration-none">Forgot password?</a>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>

                        <div class="text-center mt-3">
                            <p class="text-muted">
                                Don't have an account? <a href="<?php echo SITE_URL; ?>/auth/register.php">Register here</a>
                            </p>
                        </div>
                    </form>

                    <hr class="my-4">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Login page JS: password toggle + password visibility helper -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn     = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon       = document.getElementById('eyeIcon');

    function togglePasswordVisibility() {
        if (!passwordInput || !eyeIcon) return;
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'bi bi-eye-slash';
            passwordInput.setAttribute('aria-label', 'Hide password');
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'bi bi-eye';
            passwordInput.setAttribute('aria-label', 'Show password');
        }
        passwordInput.focus();
    }

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            togglePasswordVisibility();
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>