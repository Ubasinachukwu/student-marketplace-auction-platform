<?php
// marketplace/config/constants.php

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env from root directory
loadEnv(__DIR__ . '/../.env');

// Database Constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'marketplace_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Site Constants
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:8080/marketplace');
define('SITE_NAME', getenv('SITE_NAME') ?: 'MarketPlace');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@marketplace.com');

// Email Constants
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'no-reply@marketplace.com');

// =============================================
// PAYMENT GATEWAY CONSTANTS
// =============================================

// =============================================
// FLUTTERWAVE v3 (Nigeria - Primary Gateway)
// =============================================
// ⚠️ Keys MUST be set in .env — no plaintext fallbacks
define('FLUTTERWAVE_PUBLIC_KEY', getenv('FLUTTERWAVE_PUBLIC_KEY') ?: '');
define('FLUTTERWAVE_SECRET_KEY', getenv('FLUTTERWAVE_SECRET_KEY') ?: '');
define('FLUTTERWAVE_ENCRYPTION_KEY', getenv('FLUTTERWAVE_ENCRYPTION_KEY') ?: '');
define('FLUTTERWAVE_ENVIRONMENT', getenv('FLUTTERWAVE_ENVIRONMENT') ?: 'test');
define('FLUTTERWAVE_API_BASE', 'https://api.flutterwave.com/v3');
define('FLUTTERWAVE_SECRET_HASH', getenv('FLUTTERWAVE_SECRET_HASH') ?: '');

// =============================================
// PAYSTACK (Nigeria - Alternative Gateway)
// =============================================
// ⚠️ Keys MUST be set in .env — no plaintext fallbacks
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('PAYSTACK_ENVIRONMENT', getenv('PAYSTACK_ENVIRONMENT') ?: 'test');
define('PAYSTACK_API_BASE', 'https://api.paystack.co');

// =============================================
// LOCAL PAYMENT METHOD STATUS
// =============================================
// Helper to read boolean env values correctly
function envBool($key, $default = false) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return filter_var($val, FILTER_VALIDATE_BOOLEAN);
}

define('ENABLE_PAY_ON_DELIVERY', envBool('ENABLE_PAY_ON_DELIVERY', true));
define('ENABLE_FLUTTERWAVE',     envBool('ENABLE_FLUTTERWAVE', true));
define('ENABLE_PAYSTACK',        envBool('ENABLE_PAYSTACK', true));

// Fee Constants (in Naira)
define('PLATFORM_FEE_PERCENTAGE', getenv('PLATFORM_FEE_PERCENTAGE') ?: 10);
define('AUCTION_LISTING_FEE', getenv('AUCTION_LISTING_FEE') ?: 500.00);
define('RESERVE_PRICE_FEE', getenv('RESERVE_PRICE_FEE') ?: 1000.00);

// Security Constants
define('SALT', getenv('SALT') ?: 'your-random-salt-here');
define('SESSION_TIMEOUT', getenv('SESSION_TIMEOUT') ?: 1800);

/// Application Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('LISTING_IMAGES_PATH', UPLOAD_PATH . 'listings/');
define('LISTING_THUMBNAILS_PATH', UPLOAD_PATH . 'listings/thumbnails/');
define('PROFILE_IMAGES_PATH', UPLOAD_PATH . 'profiles/');

// User Types
define('USER_TYPE_BUYER', 'buyer');
define('USER_TYPE_SELLER', 'seller');
define('USER_TYPE_ADMIN', 'admin');

// Listing Status
define('LISTING_DRAFT', 'draft');
define('LISTING_ACTIVE', 'active');
define('LISTING_SOLD', 'sold');
define('LISTING_ENDED', 'ended');
define('LISTING_CANCELLED', 'cancelled');

// Order Status
define('ORDER_PENDING', 'pending');
define('ORDER_CONFIRMED', 'confirmed');
define('ORDER_SHIPPED', 'shipped');
define('ORDER_DELIVERED', 'delivered');
define('ORDER_COMPLETED', 'completed');
define('ORDER_CANCELLED', 'cancelled');
define('ORDER_RETURNED', 'returned');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_PAID', 'paid');
define('PAYMENT_FAILED', 'failed');
define('PAYMENT_REFUNDED', 'refunded');

// Sale Types
define('SALE_TYPE_FIXED', 'fixed');
define('SALE_TYPE_AUCTION', 'auction');
define('SALE_TYPE_HYBRID', 'hybrid');

// Conditions
define('CONDITION_NEW', 'new');
define('CONDITION_LIKE_NEW', 'like_new');
define('CONDITION_GOOD', 'good');
define('CONDITION_FAIR', 'fair');
define('CONDITION_POOR', 'poor');

// =============================================
// NIGERIAN DELIVERY CARRIERS
// =============================================
define('NG_DELIVERY_CARRIERS', [
    'GIG Logistics'         => 'GIG Logistics (GIGL)',
    'Kwik Delivery'         => 'Kwik Delivery',
    'Sendbox'               => 'Sendbox',
    'Buddy'                 => 'Buddy by Kwik',
    'DHL Nigeria'           => 'DHL Nigeria',
    'FedEx Nigeria'         => 'FedEx Nigeria',
    'UPS Nigeria'           => 'UPS Nigeria',
    'EMS Nigeria'           => 'EMS Nigeria (NIPOST)',
    'NIPOST'                => 'NIPOST',
    'Red Star Express'      => 'Red Star Express',
    'Courier Plus'          => 'Courier Plus',
    'Speedaf'               => 'Speedaf Express',
    'ABC Cargo'             => 'ABC Cargo',
    'Tranex'                => 'Tranex Logistics',
    'Jumia Logistics'       => 'Jumia Logistics',
    'Chisco Transport'      => 'Chisco Transport',
    'GUO Transport'         => 'GUO Transport',
    'ABC Transport'         => 'ABC Transport',
    'The Young Shall Grow'  => 'The Young Shall Grow',
    'Libra Motors'          => 'Libra Motors',
    'Ekeson'                => 'Ekeson',
    'Peace Mass Transit'    => 'Peace Mass Transit',
    'God Is Good Logistics' => 'God Is Good Logistics (GIG)',
    'Other'                 => 'Other (specify below)',
]);

// Pagination
define('ITEMS_PER_PAGE', 20);
define('ADMIN_ITEMS_PER_PAGE', 50);

// File Upload Limits
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_IMAGES_PER_LISTING', 10);

// Auction Defaults
define('DEFAULT_AUCTION_DURATION_DAYS', 7);
define('AUTO_EXTEND_MINUTES', 2);
define('PAYMENT_WINDOW_HOURS', 48);