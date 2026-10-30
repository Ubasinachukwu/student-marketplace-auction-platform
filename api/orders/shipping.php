<?php
// marketplace/api/orders/shipping.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mailer.php';

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

$orderId         = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$trackingNumber  = trim((string)($_POST['tracking_number'] ?? ''));
$trackingCarrier = trim((string)($_POST['tracking_carrier'] ?? ''));
$shippedDate     = trim((string)($_POST['shipped_date'] ?? ''));

if ($shippedDate === '') {
    $shippedDate = date('Y-m-d');
}

$trackingNumber  = strip_tags($trackingNumber);
$trackingCarrier = strip_tags($trackingCarrier);

if (strlen($trackingNumber) > 100 || strlen($trackingCarrier) > 100) {
    echo json_encode(['success' => false, 'message' => 'Tracking number or carrier is too long']);
    exit();
}

if (!$orderId || $trackingNumber === '' || $trackingCarrier === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide tracking number and carrier']);
    exit();
}

$dateObj = DateTime::createFromFormat('Y-m-d', $shippedDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $shippedDate) {
    echo json_encode(['success' => false, 'message' => 'Invalid shipping date']);
    exit();
}

$order = $db->fetchOne(
    "SELECT * FROM orders WHERE id = ? AND seller_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

if ($order['order_status'] != 'confirmed') {
    echo json_encode(['success' => false, 'message' => 'Order must be confirmed before shipping']);
    exit();
}

try {
    $db->beginTransaction();

    $db->update(
        "UPDATE orders SET 
            tracking_number  = ?, 
            tracking_carrier = ?, 
            shipped_date     = ?,
            order_status     = 'shipped'
         WHERE id = ?",
        [$trackingNumber, $trackingCarrier, $shippedDate . ' 00:00:00', $orderId]
    );

    $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) 
         VALUES (?, 'shipping', 'Item Shipped', 
                 CONCAT('Your order #', ?, ' has been shipped! Tracking: ', ?), 
                 CONCAT('/buyer/order-detail.php?id=', ?))",
        [
            $order['buyer_id'],
            $order['order_number'],
            $trackingNumber,
            $orderId
        ]
    );

    $db->commit();

    // =============================================
    // POST-COMMIT: SEND SHIPPING EMAIL TO BUYER
    // =============================================
    $buyer = $db->fetchOne(
        "SELECT id, full_name, email FROM users WHERE id = ?",
        [$order['buyer_id']]
    );

    if ($buyer && !empty($buyer['email'])) {
        // Resolve carrier label (e.g. "GIG Logistics" → "GIG Logistics (GIGL)")
        $carrierLabel = $trackingCarrier;
        if (defined('NG_DELIVERY_CARRIERS') && isset(NG_DELIVERY_CARRIERS[$trackingCarrier])) {
            $carrierLabel = NG_DELIVERY_CARRIERS[$trackingCarrier];
        }

        // Estimated delivery = shipped_date + 3-7 days (rough estimate)
        $estimatedDelivery = date('M d, Y', strtotime($shippedDate . ' +5 days'));

        // Build a tracking link based on the carrier if possible
        $trackingLink = SITE_URL . '/buyer/order-detail.php?id=' . $orderId;
        $carrierLower = strtolower($carrierLabel);
        if (strpos($carrierLower, 'gig') !== false) {
            $trackingLink = 'https://giglogistics.com/tracking?waybill=' . urlencode($trackingNumber);
        } elseif (strpos($carrierLower, 'kwik') !== false) {
            $trackingLink = 'https://kwik.delivery/track?tracking_id=' . urlencode($trackingNumber);
        } elseif (strpos($carrierLower, 'dhl') !== false) {
            $trackingLink = 'https://www.dhl.com/ng-en/home/tracking.html?tracking-id=' . urlencode($trackingNumber);
        } elseif (strpos($carrierLower, 'fedex') !== false) {
            $trackingLink = 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($trackingNumber);
        } elseif (strpos($carrierLower, 'nipost') !== false || strpos($carrierLower, 'ems') !== false) {
            $trackingLink = 'https://www.nipost.gov.ng/track-trace/?tracking_number=' . urlencode($trackingNumber);
        }

        // Link to message the seller about this order
        $contactSellerLink = SITE_URL . '/buyer/messages.php?user=' . (int)$order['seller_id'] . '&order=' . (int)$orderId;

        $shippingHtml = renderEmailTemplate('shipping-update', [
            'USER_NAME'           => $buyer['full_name'],
            'ORDER_NUMBER'        => $order['order_number'],
            'TRACKING_NUMBER'     => $trackingNumber,
            'CARRIER'             => $carrierLabel,
            'SHIPPED_DATE'        => date('M d, Y', strtotime($shippedDate)),
            'ESTIMATED_DELIVERY'  => $estimatedDelivery,
            'TRACKING_LINK'       => $trackingLink,
            'ORDER_LINK'          => SITE_URL . '/buyer/order-detail.php?id=' . $orderId,
            'CONTACT_SELLER_LINK' => $contactSellerLink,
        ]);

        // Fallback if the template is missing
        if (empty($shippingHtml)) {
            $shippingHtml = '<h2>Your order has been shipped!</h2>'
                          . '<p>Hi ' . htmlspecialchars($buyer['full_name']) . ',</p>'
                          . '<p>Order #' . htmlspecialchars($order['order_number']) . ' has been shipped.</p>'
                          . '<p><strong>Carrier:</strong> ' . htmlspecialchars($carrierLabel) . '</p>'
                          . '<p><strong>Tracking:</strong> ' . htmlspecialchars($trackingNumber) . '</p>'
                          . '<p><a href="' . htmlspecialchars($trackingLink) . '">Track your package</a></p>';
        }

        sendEmail(
            $buyer['email'],
            'Your order has been shipped — ' . SITE_NAME,
            $shippingHtml,
            $buyer['full_name']
        );
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Shipping information updated successfully'
    ]);

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log('Shipping API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update shipping. Please try again.']);
}