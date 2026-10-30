<?php
// marketplace/api/auction/get-current-bid.php

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

    $response = [
        'success' => true,
        'listing_id' => $listingId,
        'current_bid' => $auction->getListing()->getCurrentBid(),
        'starting_bid' => $auction->getListing()->getStartingBid(),
        'bid_increment' => $auction->getListing()->getBidIncrement(),
        'reserve_price' => $auction->getListing()->getReservePrice(),
        'is_reserve_met' => $auction->isReserveMet(),
        'total_bids' => $auction->getTotalBids(),
        'time_remaining' => $auction->getTimeRemaining(),
        'is_active' => $auction->isActive(),
        'is_ended' => $auction->isEnded(),
        'min_bid' => ($auction->getListing()->getCurrentBid() ?? $auction->getListing()->getStartingBid()) + $auction->getListing()->getBidIncrement()
    ];

    // If user is logged in, check if they are winning
    if (Session::isLoggedIn()) {
        $response['is_winning'] = $auction->isWinning(Session::getUserId());
        $userBid = $auction->getUserBid(Session::getUserId());
        $response['your_bid'] = $userBid ? $userBid['bid_amount'] : null;
        $response['your_max_bid'] = $userBid ? $userBid['max_proxy_amount'] : null;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}