<?php
// marketplace/api/orders/update-status.php

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
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['status']) ? sanitize($_POST['status']) : '';

if (!$orderId || empty($status)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Allowed status transitions
$allowedStatuses = ['confirmed', 'shipped', 'delivered', 'completed', 'cancelled'];

if (!in_array($status, $allowedStatuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Get order details
$order = $db->fetchOne(
    "SELECT * FROM orders WHERE id = ? AND seller_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

// Validate status transition
$validTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['shipped', 'cancelled'],
    'shipped' => ['delivered', 'cancelled'],
    'delivered' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => []
];

if (!in_array($status, $validTransitions[$order['order_status']] ?? [])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

try {
    $db->beginTransaction();

    // =============================================
    // Build the update query dynamically
    // =============================================
    $updateFields = "order_status = ?";
    $params = [$status];

    if ($status == 'shipped') {
        $updateFields .= ", shipped_date = NOW()";
    }
    
    if ($status == 'delivered') {
        $updateFields .= ", delivered_date = NOW()";
    }
    
    if ($status == 'cancelled') {
        $updateFields .= ", cancelled_at = NOW()";
    }

    if ($status == 'completed') {
        $updateFields .= ", completed_at = NOW()";
    }

    // Add order_id to params for WHERE clause
    $params[] = $orderId;

    // Update order status
    $db->update(
        "UPDATE orders SET $updateFields WHERE id = ?",
        $params
    );

    // If cancelled, restore listing
    if ($status == 'cancelled') {
        $db->update(
            "UPDATE listings SET status = 'active' WHERE id = ?",
            [$order['listing_id']]
        );
    }

    // =============================================
    // Add notification (FIXED: Build strings in PHP)
    // =============================================
    $notificationTitle = 'Order Status Updated';
    $notificationMessage = 'Your order #' . $order['order_number'] . ' has been ' . ucfirst($status);
    $notificationLink = '/buyer/order-detail.php?id=' . $orderId;

    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
         VALUES (?, 'order', ?, ?, ?, NOW())",
        [
            $order['buyer_id'],
            $notificationTitle,
            $notificationMessage,
            $notificationLink
        ]
    );

    // =============================================
    // Create transaction record for completed orders
    // =============================================
    if ($status == 'completed') {
        $db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status, created_at) 
             VALUES (?, ?, 'payout', ?, 'Order payout', 'completed', NOW())",
            [$orderId, $order['seller_id'], $order['net_amount']]
        );
    }

    $db->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch (Exception $e) {
    $db->rollback();
    error_log('Order Status Update Error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update order: ' . $e->getMessage()]);
}
?>