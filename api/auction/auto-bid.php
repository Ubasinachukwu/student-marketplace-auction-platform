<?php
// marketplace/api/auction/auto-bid.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auction.php';

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
$maxBid = isset($_POST['max_bid']) ? (float)$_POST['max_bid'] : 0;

if (!$listingId || $maxBid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Listing ID and max bid are required']);
    exit();
}

try {
    $auction = new Auction($listingId);
    
    if (!$auction->getListingId()) {
        echo json_encode(['success' => false, 'message' => 'Auction not found']);
        exit();
    }

    $userId = Session::getUserId();
    $currentBid = $auction->getListing()->getCurrentBid() ?? $auction->getListing()->getStartingBid();
    $bidIncrement = $auction->getListing()->getBidIncrement();
    
    // Calculate minimum bid
    $minBid = $currentBid + $bidIncrement;
    
    if ($maxBid < $minBid) {
        echo json_encode([
            'success' => false, 
            'message' => 'Max bid must be at least ' . number_format($minBid, 2)
        ]);
        exit();
    }

    // Place proxy bid
    $result = $auction->placeBid($userId, $minBid, true, $maxBid);
    
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}