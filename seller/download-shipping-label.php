<?php
// marketplace/seller/download-shipping-label.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/fpdf/receipt-template.php';

Session::requireSeller();

$db = db();
$userId  = Session::getUserId();
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$orderId) {
    setError('Invalid order');
    redirect(SITE_URL . '/seller/orders.php');
}

$order = $db->fetchOne(
    "SELECT o.*, 
            l.title as listing_title,
            s.full_name as seller_name,
            s.phone as seller_phone,
            b.full_name as buyer_name,
            b.email as buyer_email,
            b.phone as buyer_phone,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image
     FROM orders o
     JOIN listings l ON o.listing_id = l.id
     JOIN users s ON o.seller_id = s.id
     JOIN users b ON o.buyer_id = b.id
     WHERE o.id = ? AND o.seller_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setError('Order not found');
    redirect(SITE_URL . '/seller/orders.php');
}

if (!in_array($order['order_status'], ['confirmed', 'shipped', 'delivered', 'completed'], true)) {
    setError('Order must be confirmed before printing a shipping label');
    redirect(SITE_URL . '/seller/order-detail.php?id=' . $orderId);
}

$carrierLabel = !empty($order['tracking_carrier'])
    ? (NG_DELIVERY_CARRIERS[$order['tracking_carrier']] ?? $order['tracking_carrier'])
    : '—';

$pdf = new MarketplacePDF();
$pdf->AddPage();
$pdf->siteHeader('Shipping Label');

$pdf->setFontSafe('B', 16);
$pdf->Cell(0, 10, 'ORDER ' . $order['order_number'], 0, 1, 'C');
$pdf->Ln(2);

$pdf->hr();

// FROM
$pdf->setFontSafe('B', 10);
$pdf->Cell(0, 6, 'FROM (Seller)', 0, 1);
$pdf->setFontSafe('', 11);
$pdf->Cell(0, 6, $order['seller_name'], 0, 1);
if (!empty($order['seller_phone'])) {
    $pdf->Cell(0, 6, 'Phone: ' . $order['seller_phone'], 0, 1);
}
$pdf->Ln(4);

$pdf->hr();

// TO — with item image on the right
$pdf->setFontSafe('B', 12);
$pdf->Cell(0, 8, 'TO (Buyer)', 0, 1);

$toStartY = $pdf->GetY();

// Right-aligned image (50mm)
$imgPath = $pdf->prepareImage($order['listing_image'] ?? null, 600);
if ($imgPath) {
    try {
        $pdf->Image($imgPath, 145, $toStartY, 50, 50);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Rect(145, $toStartY, 50, 50);
    } catch (Exception $e) {
        error_log('Label image failed: ' . $e->getMessage());
    }
}

// Buyer info (left side, narrower)
$pdf->setFontSafe('B', 16);
$pdf->Cell(100, 9, $order['buyer_name'], 0, 1);

$pdf->setFontSafe('', 13);
if (!empty($order['buyer_phone'])) {
    $pdf->Cell(100, 7, 'Phone: ' . $order['buyer_phone'], 0, 1);
}
$pdf->Cell(100, 7, 'Email: ' . $order['buyer_email'], 0, 1);

$pdf->setFontSafe('', 12);
$pdf->MultiCell(100, 6, $order['shipping_address'] ?? '');

if (!empty($order['shipping_lga']) || !empty($order['shipping_state'])) {
    $pdf->setFontSafe('B', 12);
    $pdf->Cell(100, 7, trim(($order['shipping_lga'] ?? '') . ', ' . ($order['shipping_state'] ?? ''), ', '), 0, 1);
}

// Ensure we clear the image height
$minY = $toStartY + 53;
if ($pdf->GetY() < $minY) {
    $pdf->SetY($minY);
}
$pdf->Ln(3);

$pdf->hr();

$pdf->kvRow('Carrier:', $carrierLabel);

if (!empty($order['tracking_number'])) {
    $pdf->setFontSafe('B', 13);
    $pdf->Cell(60, 8, 'Tracking #:', 0, 0);
    $pdf->Cell(0, 8, $order['tracking_number'], 0, 1);
} else {
    $pdf->setFontSafe('I', 10);
    $pdf->Cell(0, 6, 'No tracking number provided', 0, 1);
}

$pdf->Ln(2);
$pdf->hr();

$pdf->setFontSafe('B', 10);
$pdf->Cell(0, 6, 'Item:', 0, 1);
$pdf->setFontSafe('', 10);
$pdf->MultiCell(0, 6, $order['listing_title']);

$pdf->Ln(3);

if (($order['payment_method'] ?? '') === 'pay_on_delivery') {
    $pdf->setFontSafe('B', 14);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell(0, 10, 'COLLECT ON DELIVERY: ' . $pdf->money($order['total_amount']), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->siteFooter();
$pdf->Output('I', 'shipping-label-' . $order['order_number'] . '.pdf');