<?php
// marketplace/api/orders/update-status.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId   = Session::getUserId();
$userType = Session::getUserType();
$orderId  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status   = isset($_POST['status'])   ? trim((string)$_POST['status']) : '';

if (!$orderId || $status === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// ---------------------------------------------
// Load the order regardless of role
// ---------------------------------------------
$order = $db->fetchOne(
    "SELECT * FROM orders WHERE id = ?",
    [$orderId]
);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

// Determine caller's relationship
$isBuyer  = ((int)$order['buyer_id']  === (int)$userId);
$isSeller = ((int)$order['seller_id'] === (int)$userId);

if (!$isBuyer && !$isSeller) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to update this order']);
    exit();
}

// ---------------------------------------------
// Role-specific allowed statuses
// ---------------------------------------------
if ($isSeller) {
    $allowedStatuses = ['confirmed', 'shipped', 'delivered', 'completed', 'cancelled'];
} else {
    // Buyers can only complete (after delivery) or cancel (only if pending)
    $allowedStatuses = ['completed', 'cancelled'];
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status for your role']);
    exit();
}

// ---------------------------------------------
// Idempotency — if already at that status, treat as success
// ---------------------------------------------
if ($order['order_status'] === $status) {
    echo json_encode([
        'success'      => true,
        'message'      => 'Order is already ' . $status,
        'order_status' => $status
    ]);
    exit();
}

// ---------------------------------------------
// Role-specific valid transitions
// ---------------------------------------------
$sellerTransitions = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['shipped', 'cancelled'],
    'shipped'   => ['delivered'],
    'delivered' => ['completed'],
    'completed' => [],
    'cancelled' => [],
];

$buyerTransitions = [
    'pending'   => ['cancelled'],   // buyer may cancel before seller confirms
    'confirmed' => [],              // must contact seller
    'shipped'   => ['completed'],   // buyer confirms delivery
    'delivered' => ['completed'],   // or completes from delivered
    'completed' => [],
    'cancelled' => [],
];

$transitions = $isSeller ? $sellerTransitions : $buyerTransitions;
$current     = $order['order_status'];

if (!in_array($status, $transitions[$current] ?? [], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

// ---------------------------------------------
// Apply the update
// ---------------------------------------------
try {
    $db->beginTransaction();

    // Build dynamic update
    $updateFields = "order_status = ?";
    $params = [$status];

    if ($status === 'shipped') {
        $updateFields .= ", shipped_date = NOW()";
    }
    if ($status === 'delivered') {
        $updateFields .= ", delivered_date = NOW()";
    }
    if ($status === 'cancelled') {
        $updateFields .= ", cancelled_at = NOW()";
    }
    if ($status === 'completed') {
        $updateFields .= ", completed_at = NOW()";
    }

    $params[] = $orderId;

    $db->update(
        "UPDATE orders SET $updateFields WHERE id = ?",
        $params
    );

    // Restore listing only when a cancelled order frees it up
    if ($status === 'cancelled') {
        $db->update(
            "UPDATE listings SET status = 'active' WHERE id = ? AND status = 'sold'",
            [$order['listing_id']]
        );
    }

    // ---------------------------------------------
    // Notification to the OTHER party
    // ---------------------------------------------
    $statusLabels = [
        'confirmed' => 'confirmed',
        'shipped'   => 'shipped',
        'delivered' => 'delivered',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];
    $statusLabel = $statusLabels[$status] ?? $status;

    if ($isSeller) {
        // Notify buyer
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'order', 'Order Status Updated', 
                     CONCAT('Your order #', ?, ' has been ', ?), 
                     CONCAT('/buyer/order-detail.php?id=', ?))",
            [
                $order['buyer_id'],
                $order['order_number'],
                $statusLabel,
                $orderId
            ]
        );
    } else {
        // Notify seller
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'order', 'Order Status Updated', 
                     CONCAT('Order #', ?, ' has been marked as ', ?), 
                     CONCAT('/seller/order-detail.php?id=', ?))",
            [
                $order['seller_id'],
                $order['order_number'],
                $statusLabel,
                $orderId
            ]
        );
    }

    // ---------------------------------------------
    // On completion, record seller payout ONCE
    // ---------------------------------------------
    if ($status === 'completed') {
        $existing = $db->fetchOne(
            "SELECT id FROM transactions 
             WHERE order_id = ? AND type = 'payout' LIMIT 1",
            [$orderId]
        );

        if (!$existing) {
            $db->insert(
                "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
                 VALUES (?, ?, 'payout', ?, 'Order payout', 'completed')",
                [$orderId, $order['seller_id'], $order['net_amount']]
            );
        }
    }

    // ---------------------------------------------
    // Activity log
    // ---------------------------------------------
    if (function_exists('logActivity')) {
        $actor = $isSeller ? 'seller' : 'buyer';
        logActivity(
            $userId,
            'order_status_' . $status,
            'Order #' . $order['order_number'] . ' set to ' . $status . ' by ' . $actor
        );
    }

    $db->commit();

    echo json_encode([
        'success'      => true,
        'message'      => 'Order status updated successfully',
        'order_status' => $status
    ]);

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log('Order Status Update Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update order. Please try again.']);
}