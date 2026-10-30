<?php
// marketplace/api/messages/send.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Require login
if (!Session::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId = Session::getUserId();
$receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

if (!$receiverId || empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

if ($receiverId == $userId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You cannot message yourself']);
    exit();
}

try {
    $db->insert(
        "INSERT INTO messages (sender_id, receiver_id, listing_id, order_id, message_text) 
         VALUES (?, ?, ?, ?, ?)",
        [$userId, $receiverId, $listingId ?: null, $orderId ?: null, $message]
    );

    // Add notification
    if ($orderId > 0) {
        $order = $db->fetchOne("SELECT order_number FROM orders WHERE id = ?", [$orderId]);
        $orderNumber = $order['order_number'] ?? '';
        $notificationTitle = 'New Message about Order #' . $orderNumber;
    } elseif ($listingId > 0) {
        $listing = $db->fetchOne("SELECT title FROM listings WHERE id = ?", [$listingId]);
        $listingTitle = $listing['title'] ?? '';
        $notificationTitle = 'New Message about "' . $listingTitle . '"';
    } else {
        $notificationTitle = 'New Message';
    }

    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'message', ?, ?, '/messages.php')",
        [
            $receiverId,
            $notificationTitle,
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '')
        ]
    );

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}