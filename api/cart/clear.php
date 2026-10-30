<?php
// marketplace/api/cart/clear.php

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
$userId = Session::getUserId();

try {
    $cart = $db->fetchOne("SELECT id FROM carts WHERE user_id = ?", [$userId]);
    if ($cart) {
        $db->delete("DELETE FROM carts WHERE id = ?", [$cart['id']]);
    }
    echo json_encode(['success' => true, 'count' => 0]);
} catch (Exception $e) {
    error_log('Cart clear error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
}