<?php
// marketplace/api/listings/delete.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Listing.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Listing ID is required']);
    exit();
}

try {
    $listing = new Listing($listingId);
    
    if (!$listing->getId()) {
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        exit();
    }

    $userId = Session::getUserId();
    $userType = Session::getUserType();

    // Check authorization (seller owns it OR admin)
    if ($userType !== 'admin' && $listing->getSellerId() !== $userId) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this listing']);
        exit();
    }

    // Check if listing has orders (can't delete if sold)
    $db = Database::getInstance();
    $orderCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM orders WHERE listing_id = ?",
        [$listingId]
    );

    if ($orderCount['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete listing with existing orders']);
        exit();
    }

    // Delete listing
    $result = $listing->delete();

    echo json_encode([
        'success' => true,
        'message' => 'Listing deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}