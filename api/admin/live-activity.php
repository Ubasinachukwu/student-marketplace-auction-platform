<?php
// marketplace/api/admin/live-activity.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

Session::requireRole('admin');
$db = db();

$feed = [];

// =============================================
// 1. RECENT BIDS (last 5 min)
// =============================================
try {
    $bids = $db->fetchAll(
        "SELECT b.bid_amount, b.created_at, l.title, u.full_name as bidder_name
         FROM bids b
         JOIN listings l ON b.listing_id = l.id
         JOIN users u ON b.buyer_id = u.id
         WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
           AND b.is_retracted = 0
         ORDER BY b.created_at DESC LIMIT 5"
    );
    foreach ($bids as $b) {
        $feed[] = [
            'type' => 'bid',
            'icon' => 'gavel',
            'text' => "New bid: <strong>" . formatNaira($b['bid_amount']) . "</strong> on <em>" . htmlspecialchars($b['title']) . "</em> by " . htmlspecialchars($b['bidder_name']),
            'time' => timeAgo($b['created_at']),
            'ts'   => strtotime($b['created_at']),
        ];
    }
} catch (Exception $e) {}

// =============================================
// 2. RECENT ORDERS
// =============================================
try {
    $orders = $db->fetchAll(
        "SELECT order_number, total_amount, created_at, buyer_id
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY created_at DESC LIMIT 5"
    );
    foreach ($orders as $o) {
        $feed[] = [
            'type' => 'order',
            'icon' => 'bag-check',
            'text' => "New order <strong>#{$o['order_number']}</strong> placed (" . formatNaira($o['total_amount']) . ")",
            'time' => timeAgo($o['created_at']),
            'ts'   => strtotime($o['created_at']),
        ];
    }
} catch (Exception $e) {}

// =============================================
// 3. NEW USERS
// =============================================
try {
    $users = $db->fetchAll(
        "SELECT full_name, email, created_at
         FROM users
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY created_at DESC LIMIT 5"
    );
    foreach ($users as $u) {
        $feed[] = [
            'type' => 'user',
            'icon' => 'person-plus',
            'text' => "New user registered: <strong>" . htmlspecialchars($u['full_name']) . "</strong> (" . htmlspecialchars($u['email']) . ")",
            'time' => timeAgo($u['created_at']),
            'ts'   => strtotime($u['created_at']),
        ];
    }
} catch (Exception $e) {}

// =============================================
// 4. NEW LISTINGS
// =============================================
try {
    $listings = $db->fetchAll(
        "SELECT title, created_at
         FROM listings
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY created_at DESC LIMIT 5"
    );
    foreach ($listings as $l) {
        $feed[] = [
            'type' => 'listing',
            'icon' => 'grid',
            'text' => "New listing: <strong>" . htmlspecialchars($l['title']) . "</strong>",
            'time' => timeAgo($l['created_at']),
            'ts'   => strtotime($l['created_at']),
        ];
    }
} catch (Exception $e) {}

// =============================================
// 5. PAYMENTS RECEIVED — using `transactions` table
//    (FIXED: payments → transactions, status 'success' → 'completed')
// =============================================
try {
    $payments = $db->fetchAll(
        "SELECT o.order_number, t.amount, t.created_at
         FROM transactions t
         LEFT JOIN orders o ON t.order_id = o.id
         WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
           AND t.type = 'payment'
           AND t.status = 'completed'
         ORDER BY t.created_at DESC
         LIMIT 5"
    );
    foreach ($payments as $p) {
        $orderRef = $p['order_number'] ?? '—';
        $feed[] = [
            'type' => 'payment',
            'icon' => 'cash-coin',
            'text' => "Payment received: <strong>" . formatNaira($p['amount']) . "</strong> for order #{$orderRef}",
            'time' => timeAgo($p['created_at']),
            'ts'   => strtotime($p['created_at']),
        ];
    }
} catch (Exception $e) {}

// Sort by timestamp desc
usort($feed, fn($a, $b) => $b['ts'] - $a['ts']);
$feed = array_slice($feed, 0, 15);

echo json_encode(['success' => true, 'feed' => $feed]);