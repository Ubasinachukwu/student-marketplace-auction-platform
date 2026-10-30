<?php
// marketplace/api/listing/report.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to report a listing']);
    exit();
}

// CSRF
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db     = db();
$userId = Session::getUserId();

$listingId   = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$reason      = isset($_POST['reason'])     ? trim((string)$_POST['reason'])     : '';
$description = isset($_POST['description'])? trim((string)$_POST['description']): '';

// Allowed reasons — must match the modal dropdown
$allowedReasons = [
    'scam_or_fraud',
    'prohibited_item',
    'wrong_category',
    'misleading_description',
    'duplicate_listing',
    'offensive_content',
    'other',
];

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit();
}
if (!in_array($reason, $allowedReasons, true)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid reason']);
    exit();
}
if ($reason === 'other' && mb_strlen($description) < 10) {
    echo json_encode(['success' => false, 'message' => 'Please describe the issue (at least 10 characters)']);
    exit();
}
if (mb_strlen($description) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Description is too long (max 1000 characters)']);
    exit();
}

// Verify listing exists and is not the user's own
$listing = $db->fetchOne(
    "SELECT l.id, l.title, l.seller_id, l.status,
            u.full_name AS seller_name
     FROM listings l
     JOIN users u ON l.seller_id = u.id
     WHERE l.id = ?",
    [$listingId]
);

if (!$listing) {
    echo json_encode(['success' => false, 'message' => 'Listing not found']);
    exit();
}

if ((int)$listing['seller_id'] === (int)$userId) {
    echo json_encode(['success' => false, 'message' => 'You cannot report your own listing']);
    exit();
}

// Prevent duplicate open reports from the same user on the same listing
$existing = $db->fetchOne(
    "SELECT id FROM reports 
     WHERE listing_id = ? AND reported_by = ? AND status = 'pending'
     LIMIT 1",
    [$listingId, $userId]
);

if ($existing) {
    echo json_encode([
        'success' => false,
        'message' => 'You have already reported this listing. Our team is reviewing it.',
    ]);
    exit();
}

// Insert the report
try {
    $reportId = $db->insert(
        "INSERT INTO reports (listing_id, reported_by, reason, description, status) 
         VALUES (?, ?, ?, ?, 'pending')",
        [$listingId, $userId, $reason, $description ?: null]
    );
} catch (Throwable $e) {
    error_log('Listing report insert error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit report']);
    exit();
}

// ---- Post-insert: notify admins (in-app + email) ----
try {
    $reasonLabels = [
        'scam_or_fraud'          => 'Scam or fraudulent listing',
        'prohibited_item'        => 'Prohibited or illegal item',
        'wrong_category'         => 'Wrong category',
        'misleading_description' => 'Misleading description or photos',
        'duplicate_listing'      => 'Duplicate listing',
        'offensive_content'      => 'Offensive content',
        'other'                  => 'Other',
    ];
    $reasonLabel = $reasonLabels[$reason] ?? ucfirst(str_replace('_', ' ', $reason));

    $reporter = $db->fetchOne("SELECT full_name, email FROM users WHERE id = ?", [$userId]);
    $reporterName = $reporter['full_name'] ?? 'A user';

    $admins = $db->fetchAll("SELECT id, full_name, email FROM users WHERE user_type = 'admin'");

    foreach ($admins as $admin) {
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'listing_report', ?, ?, ?)",
            [
                $admin['id'],
                'New Listing Report',
                "{$reporterName} reported listing #{$listingId} (\"{$listing['title']}\"): {$reasonLabel}",
                '/admin/listing-reports.php',
            ]
        );
    }

    if (!empty($admins)) {
        $adminBody =
            "A listing has been reported.\n\n" .
            "Listing: #{$listingId} — {$listing['title']}\n" .
            "Seller: {$listing['seller_name']}\n" .
            "Reported by: {$reporterName}\n" .
            "Reason: {$reasonLabel}\n\n" .
            "Description:\n" . ($description !== '' ? $description : '(none)') . "\n\n" .
            "Review it: " . SITE_URL . "/admin/listing-reports.php\n\n" .
            SITE_NAME . " Team";

        foreach ($admins as $admin) {
            if (empty($admin['email'])) continue;
            sendEmail(
                $admin['email'],
                'New listing report — #' . $listingId,
                nl2br($adminBody),
                $admin['full_name']
            );
        }
    }
} catch (Throwable $e) {
    // Don't fail the request if notifications fail — report is already saved
    error_log('Listing report notification error: ' . $e->getMessage());
}

echo json_encode([
    'success'   => true,
    'message'   => 'Thank you. Our team will review this report.',
    'report_id' => (int)$reportId,
]);