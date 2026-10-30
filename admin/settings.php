<?php
// marketplace/admin/settings.php

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
    "SELECT setting_key, setting_value, setting_group, is_public FROM settings ORDER BY setting_group, setting_key"
);

$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/admin/settings.php');
    }

    $section = isset($_POST['section']) ? sanitize($_POST['section']) : 'general';
    
    $siteSettings = [];
    
    // General Settings
    if ($section === 'general') {
        $siteSettings = [
            'site_name' => sanitize($_POST['site_name'] ?? 'MarketPlace'),
            'site_description' => sanitize($_POST['site_description'] ?? ''),
            'site_email' => sanitize($_POST['site_email'] ?? ''),
            'site_phone' => sanitize($_POST['site_phone'] ?? ''),
            'site_address' => sanitize($_POST['site_address'] ?? ''),
            'site_currency' => sanitize($_POST['site_currency'] ?? 'NGN'),
            'site_language' => sanitize($_POST['site_language'] ?? 'en'),
            'site_timezone' => sanitize($_POST['site_timezone'] ?? 'Africa/Lagos'),
        ];
    }
    // Email Settings
    elseif ($section === 'email') {
        $siteSettings = [
            'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
            'smtp_port' => sanitize($_POST['smtp_port'] ?? 587),
            'smtp_username' => sanitize($_POST['smtp_username'] ?? ''),
            'smtp_password' => sanitize($_POST['smtp_password'] ?? ''),
            'smtp_encryption' => sanitize($_POST['smtp_encryption'] ?? 'tls'),
            'email_from_name' => sanitize($_POST['email_from_name'] ?? ''),
            'email_from_address' => sanitize($_POST['email_from_address'] ?? ''),
        ];
    }
    // Security Settings
    elseif ($section === 'security') {
        $siteSettings = [
            'session_timeout' => (int)($_POST['session_timeout'] ?? 1800),
            'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
            'lockout_time' => (int)($_POST['lockout_time'] ?? 15),
            'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0,
            'enable_2fa' => isset($_POST['enable_2fa']) ? 1 : 0,
            'force_ssl' => isset($_POST['force_ssl']) ? 1 : 0,
        ];
    }
    // SEO Settings
    elseif ($section === 'seo') {
        $siteSettings = [
            'meta_title' => sanitize($_POST['meta_title'] ?? ''),
            'meta_description' => sanitize($_POST['meta_description'] ?? ''),
            'meta_keywords' => sanitize($_POST['meta_keywords'] ?? ''),
            'og_image' => sanitize($_POST['og_image'] ?? ''),
            'twitter_handle' => sanitize($_POST['twitter_handle'] ?? ''),
        ];
    }

    // Save settings
    try {
        $db->beginTransaction();

        foreach ($siteSettings as $key => $value) {
            $db->update(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
            
            // If no rows updated, insert
            if ($db->rowCount() == 0) {
                $db->insert(
                    "INSERT INTO settings (setting_key, setting_value, setting_group) 
                     VALUES (?, ?, ?)",
                    [$key, $value, $section]
                );
            }
        }

        // Log admin action
        $logMessage = 'Settings updated for section: ' . $section;
        $db->insert(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
             VALUES (?, 'update_settings', 'settings', 0, ?)",
            [Session::getUserId(), $logMessage]
        );

        $db->commit();
        setSuccess('Settings updated successfully');
        redirect(SITE_URL . '/admin/settings.php?section=' . $section);

    } catch (Exception $e) {
        $db->rollback();
        setError('Failed to update settings: ' . $e->getMessage());
    }
}

// Refresh settings after update
$settings = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings ORDER BY setting_group, setting_key"
);
$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting['setting_value'];
}

$currentSection = isset($_GET['section']) ? sanitize($_GET['section']) : 'general';
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
                <h1 class="h2"><i class="bi bi-gear"></i> Site Settings</h1>
            </div>

            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentSection == 'general' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/settings.php?section=general">
                        <i class="bi bi-globe"></i> General
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentSection == 'email' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/settings.php?section=email">
                        <i class="bi bi-envelope"></i> Email
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentSection == 'security' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/settings.php?section=security">
                        <i class="bi bi-shield-lock"></i> Security
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentSection == 'seo' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/settings.php?section=seo">
                        <i class="bi bi-search"></i> SEO
                    </a>
                </li>
            </ul>

            <!-- Settings Forms -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?php echo SITE_URL; ?>/admin/settings.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="section" value="<?php echo $currentSection; ?>">

                        <?php if ($currentSection == 'general'): ?>
                            <!-- General Settings -->
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-globe"></i> General Settings</h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" 
                                               value="<?php echo $settingsMap['site_name'] ?? SITE_NAME; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="site_email" class="form-label">Site Email</label>
                                        <input type="email" class="form-control" id="site_email" name="site_email" 
                                               value="<?php echo $settingsMap['site_email'] ?? ''; ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="site_description" class="form-label">Site Description</label>
                                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo $settingsMap['site_description'] ?? ''; ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="site_phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="site_phone" name="site_phone" 
                                               value="<?php echo $settingsMap['site_phone'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="site_currency" class="form-label">Currency</label>
                                        <select class="form-select" id="site_currency" name="site_currency">
                                            <option value="NGN" <?php echo ($settingsMap['site_currency'] ?? 'NGN') == 'NGN' ? 'selected' : ''; ?>>NGN (₦) - Nigerian Naira</option>
                                            <option value="USD" <?php echo ($settingsMap['site_currency'] ?? 'NGN') == 'USD' ? 'selected' : ''; ?>>USD ($) - US Dollar</option>
                                            <option value="EUR" <?php echo ($settingsMap['site_currency'] ?? 'NGN') == 'EUR' ? 'selected' : ''; ?>>EUR (€) - Euro</option>
                                            <option value="GBP" <?php echo ($settingsMap['site_currency'] ?? 'NGN') == 'GBP' ? 'selected' : ''; ?>>GBP (£) - British Pound</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="site_language" class="form-label">Language</label>
                                        <select class="form-select" id="site_language" name="site_language">
                                            <option value="en" <?php echo ($settingsMap['site_language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="fr" <?php echo ($settingsMap['site_language'] ?? 'en') == 'fr' ? 'selected' : ''; ?>>French</option>
                                            <option value="ha" <?php echo ($settingsMap['site_language'] ?? 'en') == 'ha' ? 'selected' : ''; ?>>Hausa</option>
                                            <option value="ig" <?php echo ($settingsMap['site_language'] ?? 'en') == 'ig' ? 'selected' : ''; ?>>Igbo</option>
                                            <option value="yo" <?php echo ($settingsMap['site_language'] ?? 'en') == 'yo' ? 'selected' : ''; ?>>Yoruba</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="site_timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="site_timezone" name="site_timezone">
                                            <option value="Africa/Lagos" <?php echo ($settingsMap['site_timezone'] ?? 'Africa/Lagos') == 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (WAT)</option>
                                            <option value="Africa/Abuja" <?php echo ($settingsMap['site_timezone'] ?? '') == 'Africa/Abuja' ? 'selected' : ''; ?>>Africa/Abuja</option>
                                            <option value="Africa/Accra" <?php echo ($settingsMap['site_timezone'] ?? '') == 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra</option>
                                            <option value="UTC" <?php echo ($settingsMap['site_timezone'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="site_address" class="form-label">Address</label>
                                        <textarea class="form-control" id="site_address" name="site_address" rows="2"><?php echo $settingsMap['site_address'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($currentSection == 'email'): ?>
                            <!-- Email Settings -->
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-envelope"></i> Email Configuration</h5>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Configure SMTP settings for sending emails.
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="<?php echo $settingsMap['smtp_host'] ?? ''; ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                               value="<?php echo $settingsMap['smtp_port'] ?? 587; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                               value="<?php echo $settingsMap['smtp_username'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               value="<?php echo $settingsMap['smtp_password'] ?? ''; ?>">
                                        <small class="text-muted">Leave blank to keep current password</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="smtp_encryption" class="form-label">Encryption</label>
                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                            <option value="none" <?php echo ($settingsMap['smtp_encryption'] ?? '') == 'none' ? 'selected' : ''; ?>>None</option>
                                            <option value="tls" <?php echo ($settingsMap['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo ($settingsMap['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email_from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control" id="email_from_name" name="email_from_name" 
                                               value="<?php echo $settingsMap['email_from_name'] ?? SITE_NAME; ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="email_from_address" class="form-label">From Address</label>
                                        <input type="email" class="form-control" id="email_from_address" name="email_from_address" 
                                               value="<?php echo $settingsMap['email_from_address'] ?? ''; ?>"
                                               placeholder="no-reply@yourdomain.com">
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-primary" id="testEmailBtn">
                                        <i class="bi bi-envelope-paper"></i> Send Test Email
                                    </button>
                                </div>
                            </div>

                        <?php elseif ($currentSection == 'security'): ?>
                            <!-- Security Settings -->
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-shield-lock"></i> Security Settings</h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                               value="<?php echo $settingsMap['session_timeout'] ?? 1800; ?>">
                                        <small class="text-muted">Default: 1800 (30 minutes)</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                               value="<?php echo $settingsMap['max_login_attempts'] ?? 5; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="lockout_time" class="form-label">Lockout Time (minutes)</label>
                                        <input type="number" class="form-control" id="lockout_time" name="lockout_time" 
                                               value="<?php echo $settingsMap['lockout_time'] ?? 15; ?>">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="require_email_verification" 
                                                   name="require_email_verification" 
                                                   <?php echo ($settingsMap['require_email_verification'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_email_verification">
                                                Require Email Verification
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_2fa" 
                                                   name="enable_2fa" 
                                                   <?php echo ($settingsMap['enable_2fa'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_2fa">
                                                Enable Two-Factor Authentication
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="force_ssl" 
                                                   name="force_ssl" 
                                                   <?php echo ($settingsMap['force_ssl'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="force_ssl">
                                                Force SSL (HTTPS)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($currentSection == 'seo'): ?>
                            <!-- SEO Settings -->
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-search"></i> SEO Settings</h5>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="meta_title" class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" id="meta_title" name="meta_title" 
                                               value="<?php echo $settingsMap['meta_title'] ?? SITE_NAME; ?>">
                                        <small class="text-muted">Recommended: 50-60 characters</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="meta_description" class="form-label">Meta Description</label>
                                        <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo $settingsMap['meta_description'] ?? ''; ?></textarea>
                                        <small class="text-muted">Recommended: 150-160 characters</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" 
                                               value="<?php echo $settingsMap['meta_keywords'] ?? ''; ?>"
                                               placeholder="marketplace, buy, sell, used items">
                                        <small class="text-muted">Comma separated keywords</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="og_image" class="form-label">OG Image URL</label>
                                        <input type="text" class="form-control" id="og_image" name="og_image" 
                                               value="<?php echo $settingsMap['og_image'] ?? ''; ?>"
                                               placeholder="https://yourdomain.com/og-image.jpg">
                                        <small class="text-muted">Image for social sharing</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="twitter_handle" class="form-label">Twitter Handle</label>
                                        <input type="text" class="form-control" id="twitter_handle" name="twitter_handle" 
                                               value="<?php echo $settingsMap['twitter_handle'] ?? ''; ?>"
                                               placeholder="@yourhandle">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-envelope-paper"></i> Send Test Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="email" 
                               value="<?php echo Session::get('email'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_message" class="form-label">Test Message</label>
                        <textarea class="form-control" id="test_message" name="message" rows="3">This is a test email from <?php echo SITE_NAME; ?></textarea>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendTestEmail">
                    <i class="bi bi-send"></i> Send
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test email button
    const testEmailBtn = document.getElementById('testEmailBtn');
    if (testEmailBtn) {
        testEmailBtn.addEventListener('click', function() {
            new bootstrap.Modal(document.getElementById('testEmailModal')).show();
        });
    }

    const sendTestEmailBtn = document.getElementById('sendTestEmail');
    if (sendTestEmailBtn) {
        sendTestEmailBtn.addEventListener('click', function() {
            const form = document.getElementById('testEmailForm');
            const formData = new FormData(form);
            
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
            
            fetch('<?php echo SITE_URL; ?>/api/admin/test-email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Test email sent successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('testEmailModal')).hide();
                } else {
                    alert('Failed to send test email: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-send"></i> Send';
            });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>