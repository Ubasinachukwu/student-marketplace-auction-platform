<?php
// marketplace/config/functions.php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/location-helper.php';

// =============================================
// SECURITY FUNCTIONS
// =============================================

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function escape($input) {
    return addslashes($input);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateRandomString($length = 10) {
    return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

// =============================================
// URL FUNCTIONS
// =============================================

function redirect($path = '') {
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . base_url($path));
    }
    exit();
}

function back() {
    if (isset($_SERVER['HTTP_REFERER'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    } else {
        redirect('/');
    }
    exit();
}

function currentUrl() {
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
           $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Build a canonical URL for a listing.
 * Includes the slug for readability and SEO.
 *
 * @param array $listing Listing row (needs 'id', 'slug', 'title')
 * @return string Full URL like /buyer/listing-detail.php?id=42&slug=iphone-13-pro
 */
function listingUrl($listing) {
    if (empty($listing['id'])) {
        return SITE_URL . '/buyer/browse.php';
    }
    $id = (int)$listing['id'];
    $slug = !empty($listing['slug'])
        ? $listing['slug']
        : slugify($listing['title'] ?? 'listing');
    return SITE_URL . '/buyer/listing-detail.php?id=' . $id . '&slug=' . urlencode($slug);
}

// =============================================
// DATE & TIME FUNCTIONS
// =============================================

function formatDate($datetime, $format = 'M d, Y') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function formatTime($datetime, $format = 'g:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } elseif ($diff < 2592000) {
        return floor($diff / 604800) . ' weeks ago';
    } else {
        return date('M d, Y', $time);
    }
}

function timeRemaining($datetime) {
    $now = time();
    $end = strtotime($datetime);
    $diff = $end - $now;
    
    if ($diff <= 0) {
        return 'Ended';
    }
    
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    
    if ($days > 0) {
        return "{$days}d {$hours}h {$minutes}m";
    } elseif ($hours > 0) {
        return "{$hours}h {$minutes}m";
    } elseif ($minutes > 0) {
        return "{$minutes}m {$seconds}s";
    } else {
        return "{$seconds}s";
    }
}

function isExpired($datetime) {
    return strtotime($datetime) < time();
}

// =============================================
// NUMBER & CURRENCY FUNCTIONS
// =============================================

function formatPrice($amount, $currency = '₦') {
    return $currency . number_format($amount, 2);
}

function formatPriceRaw($amount) {
    return number_format($amount, 2);
}

function calculateFee($amount, $percentage) {
    return ($amount * $percentage) / 100;
}

function calculateNetAmount($amount, $feePercentage) {
    $fee = calculateFee($amount, $feePercentage);
    return $amount - $fee;
}

function formatNaira($amount, $withSymbol = true) {
    $formatted = number_format($amount, 2);
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