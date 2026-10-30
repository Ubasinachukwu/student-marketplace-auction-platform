<?php
// marketplace/api/listings/get.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Listing.php';

header('Content-Type: application/json');

$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

    // Increment view count
    $listing->incrementViews();

    echo json_encode([
        'success' => true,
        'listing' => $listing->toArray()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}