<?php
// marketplace/config/paystack-helper.php
// Paystack Payment Helper (Hosted Redirect Flow)

/**
 * Initialize a Paystack transaction.
 * Returns authorization_url for hosted checkout.
 */
function initializePaystackPayment($paymentData) {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['success' => false, 'message' => 'Paystack secret key missing'];
    }

    $reference = 'PSK-' . time() . '-' . rand(1000, 9999);

    $payload = [
        'email'        => $paymentData['customer_email'],
        'amount'       => (int)round($paymentData['amount'] * 100), // kobo
        'currency'     => 'NGN',
        'reference'    => $reference,
        'callback_url' => $paymentData['redirect_url'],
        'metadata'     => [
            'order_id'    => $paymentData['order_id'],
            'user_id'     => $paymentData['user_id'],
            'description' => $paymentData['description'] ?? 'Order Payment',
            'custom_fields' => [
                [
                    'display_name'  => 'Order Number',
                    'variable_name' => 'order_number',
                    'value'         => $paymentData['order_number'] ?? '',
                ],
            ],
        ],
    ];

    $response = paystackRequest('POST', '/transaction/initialize', $payload);

    if ($response['success'] && !empty($response['data']['authorization_url'])) {
        return [
            'success'     => true,
            'link'        => $response['data']['authorization_url'],
            'reference'   => $reference,
            'access_code' => $response['data']['access_code'] ?? '',
        ];
    }

    return [
        'success' => false,
        'message' => $response['message'] ?? 'Paystack initialization failed',
    ];
}

/**
 * Verify a Paystack transaction by reference.
 */
function verifyPaystackTransaction($reference) {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['success' => false, 'message' => 'Paystack secret key missing'];
    }

    $response = paystackRequest('GET', '/transaction/verify/' . urlencode($reference));

    if ($response['success'] && !empty($response['data'])) {
        $data = $response['data'];
        return [
            'success'        => true,
            'status'         => $data['status'] ?? 'unknown',
            'amount'         => ($data['amount'] ?? 0) / 100, // kobo → Naira
            'currency'       => $data['currency'] ?? 'NGN',
            'reference'      => $data['reference'] ?? $reference,
            'transaction_id' => $data['id'] ?? $reference,
            'payment_type'   => $data['channel'] ?? 'card',
            'customer'       => $data['customer'] ?? [],
            'data'           => $data,
        ];
    }

    return [
        'success' => false,
        'message' => $response['message'] ?? 'Paystack verification failed',
    ];
}

/**
 * Get Paystack supported banks.
 */
function getPaystackBanks() {
    $response = paystackRequest('GET', '/bank?country=nigeria');
    return $response['success'] ? ($response['data'] ?? []) : [];
}

/**
 * Generic Paystack API request helper.
 */
function paystackRequest($method, $endpoint, $payload = null) {
    $url = rtrim(PAYSTACK_API_BASE, '/') . $endpoint;

    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];

    $ch = curl_init();
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Paystack Request Error: ' . $error);
        return ['success' => false, 'message' => $error];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['status'])) {
        return [
            'success' => true,
            'data'    => $data['data'] ?? $data,
            'message' => $data['message'] ?? 'OK',
        ];
    }

    $msg = $data['message'] ?? ('HTTP ' . $httpCode);
    error_log('Paystack API Error (' . $endpoint . '): ' . $response);
    return ['success' => false, 'message' => $msg];
}