<?php
// marketplace/config/flutterwave-helper.php
// Flutterwave v3 Payment Helper Functions

/**
 * Initialize a Flutterwave v3 payment using hosted Standard Checkout.
 * Returns a redirect URL where the user pays on Flutterwave's page.
 */
function initializeFlutterwavePayment($paymentData) {
    if (empty(FLUTTERWAVE_SECRET_KEY)) {
        return ['success' => false, 'message' => 'Flutterwave secret key missing'];
    }

    $txRef = 'TXN-' . time() . '-' . rand(1000, 9999);

    $payload = [
        'tx_ref'        => $txRef,
        'amount'        => (float)$paymentData['amount'],
        'currency'      => 'NGN',
        'redirect_url'  => $paymentData['redirect_url'],
        'payment_options' => 'card,banktransfer,ussd',
        'customer'      => [
            'email'       => $paymentData['customer_email'],
            'name'        => $paymentData['customer_name'] ?? 'Customer',
            'phonenumber' => $paymentData['customer_phone'] ?? '',
        ],
        'customizations' => [
            'title'       => SITE_NAME,
            'description' => $paymentData['description'] ?? 'Order Payment',
            'logo'        => SITE_URL . '/assets/images/logo.png',
        ],
        'meta' => [
            'order_id' => $paymentData['order_id'],
            'user_id'  => $paymentData['user_id'],
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => FLUTTERWAVE_API_BASE . '/payments',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . FLUTTERWAVE_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Flutterwave Init Error: ' . $error);
        return ['success' => false, 'message' => 'Connection failed: ' . $error];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === 'success') {
        return [
            'success' => true,
            'link'    => $data['data']['link'] ?? '',
            'tx_ref'  => $txRef,
        ];
    }

    $msg = $data['message'] ?? 'Unknown error';
    error_log('Flutterwave Init Failed: ' . $response);
    return ['success' => false, 'message' => 'Flutterwave: ' . $msg];
}

/**
 * Verify a Flutterwave v3 transaction using the transaction_id from the redirect.
 * Function name kept the same so payment-success.php doesn't need changes.
 */
function verifyFlutterwaveTransaction($transactionId) {
    if (empty(FLUTTERWAVE_SECRET_KEY)) {
        return ['success' => false, 'message' => 'Flutterwave secret key missing'];
    }

    $url = FLUTTERWAVE_API_BASE . '/transactions/' . urlencode($transactionId) . '/verify';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . FLUTTERWAVE_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === 'success') {
        $txData = $data['data'] ?? [];

        return [
            'success'        => true,
            'status'         => $txData['status'] ?? 'unknown',
            'amount'         => $txData['amount'] ?? 0,
            'currency'       => $txData['currency'] ?? 'NGN',
            'tx_ref'         => $txData['tx_ref'] ?? '',
            'transaction_id' => $txData['id'] ?? $transactionId,
            'payment_type'   => $txData['payment_type'] ?? 'card',
            'customer'       => $txData['customer'] ?? [],
            'data'           => $txData,
        ];
    }

    return [
        'success' => false,
        'message' => $data['message'] ?? 'Verification failed',
    ];
}

/**
 * Legacy helper name — kept for compatibility.
 */
function getFlutterwaveAccessToken() {
    // Not needed for v3 — the secret key is used directly.
    return true;
}

/**
 * Get Flutterwave supported banks (for payouts).
 */
function getFlutterwaveBanks() {
    if (empty(FLUTTERWAVE_SECRET_KEY)) return [];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => FLUTTERWAVE_API_BASE . '/banks/NG',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . FLUTTERWAVE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['data'] ?? [];
}