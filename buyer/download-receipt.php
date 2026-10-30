<?php
// marketplace/buyer/download-receipt.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/fpdf/receipt-template.php';

Session::requireLogin();

$db = db();
$userId  = Session::getUserId();
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$orderId) {
    setError('Invalid order');
    redirect(SITE_URL . '/buyer/orders.php');
}

$order = $db->fetchOne(
    "SELECT o.*, 
            l.title as listing_title,
            l.description as listing_description,
            s.full_name as seller_name,
            s.email as seller_email,
            s.phone as seller_phone,
            b.full_name as buyer_name,
            b.email as buyer_email,
            b.phone as buyer_phone,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users s ON o.seller_id = s.id
     JOIN users b ON o.buyer_id = b.id
     WHERE o.id = ? AND o.buyer_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/buyer/orders.php');
}

$pdf = new MarketplacePDF();
$pdf->AddPage();
$pdf->siteHeader('Order Receipt');

// Order meta
$pdf->setFontSafe('B', 12);
$pdf->Cell(0, 8, 'Order #' . $order['order_number'], 0, 1);
$pdf->setFontSafe('', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Placed on ' . date('M d, Y g:i A', strtotime($order['created_at'])), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

$pdf->hr();

// Parties
$pdf->setFontSafe('B', 11);
$pdf->Cell(95, 7, 'Buyer', 0, 0);
$pdf->Cell(95, 7, 'Seller', 0, 1);
$pdf->setFontSafe('', 10);
$pdf->Cell(95, 5, $order['buyer_name'], 0, 0);
$pdf->Cell(95, 5, $order['seller_name'], 0, 1);
$pdf->Cell(95, 5, $order['buyer_email'], 0, 0);
$pdf->Cell(95, 5, $order['seller_email'], 0, 1);
if (!empty($order['buyer_phone']) || !empty($order['seller_phone'])) {
    $pdf->Cell(95, 5, $order['buyer_phone'] ?? '', 0, 0);
    $pdf->Cell(95, 5, $order['seller_phone'] ?? '', 0, 1);
}
$pdf->Ln(4);

$pdf->hr();

// Item with image
$pdf->setFontSafe('B', 11);
$pdf->Cell(0, 7, 'Item', 0, 1);

$imageStartY = $pdf->GetY();
$imageConsumed = $pdf->addItemImage($order['listing_image'] ?? null, 35);

if ($imageConsumed > 0) {
    // Image was drawn — text flows to the right
    $pdf->setFontSafe('B', 11);
    $pdf->MultiCell(150, 6, $order['listing_title'], 0, 'L');
    $pdf->setFontSafe('', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(150, 5, $order['listing_description'] ?? '');
    $pdf->SetTextColor(0, 0, 0);

    // Ensure we clear the image height
    $minY = $imageStartY + 38;
    if ($pdf->GetY() < $minY) {
        $pdf->SetY($minY);
    }
} else {
    // No image — full-width text
    $pdf->setFontSafe('', 10);
    $pdf->MultiCell(0, 6, $order['listing_title']);
}
$pdf->Ln(3);

$pdf->hr();

// Summary
$pdf->setFontSafe('B', 11);
$pdf->Cell(0, 7, 'Summary', 0, 1);

$itemPrice = (float)$order['total_amount'] - (float)$order['shipping_cost'] - (float)$order['tax_amount'];
$rows = [
    ['Item Price',   $pdf->money($itemPrice)],
    ['Shipping',     $pdf->money($order['shipping_cost'])],
    ['Tax',          $pdf->money($order['tax_amount'])],
    ['Platform Fee', $pdf->money($order['platform_fee'])],
];
foreach ($rows as $row) {
    $pdf->setFontSafe('', 10);
    $pdf->Cell(140, 6, $row[0], 0, 0);
    $pdf->Cell(50, 6, $row[1], 0, 1, 'R');
}
$pdf->setFontSafe('B', 11);
$pdf->Cell(140, 7, 'Total', 0, 0);
$pdf->Cell(50, 7, $pdf->money($order['total_amount']), 0, 1, 'R');
$pdf->Ln(2);

// Payment + Status
$pdf->hr();
$pdf->kvRow('Payment Method:', ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')));
$pdf->kvRow('Payment Status:', ucfirst($order['payment_status'] ?? 'N/A'));
$pdf->kvRow('Order Status:',   ucfirst($order['order_status'] ?? 'N/A'));
$pdf->Ln(4);

// Shipping
if (!empty($order['shipping_address'])) {
    $pdf->hr();
    $pdf->setFontSafe('B', 11);
    $pdf->Cell(0, 7, 'Shipping Address', 0, 1);
    $pdf->setFontSafe('', 10);
    $pdf->MultiCell(0, 6, $order['shipping_address']);
    $pdf->Ln(2);

    if (!empty($order['preferred_carrier'])) {
        $carrierLabel = NG_DELIVERY_CARRIERS[$order['preferred_carrier']] ?? $order['preferred_carrier'];
        $pdf->kvRow('Preferred Carrier:', $carrierLabel);
    }
    if (!empty($order['tracking_carrier'])) {
        $carrierLabel = NG_DELIVERY_CARRIERS[$order['tracking_carrier']] ?? $order['tracking_carrier'];
        $pdf->kvRow('Shipped Via:', $carrierLabel);
    }
    if (!empty($order['tracking_number'])) {
        $pdf->kvRow('Tracking #:', $order['tracking_number']);
    }
}

$pdf->siteFooter();

$pdf->Output('I', 'receipt-' . $order['order_number'] . '.pdf');