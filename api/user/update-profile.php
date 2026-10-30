<?php
// marketplace/api/user/update-profile.php
// Included by buyer/settings.php — DO NOT access directly

if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

$db = db();
$userId = Session::getUserId();

$fullName = trim((string)($_POST['full_name'] ?? ''));
$phone    = trim((string)($_POST['phone'] ?? ''));
$state    = trim((string)($_POST['state'] ?? ''));
$lga      = trim((string)($_POST['lga'] ?? ''));
$address  = trim((string)($_POST['address'] ?? ''));

if ($fullName === '') {
    setError('Full name is required.');
    redirect(SITE_URL . '/buyer/settings.php?tab=profile');
}

if ($state !== '' && !isValidNigerianState($state)) {
    setError('Please select a valid Nigerian state.');
    redirect(SITE_URL . '/buyer/settings.php?tab=profile');
}

if ($state !== '' && $lga !== '' && !isValidLGA($state, $lga)) {
    setError('Please select a valid LGA for the chosen state.');
    redirect(SITE_URL . '/buyer/settings.php?tab=profile');
}

// Handle picture upload
$profilePicFilename = null;

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir(PROFILE_IMAGES_PATH)) {
        mkdir(PROFILE_IMAGES_PATH, 0777, true);
    }

    $result = uploadFile($_FILES['profile_pic'], PROFILE_IMAGES_PATH, ['jpg','jpeg','png','webp'], 5 * 1024 * 1024);

    if (!$result['success']) {
        setError('Picture upload failed: ' . ($result['error'] ?? 'unknown'));
        redirect(SITE_URL . '/buyer/settings.php?tab=profile');
    }

    $profilePicFilename = $result['filename'];

    // Delete old picture
    $old = $db->fetchOne("SELECT profile_pic FROM users WHERE id = ?", [$userId]);
    if ($old && !empty($old['profile_pic'])) {
        $oldPath = PROFILE_IMAGES_PATH . $old['profile_pic'];
        if (file_exists($oldPath)) @unlink($oldPath);
    }
}

// Build update
if ($profilePicFilename !== null) {
    $db->update(
        "UPDATE users SET full_name = ?, phone = ?, state = ?, lga = ?, address = ?, profile_pic = ? WHERE id = ?",
        [$fullName, $phone ?: null, $state ?: null, $lga ?: null, $address ?: null, $profilePicFilename, $userId]
    );
} else {
    $db->update(
        "UPDATE users SET full_name = ?, phone = ?, state = ?, lga = ?, address = ? WHERE id = ?",
        [$fullName, $phone ?: null, $state ?: null, $lga ?: null, $address ?: null, $userId]
    );
}

// Update session name
Session::set('full_name', $fullName);

setSuccess('Profile updated successfully.');
redirect(SITE_URL . '/buyer/settings.php?tab=profile');