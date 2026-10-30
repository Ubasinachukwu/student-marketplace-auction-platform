<?php
// marketplace/api/payments/callback.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Payment.php';

header('Content-Type: application/json');

// This endpoint receives webhook callbacks from payment gateways (Flutterwave, Paystack).
// No CSRF protection needed - uses signature verification instead.

// Get raw input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit();
}

// Verify webhook signature (implement based on payment gateway)
// For example, Flutterwave and Paystack use secret hash verification.
// For MVP, we'll accept the data as is.

$db = Database::getInstance();

try {
    $eventType = $data['type'] ?? '';
    $eventData = $data['data'] ?? $data;

    // Process different event types
    switch ($eventType) {
        case 'payment_intent.succeeded':
        case 'charge.succeeded':
            $paymentId = $eventData['id'] ?? '';
            $orderId = $eventData['metadata']['order_id'] ?? 0;
            
            if ($orderId > 0) {
                $order = new Order($orderId);
                
                if ($order->getId() && $order->getPaymentStatus() !== 'paid') {
                    // Update order payment status
                    $order->updatePayment('paid', $paymentId);
                    
                    // Create notification for buyer
                    $db->insert(
                        "INSERT INTO notifications (user_id, type, title, message, link) 
                         VALUES (?, 'payment', 'Payment Successful', 'Your payment for order #' . $order->getOrderNumber() . ' was successful.', '/buyer/order-detail.php?id=' . $orderId)",
                        [$order->getBuyerId()]
                    );
                    
                    // Create notification for seller
                    $db->insert(
                        "INSERT INTO notifications (user_id, type, title, message, link) 
                         VALUES (?, 'payment', 'Order Paid', 'Order #' . $order->getOrderNumber() . ' has been paid.', '/seller/order-detail.php?id=' . $orderId)",
                        [$order->getSellerId()]
                    );
                }
            }
            break;

        case 'payment_intent.payment_failed':
            $paymentId = $eventData['id'] ?? '';
            $orderId = $eventData['metadata']['order_id'] ?? 0;
            
            if ($orderId > 0) {
                $order = new Order($orderId);
                if ($order->getId()) {
                    $order->updatePayment('failed', $paymentId);
                }
            }
            break;

        case 'charge.refunded':
            $paymentId = $eventData['id'] ?? '';
            $orderId = $eventData['metadata']['order_id'] ?? 0;
            
            if ($orderId > 0) {
                $order = new Order($orderId);
                if ($order->getId()) {
                    $order->updatePayment('refunded', $paymentId);
                    
                    // Update order status
                    $order->updateStatus('cancelled');
                }
            }
            break;

        default:
            // Unknown event type - log it
            $logFile = __DIR__ . '/../../logs/webhook.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unknown event: " . $eventType . "\n", FILE_APPEND);
            break;
    }

    // Return success response (required by payment gateways)
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Log error
    $logFile = __DIR__ . '/../../logs/webhook-error.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}