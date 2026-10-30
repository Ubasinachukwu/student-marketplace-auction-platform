<?php
// marketplace/api/auction/live-bid.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$db = db();
$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit;
}

$listing = $db->fetchOne(
    "SELECT id, sale_type, starting_bid, current_bid, bid_increment, reserve_price,
            auction_end, status, buy_it_now_price
     FROM listings WHERE id = ?",
    [$listingId]
);

if (!$listing) {
    echo json_encode(['success' => false, 'message' => 'Listing not found']);
    exit;
}

// Bid history
$bids = $db->fetchAll(
    "SELECT b.bid_amount, b.created_at, b.is_proxy_bid, u.full_name as bidder_name
     FROM bids b
     JOIN users u ON b.buyer_id = u.id
     WHERE b.listing_id = ? AND b.is_retracted = 0
     ORDER BY b.bid_amount DESC, b.created_at DESC
     LIMIT 20",
    [$listingId]
);

// Time remaining
$now = time();
$endTs = !empty($listing['auction_end']) ? strtotime($listing['auction_end']) : 0;
$remaining = max(0, $endTs - $now);
$isEnding = $remaining > 0 && $remaining <= 3600; // < 1 hour
$isEnded  = $endTs > 0 && $endTs <= $now;

// Format time remaining
if ($remaining > 0) {
    $d = floor($remaining / 86400);
    $h = floor(($remaining % 86400) / 3600);
    $m = floor(($remaining % 3600) / 60);
    $s = $remaining % 60;
    $timeStr = ($d > 0 ? "{$d}d " : '') . sprintf('%02dh %02dm %02ds', $h, $m, $s);
} else {
    $timeStr = 'Ended';
}

// Latest bids formatted
$latestBids = [];
foreach ($bids as $b) {
    $latestBids[] = [
        'name'   => $b['bidder_name'],
        'amount' => formatNaira($b['bid_amount']),
        'raw'    => (float)$b['bid_amount'],
        'time'   => timeAgo($b['created_at']),
        'proxy'  => (bool)$b['is_proxy_bid'],
    ];
}

$currentBid = (float)($listing['current_bid'] ?: $listing['starting_bid']);
$highestBidder = $bids[0]['bidder_name'] ?? 'No bids yet';

echo json_encode([
    'success'         => true,
    'current_bid'     => formatNaira($currentBid),
    'current_bid_raw' => $currentBid,
    'total_bids'      => count($bids),
    'highest_bidder'  => $highestBidder,
    'time_remaining'  => $timeStr,
    'is_ending'       => $isEnding,
    'is_ended'        => $isEnded,
    'reserve_met'     => $listing['reserve_price'] && $currentBid >= $listing['reserve_price'],
    'latest_bids'     => $latestBids,
    'server_time'     => date('c'),
]);