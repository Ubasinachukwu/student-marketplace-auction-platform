<?php
// marketplace/api/user/update-store.php
// Included by buyer/settings.php — DO NOT access directly

if (!defined('SITE_URL')) {
    http_response_code(403);
    exit('Forbidden');
}

$db = db();
$userId = Session::getUserId();

// Must be an approved seller
$user = $db->fetchOne("SELECT is_seller, seller_status, full_name FROM users WHERE id = ?", [$userId]);

if (!$user || (int)$user['is_seller'] !== 1 || $user['seller_status'] !== 'active') {
    setError('Only approved sellers can edit store settings.');
    redirect(SITE_URL . '/buyer/dashboard.php');
}

$storeName        = trim((string)($_POST['store_name'] ?? ''));
$storeDescription = trim((string)($_POST['store_description'] ?? ''));
$payoutDetails    = trim((string)($_POST['payout_details'] ?? ''));

// Bank transfer is the only supported payout method.
$payoutMethod = 'bank';

if ($storeName === '') {
    $storeName = ($user['full_name'] ?: 'My') . "'s Store";
}

if (strlen($storeName) > 100) {
    setError('Store name is too long (max 100 chars).');
    redirect(SITE_URL . '/buyer/settings.php?tab=store');
}

// Upsert seller profile
$existing = $db->fetchOne("SELECT user_id FROM seller_profiles WHERE user_id = ?", [$userId]);

if ($existing) {
    $db->update(
        "UPDATE seller_profiles 
         SET store_name = ?, store_description = ?, payout_method = ?, payout_details = ? 
         WHERE user_id = ?",
        [$storeName, $storeDescription ?: null, $payoutMethod, $payoutDetails ?: null, $userId]
    );
} else {
    $db->insert(
        "INSERT INTO seller_profiles (user_id, store_name, store_description, payout_method, payout_details) 
         VALUES (?, ?, ?, ?, ?)",
        [$userId, $storeName, $storeDescription ?: null, $payoutMethod, $payoutDetails ?: null]
    );
}

setSuccess('Store settings saved.');
redirect(SITE_URL . '/buyer/settings.php?tab=store');