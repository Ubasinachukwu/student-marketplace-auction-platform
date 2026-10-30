<?php
// marketplace/api/auction/place-bid.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';
require_once __DIR__ . '/../../classes/Auction.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to bid']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$userId = Session::getUserId();

// Read inputs
$listingId      = isset($_POST['listing_id'])      ? (int)$_POST['listing_id']                 : 0;
$bidAmount      = isset($_POST['bid_amount'])      ? (float)$_POST['bid_amount']               : 0;
$isProxy        = (isset($_POST['proxy_bid']) && $_POST['proxy_bid'] === 'on')
               || (isset($_POST['is_proxy_bid']) && (int)$_POST['is_proxy_bid'] === 1);
$maxProxyAmount = isset($_POST['max_proxy_amount']) ? (float)$_POST['max_proxy_amount']       : 0;

// Basic input validation
if (!$listingId || $bidAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid amount']);
    exit();
}

if ($isProxy && $maxProxyAmount < $bidAmount) {
    echo json_encode(['success' => false, 'message' => 'Maximum bid amount must be greater than your bid']);
    exit();
}

try {
    // Instantiate the Auction class — it will validate + process + send emails
    $auction = new Auction($listingId);

    // Additional check: seller cannot bid
    if ($auction->getListing()->getSellerId() == $userId) {
        echo json_encode(['success' => false, 'message' => 'You cannot bid on your own listing']);
        exit();
    }

    // Additional check: auction must be active
    if (!$auction->isActive()) {
        echo json_encode(['success' => false, 'message' => 'Listing not found or not active']);
        exit();
    }

    // Additional check: auction must not be ended
    if ($auction->isEnded()) {
        echo json_encode(['success' => false, 'message' => 'This auction has ended']);
        exit();
    }

    // Delegate the entire bid process to the Auction class
    // This handles: validation, DB transaction, proxy resolution,
    // in-app notifications, outbid emails, and seller bid-placed emails.
    $result = $auction->placeBid(
        $userId,
        $bidAmount,
        $isProxy,
        $isProxy ? $maxProxyAmount : null
    );

    echo json_encode([
        'success'     => !empty($result['success']),
        'message'     => $result['message'] ?? 'Bid placed successfully',
        'bid_amount'  => $bidAmount,
        'is_winning'  => !empty($result['is_winning']),
        'current_bid' => $result['current_bid'] ?? null,
    ]);

} catch (Exception $e) {
    error_log('Place bid error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}