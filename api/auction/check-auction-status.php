<?php
// marketplace/api/auction/check-auction-status.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auction.php';

header('Content-Type: application/json');

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

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

    // Check if auction needs processing
    if ($auction->isEnded() && $auction->isActive()) {
        // Process ended auction
        $result = $auction->processEndedAuction();
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'status' => 'ended',
                'message' => $result['message'],
                'winner' => $result['winner'] ?? null,
                'winning_bid' => $result['winning_bid'] ?? null,
                'order_id' => $result['order_id'] ?? null
            ]);
            exit();
        }
    }

    // Return current status
    echo json_encode([
        'success' => true,
        'listing_id' => $listingId,
        'is_active' => $auction->isActive(),
        'is_ended' => $auction->isEnded(),
        'is_reserve_met' => $auction->isReserveMet(),
        'current_bid' => $auction->getListing()->getCurrentBid(),
        'time_remaining' => $auction->getTimeRemaining(),
        'total_bids' => $auction->getTotalBids(),
        'winner' => $auction->getWinner() ? $auction->getWinner()->toArray() : null
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}