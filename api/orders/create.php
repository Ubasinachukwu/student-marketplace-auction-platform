<?php
// marketplace/api/orders/create.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Listing.php';

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

$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$shippingAddress = isset($_POST['shipping_address']) ? sanitize($_POST['shipping_address']) : '';
$paymentMethod = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : 'stripe';
$buyerMessage = isset($_POST['buyer_message']) ? sanitize($_POST['buyer_message']) : '';

if (!$listingId || empty($shippingAddress)) {
    echo json_encode(['success' => false, 'message' => 'Listing ID and shipping address are required']);
    exit();
}

try {
    $listing = new Listing($listingId);
    
    if (!$listing->getId()) {
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        exit();
    }

    $userId = Session::getUserId();

    // Calculate price
    if ($listing->getSaleType() === 'fixed') {
        $price = $listing->getPrice();
    } elseif ($listing->getSaleType() === 'hybrid' && $listing->getBuyItNowPrice() > 0) {
        $price = $listing->getBuyItNowPrice();
    } else {
        echo json_encode(['success' => false, 'message' => 'Buy Now not available for this listing']);
        exit();
    }

    // Create order
    $orderData = [
        'listing_id' => $listingId,
        'buyer_id' => $userId,
        'seller_id' => $listing->getSellerId(),
        'total_amount' => $price,
        'shipping_address' => $shippingAddress,
        'buyer_message' => $buyerMessage,
        'payment_method' => $paymentMethod,
        'platform_fee' => calculateFee($price, PLATFORM_FEE_PERCENTAGE)
    ];

    $order = new Order();
    $orderId = $order->create($orderData);

    // Update listing status
    $listing->updateStatus('sold');

    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $orderId,
        'order_number' => $order->getOrderNumber(),
        'redirect' => '/buyer/payment-success.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}