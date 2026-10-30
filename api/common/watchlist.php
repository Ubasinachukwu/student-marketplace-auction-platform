<?php
// marketplace/api/common/watchlist.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Require login
if (!Session::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$db = db();
$userId = Session::getUserId();
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if (!$listingId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID']);
    exit();
}

// Check if already in watchlist
$existing = $db->fetchOne(
    "SELECT id FROM watchlist WHERE user_id = ? AND listing_id = ?",
    [$userId, $listingId]
);

if ($existing) {
    // Remove from watchlist
    $db->delete(
        "DELETE FROM watchlist WHERE user_id = ? AND listing_id = ?",
        [$userId, $listingId]
    );
    
    // Decrement watch count
    $db->update(
        "UPDATE listings SET watch_count = watch_count - 1 WHERE id = ?",
        [$listingId]
    );
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add to watchlist
    $db->insert(
        "INSERT INTO watchlist (user_id, listing_id) VALUES (?, ?)",
        [$userId, $listingId]
    );
    
    // Increment watch count
    $db->update(
        "UPDATE listings SET watch_count = watch_count + 1 WHERE id = ?",
        [$listingId]
    );
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'action' => 'added']);
}