<?php
// marketplace/api/dispute/create.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to raise a dispute']);
    exit();
}

if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db          = db();
$userId      = Session::getUserId();
$orderId     = isset($_POST['order_id'])     ? (int)$_POST['order_id']          : 0;
$disputeType = isset($_POST['dispute_type']) ? sanitize($_POST['dispute_type']) : '';
$description = isset($_POST['description'])  ? trim((string)$_POST['description']) : '';

// --- Role-specific allowed types -----------------------------------
$buyerTypes  = ['item_not_received', 'item_not_as_described', 'payment_issue', 'other'];
$sellerTypes = ['buyer_no_payment', 'buyer_unresponsive', 'buyer_fraudulent_claim', 'shipping_problem', 'payment_issue', 'other'];

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order']);
    exit();
}
if (mb_strlen($description) < 10) {
    echo json_encode(['success' => false, 'message' => 'Description must be at least 10 characters']);
    exit();
}
if (mb_strlen($description) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Description must be under 2000 characters']);
    exit();
}

// --- Load order + verify user is a participant ---------------------
$order = $db->fetchOne(
    "SELECT o.id, o.order_number, o.buyer_id, o.seller_id, o.order_status, o.total_amount,
            l.title AS listing_title,
            ub.full_name AS buyer_name,  ub.email AS buyer_email,
            us.full_name AS seller_name, us.email AS seller_email
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users ub   ON o.buyer_id   = ub.id
     JOIN users us   ON o.seller_id  = us.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$isBuyer  = ((int)$order['buyer_id']  === (int)$userId);
$isSeller = ((int)$order['seller_id'] === (int)$userId);

if (!$isBuyer && !$isSeller) {
    echo json_encode(['success' => false, 'message' => 'You are not a party to this order']);
    exit();
}

$raisedByType = $isBuyer ? 'buyer' : 'seller';
$allowedTypes = $isBuyer ? $buyerTypes : $sellerTypes;

if (!in_array($disputeType, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid dispute type for your role']);
    exit();
}

// --- Business rules ------------------------------------------------
if (!in_array($order['order_status'], ['delivered', 'completed'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'You can only raise a dispute after the order is delivered or completed'
    ]);
    exit();
}

$existing = $db->fetchOne(
    "SELECT id FROM disputes 
     WHERE order_id = ? AND status IN ('open', 'under_review')
     LIMIT 1",
    [$orderId]
);
if ($existing) {
    echo json_encode([
        'success' => false,
        'message' => 'A dispute is already open for this order',
        'dispute_id' => (int)$existing['id']
    ]);
    exit();
}

// --- Insert --------------------------------------------------------
try {
    $db->beginTransaction();

    $disputeId = $db->insert(
        "INSERT INTO disputes 
            (order_id, raised_by, raised_by_type, dispute_type, description, status) 
         VALUES (?, ?, ?, ?, ?, 'open')",
        [$orderId, $userId, $raisedByType, $disputeType, $description]
    );

    $db->commit();

} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log('Dispute create error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create dispute']);
    exit();
}

// --- Post-commit: notifications + emails ---------------------------
try {
    // Human-readable label for the chosen type
    $typeLabels = [
        'item_not_received'       => 'Item never arrived',
        'item_not_as_described'   => 'Item not as described',
        'payment_issue'           => 'Payment issue',
        'other'                   => 'Other',
        'buyer_no_payment'        => 'Buyer has not paid',
        'buyer_unresponsive'      => 'Buyer is unresponsive',
        'buyer_fraudulent_claim'  => 'Fraudulent buyer claim',
        'shipping_problem'        => 'Shipping problem',
    ];
    $typeLabel  = $typeLabels[$disputeType] ?? ucfirst(str_replace('_', ' ', $disputeType));
    $raiserName = $isBuyer ? $order['buyer_name'] : $order['seller_name'];
    $raiserRole = $isBuyer ? 'buyer' : 'seller';

    // 1) Admin in-app notifications
    $admins = $db->fetchAll("SELECT id, full_name, email FROM users WHERE user_type = 'admin'");

    foreach ($admins as $admin) {
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) 
             VALUES (?, 'dispute', ?, ?, ?)",
            [
                $admin['id'],
                'New Dispute Raised',
                "A {$raiserRole} ({$raiserName}) raised a dispute on order #{$order['order_number']}: {$typeLabel}",
                '/admin/dispute-detail.php?id=' . $disputeId,
            ]
        );
    }

    // 2) Admin emails
    if (!empty($admins)) {
        $adminBody =
            "A new dispute has been raised.\n\n" .
            "Order: #{$order['order_number']}\n" .
            "Item: {$order['listing_title']}\n" .
            "Raised by: {$raiserName} ({$raiserRole})\n" .
            "Type: {$typeLabel}\n\n" .
            "Description:\n{$description}\n\n" .
            "Review it: " . SITE_URL . "/admin/dispute-detail.php?id={$disputeId}\n\n" .
            SITE_NAME . " Team";

        foreach ($admins as $admin) {
            if (empty($admin['email'])) continue;
            sendEmail(
                $admin['email'],
                'New dispute on order #' . $order['order_number'],
                nl2br($adminBody),
                $admin['full_name']
            );
        }
    }

    // 3) Other party — in-app
    $otherUserId = $isBuyer ? (int)$order['seller_id'] : (int)$order['buyer_id'];
    $otherLink   = ($isBuyer ? '/seller/order-detail.php?id=' : '/buyer/order-detail.php?id=') . $orderId;

    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'dispute', ?, ?, ?)",
        [
            $otherUserId,
            'A Dispute Was Raised',
            "A {$raiserRole} raised a dispute on order #{$order['order_number']}: {$typeLabel}",
            $otherLink,
        ]
    );

    // 4) Other party — email
    $otherEmail = $isBuyer ? $order['seller_email'] : $order['buyer_email'];
    $otherName  = $isBuyer ? $order['seller_name']  : $order['buyer_name'];

    if (!empty($otherEmail)) {
        $otherBody =
            "Hi {$otherName},\n\n" .
            "A {$raiserRole} has raised a dispute on order #{$order['order_number']} for \"{$order['listing_title']}\".\n\n" .
            "Type: {$typeLabel}\n" .
            "Their description:\n{$description}\n\n" .
            "An admin will review this dispute. You can respond via the order page:\n" .
            SITE_URL . $otherLink . "\n\n" .
            SITE_NAME . " Team";

        sendEmail(
            $otherEmail,
            'Dispute raised on order #' . $order['order_number'],
            nl2br($otherBody),
            $otherName
        );
    }

} catch (Throwable $e) {
    error_log('Dispute create notification error: ' . $e->getMessage());
}

echo json_encode([
    'success'    => true,
    'message'    => 'Dispute submitted successfully. An admin will review it shortly.',
    'dispute_id' => (int)$disputeId,
]);