<?php
// marketplace/api/cart/remove.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId    = Session::getUserId();
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit();
}

try {
    $cart = $db->fetchOne("SELECT id FROM carts WHERE user_id = ?", [$userId]);

    if (!$cart) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit();
    }

    $db->delete(
        "DELETE FROM cart_items WHERE cart_id = ? AND listing_id = ?",
        [$cart['id'], $listingId]
    );

    $count = $db->fetchOne(
        "SELECT COUNT(*) as c FROM cart_items WHERE cart_id = ?",
        [$cart['id']]
    )['c'] ?? 0;

    // Auto-delete empty cart
    if ((int)$count === 0) {
        $db->delete("DELETE FROM carts WHERE id = ?", [$cart['id']]);
    }

    echo json_encode(['success' => true, 'count' => (int)$count]);

} catch (Exception $e) {
    error_log('Cart remove error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
}