<?php
// marketplace/includes/receipt-template.php
// Shared HTML template renderer for printable receipts and labels.

/**
 * Render the printable receipt page (buyer OR seller view).
 */
function renderReceiptPage($order, $role = 'buyer') {
    $siteName   = defined('SITE_NAME')   ? SITE_NAME   : 'MarketPlace';
    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
    $siteUrl    = defined('SITE_URL')    ? SITE_URL    : '';

    $itemPrice = (float)$order['total_amount']
               - (float)$order['shipping_cost']
               - (float)$order['tax_amount'];

    $carrierLabel = '';
    if (!empty($order['tracking_carrier'])) {
        $carrierLabel = NG_DELIVERY_CARRIERS[$order['tracking_carrier']]
                     ?? $order['tracking_carrier'];
    }
    $preferredLabel = '';
    if (!empty($order['preferred_carrier'])) {
        $preferredLabel = NG_DELIVERY_CARRIERS[$order['preferred_carrier']]
                       ?? $order['preferred_carrier'];
    }

    $methodLabels = [
        'pay_on_delivery' => 'Pay on Delivery (Cash)',
        'paystack'        => 'Bank Transfer (Paystack)',
        'flutterwave'     => 'Flutterwave (Card/Bank/USSD)'
        
    ];
    $paymentLabel = $methodLabels[$order['payment_method']]
                 ?? ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?php echo htmlspecialchars($order['order_number']); ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #222;
        background: #f4f4f4;
        margin: 0;
        padding: 20px;
        font-size: 14px;
    }
    .paper {
        max-width: 780px;
        margin: 0 auto;
        background: #fff;
        padding: 40px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        border-radius: 6px;
    }
    .header {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 2px solid #111;
    }
    .header h1 {
        margin: 0 0 6px 0;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .header .doc-title {
        font-size: 15px;
        color: #666;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    .meta {
        display: flex;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid #e0e0e0;
        font-size: 13px;
    }
    .meta strong { font-size: 15px; color: #111; }
    .section {
        padding: 18px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .section:last-of-type { border-bottom: none; }
    .section h3 {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #888;
        margin: 0 0 10px 0;
        font-weight: 700;
    }
    .two-col {
        display: flex;
        gap: 30px;
    }
    .two-col > div { flex: 1; }
    .party-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
    .party-line { color: #555; font-size: 13px; line-height: 1.5; }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    table.items th, table.items td {
        padding: 10px 8px;
        text-align: left;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }
    table.items th {
        background: #fafafa;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #555;
    }
    table.items .r { text-align: right; }
    table.summary {
        width: 100%;
        margin-top: 6px;
    }
    table.summary td {
        padding: 8px 0;
        font-size: 14px;
    }
    table.summary td.r { text-align: right; }
    table.summary tr.total td {
        border-top: 2px solid #111;
        padding-top: 12px;
        font-weight: 800;
        font-size: 17px;
    }
    table.summary tr.net td {
        color: #0a7d2c;
        font-weight: 700;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-paid { background: #d4f4dd; color: #0a7d2c; }
    .badge-pending { background: #fff3cd; color: #a06b00; }
    .badge-other { background: #e0e0e0; color: #555; }
    .tracking-box {
        background: #f9f9f9;
        padding: 14px 18px;
        border-radius: 4px;
        margin-top: 8px;
        font-size: 13px;
    }
    .tracking-box strong { font-size: 15px; }
    .footer {
        text-align: center;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 1px solid #e0e0e0;
        color: #888;
        font-size: 12px;
    }
    .actions {
        max-width: 780px;
        margin: 20px auto 0;
        text-align: center;
    }
    .actions button {
        background: #0d6efd;
        color: #fff;
        border: none;
        padding: 12px 30px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        margin: 0 6px;
    }
    .actions button:hover { background: #0b5ed7; }
    .actions a {
        display: inline-block;
        margin-left: 12px;
        color: #666;
        text-decoration: none;
        font-size: 13px;
    }
    @media print {
        body { background: #fff; padding: 0; font-size: 12px; }
        .paper { box-shadow: none; max-width: 100%; padding: 10mm; border-radius: 0; }
        .actions { display: none !important; }
        .header h1 { font-size: 22px; }
        .section { padding: 10px 0; }
    }
</style>
</head>
<body>

<div class="paper">

    <div class="header">
        <h1><?php echo htmlspecialchars($siteName); ?></h1>
        <p class="doc-title">Order Receipt</p>
    </div>

    <div class="meta">
        <div>
            <strong>Order #<?php echo htmlspecialchars($order['order_number']); ?></strong><br>
            Placed on <?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?>
        </div>
        <div style="text-align:right;">
            <span class="badge badge-<?php
                echo $order['payment_status'] === 'paid' ? 'paid'
                   : ($order['payment_status'] === 'pending' ? 'pending' : 'other');
            ?>">
                <?php echo htmlspecialchars(ucfirst($order['payment_status'] ?? 'unknown')); ?>
            </span><br>
            <span style="color:#666; margin-top:6px; display:inline-block;">
                Status: <?php echo htmlspecialchars(ucfirst($order['order_status'] ?? 'pending')); ?>
            </span>
        </div>
    </div>

    <div class="section">
        <div class="two-col">
            <div>
                <h3>Buyer</h3>
                <div class="party-name"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                <div class="party-line">
                    <?php echo htmlspecialchars($order['buyer_email']); ?>
                    <?php if (!empty($order['buyer_phone'])): ?>
                        <br><?php echo htmlspecialchars($order['buyer_phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <h3>Seller</h3>
                <div class="party-name"><?php echo htmlspecialchars($order['seller_name']); ?></div>
                <div class="party-line">
                    <?php echo htmlspecialchars($order['seller_email']); ?>
                    <?php if (!empty($order['seller_phone'])): ?>
                        <br><?php echo htmlspecialchars($order['seller_phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Item</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="r">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($order['listing_title']); ?></td>
                    <td class="r"><?php echo formatNaira($itemPrice); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Summary</h3>
        <table class="summary">
            <tr>
                <td>Item Price</td>
                <td class="r"><?php echo formatNaira($itemPrice); ?></td>
            </tr>
            <?php if ((float)$order['shipping_cost'] > 0): ?>
                <tr>
                    <td>Shipping</td>
                    <td class="r"><?php echo formatNaira($order['shipping_cost']); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ((float)$order['tax_amount'] > 0): ?>
                <tr>
                    <td>Tax</td>
                    <td class="r"><?php echo formatNaira($order['tax_amount']); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td>Platform Fee</td>
                <td class="r"><?php echo formatNaira($order['platform_fee']); ?></td>
            </tr>
            <tr class="total">
                <td>Total</td>
                <td class="r"><?php echo formatNaira($order['total_amount']); ?></td>
            </tr>
            <?php if ($role === 'seller'): ?>
                <tr class="net">
                    <td>Net to You</td>
                    <td class="r"><?php echo formatNaira($order['net_amount']); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="section">
        <h3>Payment</h3>
        <div class="party-line">
            <strong><?php echo htmlspecialchars($paymentLabel); ?></strong>
        </div>
    </div>

    <?php if (!empty($order['shipping_address'])): ?>
        <div class="section">
            <h3>Shipping Address</h3>
            <div class="party-line">
                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                <?php if (!empty($order['shipping_lga']) || !empty($order['shipping_state'])): ?>
                    <br><?php echo htmlspecialchars(trim(($order['shipping_lga'] ?? '') . ', ' . ($order['shipping_state'] ?? ''), ', ')); ?>
                <?php endif; ?>
            </div>

            <?php if ($preferredLabel): ?>
                <div class="tracking-box">
                    <strong>Preferred Carrier:</strong> <?php echo htmlspecialchars($preferredLabel); ?>
                </div>
            <?php endif; ?>

            <?php if ($carrierLabel): ?>
                <div class="tracking-box">
                    <strong>Shipped Via:</strong> <?php echo htmlspecialchars($carrierLabel); ?>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <br><strong>Tracking #:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        Thank you for shopping with <?php echo htmlspecialchars($siteName); ?><br>
        <?php if ($adminEmail): ?>
            Support: <?php echo htmlspecialchars($adminEmail); ?>
        <?php endif; ?>
    </div>

</div>

<div class="actions">
    <button onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="javascript:history.back()">← Back</a>
</div>

<script>
    // Auto-open print dialog when the user opens this page with ?autoprint=1
    if (new URLSearchParams(window.location.search).has('autoprint')) {
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    }
</script>

</body>
</html>
    <?php
}

/**
 * Render the printable shipping label (seller view only).
 */
function renderShippingLabelPage($order) {
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'MarketPlace';

    $carrierLabel = !empty($order['tracking_carrier'])
        ? (NG_DELIVERY_CARRIERS[$order['tracking_carrier']] ?? $order['tracking_carrier'])
        : '—';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shipping Label <?php echo htmlspecialchars($order['order_number']); ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        background: #f4f4f4;
        margin: 0;
        padding: 20px;
        color: #111;
    }
    .label {
        max-width: 640px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border: 3px dashed #111;
        border-radius: 6px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    .label-header {
        text-align: center;
        border-bottom: 3px solid #111;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .label-header .site {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #666;
        margin-bottom: 6px;
    }
    .label-header .order {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 1px;
    }
    .block {
        padding: 16px 0;
        border-bottom: 2px solid #e0e0e0;
    }
    .block:last-child { border-bottom: none; }
    .block-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #888;
        margin-bottom: 6px;
        font-weight: 700;
    }
    .from-name { font-weight: 700; font-size: 15px; }
    .from-line { font-size: 13px; color: #555; margin-top: 2px; }
    .to-name { font-size: 24px; font-weight: 800; margin-bottom: 6px; }
    .to-phone { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    .to-address { font-size: 15px; line-height: 1.5; }
    .to-city { font-size: 17px; font-weight: 700; margin-top: 6px; }
    .meta-grid {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding-top: 14px;
    }
    .meta-grid > div { flex: 1; }
    .meta-grid .key {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        font-weight: 700;
    }
    .meta-grid .val { font-size: 15px; font-weight: 700; margin-top: 3px; }
    .cod-box {
        margin-top: 16px;
        text-align: center;
        background: #111;
        color: #fff;
        padding: 14px;
        font-size: 18px;
        font-weight: 800;
        border-radius: 4px;
        letter-spacing: 0.5px;
    }
    .actions {
        max-width: 640px;
        margin: 20px auto 0;
        text-align: center;
    }
    .actions button {
        background: #0d6efd;
        color: #fff;
        border: none;
        padding: 12px 30px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        margin: 0 6px;
    }
    .actions button:hover { background: #0b5ed7; }
    .actions a {
        display: inline-block;
        margin-left: 12px;
        color: #666;
        text-decoration: none;
        font-size: 13px;
    }
    @media print {
        body { background: #fff; padding: 0; }
        .label { box-shadow: none; border-width: 2px; padding: 20px; }
        .actions { display: none !important; }
    }
</style>
</head>
<body>

<div class="label">

    <div class="label-header">
        <div class="site"><?php echo htmlspecialchars($siteName); ?></div>
        <div class="order">ORDER <?php echo htmlspecialchars($order['order_number']); ?></div>
    </div>

    <div class="block">
        <div class="block-label">From (Seller)</div>
        <div class="from-name"><?php echo htmlspecialchars($order['seller_name']); ?></div>
        <div class="from-line">
            <?php if (!empty($order['seller_phone'])): ?>
                Phone: <?php echo htmlspecialchars($order['seller_phone']); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="block">
        <div class="block-label">Deliver To</div>
        <div class="to-name"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
        <?php if (!empty($order['buyer_phone'])): ?>
            <div class="to-phone">📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></div>
        <?php endif; ?>
        <div class="to-address">
            <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '')); ?>
        </div>
        <?php if (!empty($order['shipping_lga']) || !empty($order['shipping_state'])): ?>
            <div class="to-city">
                <?php echo htmlspecialchars(trim(($order['shipping_lga'] ?? '') . ', ' . ($order['shipping_state'] ?? ''), ', ')); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="block">
        <div class="meta-grid">
            <div>
                <div class="key">Carrier</div>
                <div class="val"><?php echo htmlspecialchars($carrierLabel); ?></div>
            </div>
            <div>
                <div class="key">Tracking #</div>
                <div class="val">
                    <?php echo !empty($order['tracking_number'])
                        ? htmlspecialchars($order['tracking_number'])
                        : '—'; ?>
                </div>
            </div>
        </div>

        <?php if (($order['payment_method'] ?? '') === 'pay_on_delivery'): ?>
            <div class="cod-box">
                COLLECT ON DELIVERY: <?php echo formatNaira($order['total_amount']); ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<div class="actions">
    <button onclick="window.print()">🖨️ Print Label</button>
    <a href="javascript:history.back()">← Back</a>
</div>

</body>
</html>
    <?php
}