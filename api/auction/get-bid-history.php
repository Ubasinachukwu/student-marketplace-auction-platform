<?php
// marketplace/api/auction/get-bid-history.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auction.php';

header('Content-Type: application/json');

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Listing ID is required']);
    exit();
}

try {
    $auction = new Auction($listingId);
    
    if (!$auction->getListingId()) {
        echo json_encode(['success' => false, 'message' => 'Auction not found']);
        exit();
    }

    $history = $auction->getBidHistory($limit);
    
    // Anonymize bidder names for non-winning bidders
    $anonymizedHistory = [];
    foreach ($history as $index => $bid) {
        $anonymizedHistory[] = [
            'bidder' => $index === 0 ? $bid['bidder_name'] : 'Bidder #' . ($index + 1),
            'bidder_id' => $index === 0 ? $bid['buyer_id'] : null,
            'amount' => $bid['bid_amount'],
            'is_proxy' => (bool)$bid['is_proxy_bid'],
            'is_winning' => (bool)$bid['is_winning'],
            'time' => $bid['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'listing_id' => $listingId,
        'total_bids' => $auction->getTotalBids(),
        'history' => $anonymizedHistory
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}