<?php
// marketplace/api/payments/process.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Payment.php';

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

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$paymentMethod = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : 'stripe';
$paymentToken = isset($_POST['payment_token']) ? sanitize($_POST['payment_token']) : '';

if (!$orderId || empty($paymentToken)) {
    echo json_encode(['success' => false, 'message' => 'Order ID and payment token are required']);
    exit();
}

try {
    $order = new Order($orderId);
    
    if (!$order->getId()) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    $userId = Session::getUserId();

    // Verify order belongs to user
    if ($order->getBuyerId() !== $userId) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to pay for this order']);
        exit();
    }

    // Check if already paid
    if ($order->getPaymentStatus() === 'paid') {
        echo json_encode([
            'success' => true, 
            'message' => 'Order already paid',
            'redirect' => '/buyer/order-detail.php?id=' . $orderId
        ]);
        exit();
    }

    // Process payment
    $paymentData = [
        'order_id' => $orderId,
        'user_id' => $userId,
        'amount' => $order->getTotalAmount(),
        'payment_method' => $paymentMethod,
        'payment_token' => $paymentToken
    ];

    $payment = new Payment();
    $result = $payment->processPayment($paymentData);

    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $result['payment_id'],
        'transaction_id' => $result['transaction_id'],
        'redirect' => '/buyer/payment-success.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}