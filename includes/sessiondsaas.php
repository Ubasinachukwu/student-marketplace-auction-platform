<?php
// marketplace/config/session.php

require_once __DIR__ . '/constants.php';

class Session {
    private static $started = false;

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
            
            session_start();
            self::$started = true;
        } elseif (!self::$started) {
            self::$started = true;
        }
    }

    public static function set($key, $value) {
        self::init();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        self::init();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function has($key) {
        self::init();
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        self::init();
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        self::init();
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
        self::$started = false;
    }

    public static function setFlash($key, $message) {
        self::init();
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash($key, $default = null) {
        self::init();
        $message = isset($_SESSION['_flash'][$key]) ? $_SESSION['_flash'][$key] : $default;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }

    public static function hasFlash($key) {
        self::init();
        return isset($_SESSION['_flash'][$key]);
    }

    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function getUserType() {
        return self::get('user_type');
    }

    public static function getUserId() {
        return self::get('user_id');
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }

    public static function requireRole($role) {
        self::requireLogin();
        if (self::getUserType() !== $role) {
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }

    public static function isAdmin() {
        return self::isLoggedIn() && self::getUserType() === USER_TYPE_ADMIN;
    }

    public static function isSeller() {
        return self::isLoggedIn() && self::getUserType() === USER_TYPE_SELLER;
    }

    public static function isBuyer() {
        return self::isLoggedIn() && self::getUserType() === USER_TYPE_BUYER;
    }

    public static function regenerateId() {
        self::init();
        session_regenerate_id(true);
    }

    // =============================================
    // CSRF PROTECTION - FIXED VERSION
    // =============================================

    // Generate CSRF token (keeps existing token if already set)
    public static function generateCSRFToken() {
        self::init();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Verify CSRF token with timing-safe comparison
    public static function verifyCSRFToken($token) {
        self::init();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        // Use hash_equals for timing-safe comparison
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // Get CSRF hidden field
    public static function getCSRFField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    // Regenerate CSRF token (use after login, etc.)
    public static function regenerateCSRFToken() {
        self::init();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    // =============================================
}

// Session helper functions
function session() {
    return Session::class;
}

// Initialize session on every request
Session::init();

// Check if session is expired (inactivity)
if (Session::isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        Session::destroy();
        header('Location: ' . SITE_URL . '/auth/login.php?expired=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();
?>