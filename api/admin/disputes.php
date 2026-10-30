<?php
// marketplace/api/admin/disputes.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';       // sendEmail()
require_once __DIR__ . '/../../classes/Admin.php';

header('Content-Type: application/json');

// Require admin role
Session::requireRole('admin');

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db         = Database::getInstance();
$admin      = new Admin(Session::getUserId());
$action     = isset($_POST['action'])      ? sanitize($_POST['action'])               : '';
$disputeId  = isset($_POST['dispute_id'])  ? (int)$_POST['dispute_id']                : 0;
$resolution = isset($_POST['resolution'])  ? trim((string)$_POST['resolution'])       : '';
$refundAmount = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount']       : 0;

if (!$action || !$disputeId) {
    echo json_encode(['success' => false, 'message' => 'Action and dispute ID are required']);
    exit();
}

if (!in_array($action, ['resolve', 'close'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Load dispute with joined order/listing/user info (needed for notifications + emails)
$details = $db->fetchOne(
    "SELECT d.*, 
            o.order_id, o.order_number, o.buyer_id, o.seller_id, o.total_amount,
            l.title AS listing_title,
            ub.full_name AS buyer_name,  ub.email AS buyer_email,
            us.full_name AS seller_name, us.email AS seller_email
     FROM disputes d
     JOIN orders o   ON d.order_id   = o.id
     JOIN listings l ON o.listing_id = l.id
     JOIN users ub   ON o.buyer_id   = ub.id
     JOIN users us   ON o.seller_id  = us.id
     WHERE d.id = ?",
    [$disputeId]
);

if (!$details) {
    echo json_encode(['success' => false, 'message' => 'Dispute not found']);
    exit();
}

if (!in_array($details['status'], ['open', 'under_review'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dispute is already ' . $details['status']]);
    exit();
}

// Refund guard
if ($refundAmount < 0) {
    echo json_encode(['success' => false, 'message' => 'Refund amount cannot be negative']);
    exit();
}
if ($refundAmount > (float)$details['total_amount']) {
    echo json_encode([
        'success' => false,
        'message' => 'Refund cannot exceed order total of ' . formatNaira($details['total_amount'])
    ]);
    exit();
}

try {
    $db->beginTransaction();

    $newStatus = ($action === 'resolve') ? 'resolved' : 'closed';

    $db->update(
        "UPDATE disputes SET 
            status = ?, 
            resolution_notes = ?, 
            resolved_by = ?, 
            resolved_at = NOW() 
         WHERE id = ?",
        [$newStatus, $resolution, Session::getUserId(), $disputeId]
    );

    // If refund is issued (only on 'resolve')
    if ($action === 'resolve' && $refundAmount > 0) {
        $db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
             VALUES (?, ?, 'refund', ?, 'Refund from dispute resolution', 'completed')",
            [$details['order_id'], $details['raised_by'], $refundAmount]
        );

        // NOTE: reduces the recorded order total by the refund amount.
        // Remove this UPDATE if `orders.total_amount` should remain historical.
        $db->update(
            "UPDATE orders SET total_amount = total_amount - ? WHERE id = ?",
            [$refundAmount, $details['order_id']]
        );
    }

    // Log admin action
    $db->insert(
        "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
         VALUES (?, ?, 'dispute', ?, ?)",
        [
            Session::getUserId(),
            $action === 'resolve' ? 'resolve_dispute' : 'close_dispute',
            $disputeId,
            ucfirst($newStatus) . '. Notes: ' . ($resolution !== '' ? $resolution : '(none)')
        ]
    );

    $db->commit();

} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

// ============================================
// POST-COMMIT: notifications + emails
// (a failure here does NOT undo the resolution)
// ============================================
try {
    // In-app: buyer
    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'dispute', ?, ?, ?)",
        [
            $details['buyer_id'],
            'Dispute ' . ucfirst($newStatus),
            "Your dispute on order #{$details['order_number']} has been {$newStatus}.",
            '/buyer/order-detail.php?id=' . $details['order_id'],
        ]
    );

    // In-app: seller
    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'dispute', ?, ?, ?)",
        [
            $details['seller_id'],
            'Dispute ' . ucfirst($newStatus),
            "A dispute on order #{$details['order_number']} has been {$newStatus}.",
            '/seller/order-detail.php?id=' . $details['order_id'],
        ]
    );

    // Emails
    $notesText  = $resolution !== '' ? $resolution : 'No additional notes provided.';
    $refundLine = $refundAmount > 0
        ? "Refund issued: " . formatNaira($refundAmount) . "\n"
        : '';

    // Buyer email
    if (!empty($details['buyer_email'])) {
        $buyerBody =
            "Hi " . $details['buyer_name'] . ",\n\n" .
            "Your dispute on order #{$details['order_number']} for \"{$details['listing_title']}\" has been {$newStatus}.\n\n" .
            "Resolution notes:\n{$notesText}\n\n" .
            $refundLine .
            "View your order: " . SITE_URL . "/buyer/order-detail.php?id=" . $details['order_id'] . "\n\n" .
            SITE_NAME . " Team";

        sendEmail(
            $details['buyer_email'],
            'Dispute ' . $newStatus . ' — Order #' . $details['order_number'],
            nl2br($buyerBody),
            $details['buyer_name']
        );
    }

    // Seller email
    if (!empty($details['seller_email'])) {
        $sellerBody =
            "Hi " . $details['seller_name'] . ",\n\n" .
            "A dispute on order #{$details['order_number']} for \"{$details['listing_title']}\" has been {$newStatus}.\n\n" .
            "Resolution notes:\n{$notesText}\n\n" .
            $refundLine .
            "View the order: " . SITE_URL . "/seller/order-detail.php?id=" . $details['order_id'] . "\n\n" .
            SITE_NAME . " Team";

        sendEmail(
            $details['seller_email'],
            'Dispute ' . $newStatus . ' — Order #' . $details['order_number'],
            nl2br($sellerBody),
            $details['seller_name']
        );
    }

} catch (Throwable $e) {
    // Log it but don't fail the response
    error_log('Dispute API notification error: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => 'Dispute ' . $newStatus . ' successfully'
]);