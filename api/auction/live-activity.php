<?php
// marketplace/api/auction/live-activity.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$db = db();

// Latest bids across all auctions (last 30 minutes)
$recentBids = $db->fetchAll(
    "SELECT b.bid_amount, b.created_at, l.title, l.slug, l.id as listing_id,
            u.full_name as bidder_name
     FROM bids b
     JOIN listings l ON b.listing_id = l.id
     JOIN users u ON b.buyer_id = u.id
     WHERE b.is_retracted = 0
       AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
       AND l.status = 'active'
     ORDER BY b.created_at DESC
     LIMIT 10"
);

$items = [];
foreach ($recentBids as $b) {
    $items[] = [
        'bidder'  => $b['bidder_name'],
        'amount'  => formatNaira($b['bid_amount']),
        'title'   => $b['title'],
        'url'     => SITE_URL . '/buyer/listing-detail.php?id=' . $b['listing_id'],
        'time'    => timeAgo($b['created_at']),
    ];
}

// Auctions ending soon (< 2 hours)
$endingSoon = $db->fetchAll(
    "SELECT l.id, l.title, l.current_bid, l.starting_bid, l.auction_end,
            (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count
     FROM listings l
     WHERE l.sale_type IN ('auction','hybrid')
       AND l.status = 'active'
       AND l.auction_end > NOW()
       AND l.auction_end <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
     ORDER BY l.auction_end ASC
     LIMIT 5"
);

$endingItems = [];
foreach ($endingSoon as $l) {
    $endingItems[] = [
        'title'     => $l['title'],
        'price'     => formatNaira($l['current_bid'] ?: $l['starting_bid']),
        'bids'      => (int)$l['bid_count'],
        'remaining' => timeRemaining($l['auction_end']),
        'url'       => SITE_URL . '/buyer/listing-detail.php?id=' . $l['id'],
    ];
}

echo json_encode([
    'success'      => true,
    'recent_bids'  => $items,
    'ending_soon'  => $endingItems,
]);