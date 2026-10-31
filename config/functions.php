<?php
// marketplace/config/functions.php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/location-helper.php';

/// =============================================
// SECURITY FUNCTIONS
// =============================================

/**
 * Sanitize input while preserving the existing project's behaviour.
 * This function trims strings and HTML-escapes them.
 *
 * Note: Use prepared SQL statements for database queries.
 * This function is not SQL injection protection.
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }

    if ($input === null) {
        return '';
    }

    if (!is_scalar($input)) {
        return '';
    }

    return htmlspecialchars(
        trim((string) $input),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Escape a value for safe HTML output.
 */
function escapeHtml($input) {
    if ($input === null) {
        return '';
    }

    if (!is_scalar($input)) {
        return '';
    }

    return htmlspecialchars(
        (string) $input,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Legacy compatibility helper.
 *
 * Do not use this function to secure SQL queries.
 * Use PDO prepared statements instead.
 */
function escape($input) {
    if ($input === null) {
        return '';
    }

    if (!is_scalar($input)) {
        return '';
    }

    return addslashes((string) $input);
}

/**
 * Generate a cryptographically secure random token.
 */
function generateToken($length = 32) {
    $length = (int) $length;

    if ($length < 1 || $length > 128) {
        throw new InvalidArgumentException(
            'Token length must be between 1 and 128.'
        );
    }

    return bin2hex(random_bytes($length));
}

/**
 * Hash a password securely.
 */
function hashPassword($password) {
    return password_hash(
        (string) $password,
        PASSWORD_BCRYPT,
        ['cost' => 12]
    );
}

/**
 * Verify a password against its stored hash.
 */
function verifyPassword($password, $hash) {
    return password_verify((string) $password, (string) $hash);
}

/**
 * Generate a secure random alphanumeric string.
 */
function generateRandomString($length = 10) {
    $length = (int) $length;

    if ($length < 1 || $length > 128) {
        throw new InvalidArgumentException(
            'String length must be between 1 and 128.'
        );
    }

    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $result = '';

    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[random_int(0, $maxIndex)];
    }

    return $result;
}
// =============================================
// URL FUNCTIONS
// =============================================

/**
 * Redirect safely within the application.
 * External redirects are rejected unless they point to this site.
 */
function redirect($path = '') {
    $path = trim((string) $path);

    $siteParts = parse_url(SITE_URL);
    $target = $path;

    // Check whether the supplied destination is an absolute URL.
    if (preg_match('#^https?://#i', $path)) {
        $targetParts = parse_url($path);

        $sameHost = isset($siteParts['host'], $targetParts['host'])
            && strcasecmp($siteParts['host'], $targetParts['host']) === 0;

        $sameScheme = isset($siteParts['scheme'], $targetParts['scheme'])
            && strtolower($siteParts['scheme']) === strtolower($targetParts['scheme']);

        $samePort = ($siteParts['port'] ?? null) === ($targetParts['port'] ?? null);

        if (!$sameHost || !$sameScheme || !$samePort) {
            $target = SITE_URL;
        }
    } else {
        // Reject protocol-relative URLs and unsafe control characters.
        if (
            strpos($path, '//') === 0 ||
            preg_match('/[\r\n]/', $path)
        ) {
            $target = SITE_URL;
        } else {
            $target = base_url($path);
        }
    }

    if (headers_sent()) {
        return false;
    }

    header('Location: ' . $target, true, 302);
    exit();
}

/**
 * Return to the referring page only when it belongs to this site.
 */
function back() {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    if ($referer !== '') {
        $refererParts = parse_url($referer);
        $siteParts = parse_url(SITE_URL);

        $sameHost = isset($refererParts['host'], $siteParts['host'])
            && strcasecmp($refererParts['host'], $siteParts['host']) === 0;

        $sameScheme = isset($refererParts['scheme'], $siteParts['scheme'])
            && strtolower($refererParts['scheme']) === strtolower($siteParts['scheme']);

        $samePort = ($refererParts['port'] ?? null) === ($siteParts['port'] ?? null);

        if ($sameHost && $sameScheme && $samePort) {
            redirect($referer);
        }
    }

    redirect('/');
}

/**
 * Get the current application URL using validated server information.
 */
function currentUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    if (
        $host === '' ||
        !preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host) ||
        preg_match('/[\r\n]/', $requestUri)
    ) {
        return SITE_URL . '/';
    }

    $isHttps = (
        !empty($_SERVER['HTTPS']) &&
        strtolower((string) $_SERVER['HTTPS']) !== 'off'
    );

    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . '://' . $host . $requestUri;
}

/**
 * Build a canonical URL for a listing.
 */
function listingUrl($listing) {
    if (empty($listing['id'])) {
        return SITE_URL . '/buyer/browse.php';
    }

    $id = (int) $listing['id'];

    $slug = !empty($listing['slug'])
        ? (string) $listing['slug']
        : slugify($listing['title'] ?? 'listing');

    return SITE_URL . '/buyer/listing-detail.php?id='
        . $id . '&slug=' . rawurlencode($slug);
}
// =============================================
// DATE & TIME FUNCTIONS
// =============================================

/**
 * Convert a date value into a Unix timestamp.
 * Returns false when the value is empty or invalid.
 */
function parseDateTimestamp($datetime) {
    if (
        $datetime === null ||
        $datetime === '' ||
        !is_scalar($datetime)
    ) {
        return false;
    }

    $timestamp = strtotime((string) $datetime);

    return ($timestamp === false) ? false : $timestamp;
}

/**
 * Format a date safely.
 */
function formatDate($datetime, $format = 'M d, Y') {
    $timestamp = parseDateTimestamp($datetime);

    return ($timestamp === false)
        ? 'N/A'
        : date($format, $timestamp);
}

/**
 * Format a time safely.
 */
function formatTime($datetime, $format = 'h:i A') {
    $timestamp = parseDateTimestamp($datetime);

    return ($timestamp === false)
        ? 'N/A'
        : date($format, $timestamp);
}

/**
 * Format a date and time safely.
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    $timestamp = parseDateTimestamp($datetime);

    return ($timestamp === false)
        ? 'N/A'
        : date($format, $timestamp);
}

/**
 * Return a human-readable time difference.
 */
function timeAgo($datetime) {
    $timestamp = parseDateTimestamp($datetime);

    if ($timestamp === false) {
        return 'Unknown';
    }

    $difference = time() - $timestamp;

    if ($difference < 0) {
        return 'Just now';
    }

    if ($difference < 60) {
        return 'Just now';
    }

    if ($difference < 3600) {
        $minutes = (int) floor($difference / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    if ($difference < 86400) {
        $hours = (int) floor($difference / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    if ($difference < 604800) {
        $days = (int) floor($difference / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    if ($difference < 2592000) {
        $weeks = (int) floor($difference / 604800);
        return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
    }

    if ($difference < 31536000) {
        $months = (int) floor($difference / 2592000);
        return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
    }

    $years = (int) floor($difference / 31536000);

    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
}

/**
 * Return the remaining time until a specified date.
 */
function timeRemaining($endDate) {
    $endTimestamp = parseDateTimestamp($endDate);

    if ($endTimestamp === false) {
        return 'Unknown';
    }

    $difference = $endTimestamp - time();

    if ($difference <= 0) {
        return 'Ended';
    }

    $days = (int) floor($difference / 86400);
    $hours = (int) floor(($difference % 86400) / 3600);
    $minutes = (int) floor(($difference % 3600) / 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h remaining';
    }

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm remaining';
    }

    return $minutes . 'm remaining';
}

/**
 * Check whether a date has expired.
 */
function isExpired($datetime) {
    $timestamp = parseDateTimestamp($datetime);

    if ($timestamp === false) {
        return false;
    }

    return $timestamp < time();
}

// =============================================
// NUMBER & CURRENCY FUNCTIONS
// =============================================

/**
 * Check whether a value is a valid finite number.
 */
function isValidMoneyValue($value) {
    return is_numeric($value) && is_finite((float) $value);
}

/**
 * Format an amount with a currency symbol.
 */
function formatPrice($amount, $currency = '₦') {
    if (!isValidMoneyValue($amount)) {
        $amount = 0;
    }

    if (!is_scalar($currency)) {
        $currency = '₦';
    }

    return (string) $currency . number_format((float) $amount, 2);
}

/**
 * Format an amount without a currency symbol.
 */
function formatPriceRaw($amount) {
    if (!isValidMoneyValue($amount)) {
        $amount = 0;
    }

    return number_format((float) $amount, 2);
}

/**
 * Calculate a percentage fee.
 */
function calculateFee($amount, $percentage) {
    if (
        !isValidMoneyValue($amount) ||
        !isValidMoneyValue($percentage)
    ) {
        return 0.0;
    }

    return ((float) $amount * (float) $percentage) / 100;
}

/**
 * Calculate the amount remaining after deducting a percentage fee.
 */
function calculateNetAmount($amount, $feePercentage) {
    if (
        !isValidMoneyValue($amount) ||
        !isValidMoneyValue($feePercentage)
    ) {
        return 0.0;
    }

    $fee = calculateFee($amount, $feePercentage);

    return (float) $amount - $fee;
}

/**
 * Format an amount in Nigerian Naira.
 */
function formatNaira($amount, $withSymbol = true) {
    if (!isValidMoneyValue($amount)) {
        $amount = 0;
    }

    $formatted = number_format((float) $amount, 2);

    return $withSymbol ? '₦' . $formatted : $formatted;
}
// =============================================
// FILE FUNCTIONS
// =============================================

function uploadFile($file, $targetDir, $allowedExtensions = null, $maxSize = null) {
    if ($allowedExtensions === null) {
        $allowedExtensions = ALLOWED_EXTENSIONS;
    }
    if ($maxSize === null) {
        $maxSize = MAX_FILE_SIZE;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Maximum: ' . ($maxSize / 1024 / 1024) . 'MB'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedExtensions)];
    }
    
    $filename = generateToken(16) . '.' . $extension;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

function deleteFile($path) {
    if (file_exists($path)) {
        return unlink($path);
    }
    return false;
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function getFileSize($path) {
    if (file_exists($path)) {
        return filesize($path);
    }
    return 0;
}

function getImageUrl($filename, $type = 'listing') {
    if (empty($filename)) {
        return SITE_URL . '/assets/images/default-' . $type . '.jpg';
    }
    // External URLs (e.g. https://picsum.photos/...) pass through
    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }
    return SITE_URL . '/assets/uploads/' . $type . 's/' . $filename;
}

function getProfileImageUrl($filename) {
    if (empty($filename)) {
        return SITE_URL . '/assets/images/default-avatar.jpg';
    }
    return SITE_URL . '/assets/uploads/profiles/' . $filename;
}

/**
 * Get the primary image filename for a listing.
 * Returns the filename only (not full URL).
 */
function getListingPrimaryImage($listingId) {
    global $db;
    if (!$db) {
        require_once __DIR__ . '/database.php';
        $db = db();
    }
    $row = $db->fetchOne(
        "SELECT image_url FROM listing_images 
         WHERE listing_id = ? AND is_primary = 1 LIMIT 1",
        [(int)$listingId]
    );
    return $row['image_url'] ?? null;
}

// =============================================
// MISC FUNCTIONS
// =============================================

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

function getRandomColor() {
    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
    return $colors[array_rand($colors)];
}

function getStatusBadgeClass($status) {
    $classes = [
        'draft' => 'secondary',
        'active' => 'success',
        'sold' => 'danger',
        'ended' => 'warning',
        'cancelled' => 'dark',
        'pending' => 'warning',
        'confirmed' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'completed' => 'success',
        'returned' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getConditionLabel($condition) {
    $labels = [
        'new' => 'New',
        'like_new' => 'Like New',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor'
    ];
    return $labels[$condition] ?? ucfirst($condition);
}

function getSaleTypeLabel($type) {
    $labels = [
        'fixed' => 'Fixed Price',
        'auction' => 'Auction',
        'hybrid' => 'Buy Now or Auction'
    ];
    return $labels[$type] ?? ucfirst($type);
}

function generateOrderNumber() {
    return 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function arrayToJson($array) {
    return json_encode($array);
}

function jsonToArray($json) {
    return json_decode($json, true);
}

// Flash message helpers
function setSuccess($message) {
    Session::setFlash('success', $message);
}

function setError($message) {
    Session::setFlash('error', $message);
}

function setWarning($message) {
    Session::setFlash('warning', $message);
}

function setInfo($message) {
    Session::setFlash('info', $message);
}

function displayFlashMessages() {
    $types = ['success', 'error', 'warning', 'info'];
    $output = '';
    foreach ($types as $type) {
        if (Session::hasFlash($type)) {
            $message = Session::getFlash($type);
            $output .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
            $output .= $message;
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
        }
    }
    return $output;
}

function requireLogin() {
    Session::requireLogin();
}

function requireRole($role) {
    Session::requireRole($role);
}

function getCurrentUser() {
    if (!Session::isLoggedIn()) {
        return null;
    }
    return [
        'id' => Session::getUserId(),
        'type' => Session::getUserType(),
        'name' => Session::get('full_name'),
        'email' => Session::get('email')
    ];
}

function paginate($currentPage, $totalPages, $url = '') {
    if ($totalPages <= 1) return '';
    
    $output = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    if ($currentPage > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><span class="page-link">&laquo; Previous</span></li>';
    }
    
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1">1</a></li>';
        if ($start > 2) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $output .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    if ($currentPage < $totalPages) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
    }
    
    $output .= '</ul></nav>';
    return $output;
}

function dd($data) {
    echo '<pre style="background: #f4f4f4; padding: 20px; margin: 20px; border: 1px solid #ccc; border-radius: 5px; max-height: 500px; overflow: auto;">';
    var_dump($data);
    echo '</pre>';
    die();
}

// =============================================
// BASE URL FUNCTIONS
// =============================================

function base_url($path = '') {
    $path = ltrim($path, '/');
    if (empty($path)) {
        return SITE_URL;
    }
    return SITE_URL . '/' . $path;
}

function url($path = '') {
    return base_url($path);
}

// =============================================
// DASHBOARD COUNTER FUNCTIONS
// =============================================

function getUnreadMessagesCount($userId) {
    global $db;
    if (!$db) {
        require_once __DIR__ . '/database.php';
        $db = db();
    }
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM messages 
         WHERE receiver_id = ? AND is_read = 0",
        [$userId]
    );
    return $result['count'] ?? 0;
}

function getNewReviewsCount($userId) {
    global $db;
    if (!$db) {
        require_once __DIR__ . '/database.php';
        $db = db();
    }
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM reviews 
         WHERE target_user_id = ? AND is_read = 0",
        [$userId]
    );
    return $result['count'] ?? 0;
}

// =============================================
// CATEGORY ICON FUNCTIONS
// =============================================

function getCategoryIcon($slug) {
    static $icons = null;
    
    if ($icons === null) {
        $iconFile = __DIR__ . '/category_icons.php';
        if (file_exists($iconFile)) {
            $icons = require $iconFile;
        } else {
            $icons = [];
        }
    }
    
    $slug = strtolower(trim($slug));
    return $icons[$slug] ?? ($icons['default'] ?? 'bi-tag');
}

function getCategoryIconWithFallback($category) {
    if (!empty($category['icon'])) {
        return $category['icon'];
    }
    return getCategoryIcon($category['slug'] ?? '');
}

function getCategoryIconHtml($slug, $classes = '') {
    $icon = getCategoryIcon($slug);
    return '<i class="bi ' . $icon . ' ' . $classes . '"></i>';
}

// =============================================
// ACTIVITY LOGGING
// =============================================

function logActivity($userId, $action, $description = '') {
    global $db;
    if (!$db) {
        require_once __DIR__ . '/database.php';
        $db = db();
    }
    
    try {
        $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'admin_logs'");
        if (!$tableCheck) {
            return false;
        }
        
        $db->insert(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value, ip_address, created_at) 
             VALUES (?, ?, 'user', ?, ?, ?, NOW())",
            [
                $userId,
                $action,
                $userId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log('logActivity failed: ' . $e->getMessage());
        return false;
    }
}