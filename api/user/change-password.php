<?php
// marketplace/api/user/change-password.php
// Included by buyer/settings.php — DO NOT access directly

if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

$db = db();
$userId = Session::getUserId();

$current = (string)($_POST['current_password'] ?? '');
$new     = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    setError('All password fields are required.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}

if ($new !== $confirm) {
    setError('New passwords do not match.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}

if (strlen($new) < 8) {
    setError('Password must be at least 8 characters.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}
if (!preg_match('/[A-Z]/', $new)) {
    setError('Password must contain at least one uppercase letter.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}
if (!preg_match('/[a-z]/', $new)) {
    setError('Password must contain at least one lowercase letter.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}
if (!preg_match('/[0-9]/', $new)) {
    setError('Password must contain at least one number.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}

$user = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
if (!$user || !password_verify($current, $user['password_hash'])) {
    setError('Current password is incorrect.');
    redirect(SITE_URL . '/buyer/settings.php?tab=password');
}

$newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);

$db->update("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId]);

if (function_exists('logActivity')) {
    logActivity($userId, 'password_changed', 'User changed their password');
}

setSuccess('Password changed successfully.');
redirect(SITE_URL . '/buyer/settings.php?tab=password');