<?php
// marketplace/api/orders/get.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Order.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

try {
    $order = new Order($orderId);
    
    if (!$order->getId()) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // Check permissions
    $userId = Session::getUserId();
    $userType = Session::getUserType();

    if ($userType !== 'admin' && $order->getBuyerId() !== $userId && $order->getSellerId() !== $userId) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to view this order']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'order' => $order->toArray()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}