<?php
// marketplace/config/session.php

require_once __DIR__ . '/constants.php';

class Session {
    private static $started = false;
    private static $userCache = null;

    public static function init() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        ini_set('session.cookie_lifetime', SESSION_TIMEOUT);  // NEW — makes cookie last as long as session
        ini_set('session.gc_probability', 1);                   // NEW — limits gc runs
        ini_set('session.gc_divisor', 100);                     // NEW — 1% chance per request

        session_start();
        self::$started = true;
    } elseif (!self::$started) {
        self::$started = true;
    }
}

    public static function set($key, $value) {
        self::init();
        $_SESSION[$key] = $value;
        self::$userCache = null;
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
        self::$userCache = null;
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
        self::$userCache = null;
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

    // =============================================
    // API DETECTION (NEW)
    // =============================================
    /**
     * Is the current request an API call?
     * Used to avoid sending HTML redirects to JSON endpoints.
     */
    public static function isApiRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/api/') !== false;
    }

    /**
     * Send a JSON error and exit — for API requests.
     */
    private static function apiError($httpCode, $message) {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }

    /**
     * Load the current user's row from DB (cached per request).
     */
    private static function loadUser() {
        if (self::$userCache !== null) {
            return self::$userCache;
        }

        if (!self::isLoggedIn()) {
            self::$userCache = false;
            return false;
        }

        require_once __DIR__ . '/database.php';
        $db = db();
        $user = $db->fetchOne(
            "SELECT id, user_type, is_seller, seller_status, full_name, email
             FROM users WHERE id = ?",
            [self::getUserId()]
        );

        self::$userCache = $user ?: false;
        return self::$userCache;
    }

    public static function isSeller() {
        $user = self::loadUser();
        return $user
            && (int)$user['is_seller'] === 1
            && $user['seller_status'] === 'active';
    }

    public static function isPendingSeller() {
        $user = self::loadUser();
        return $user
            && (int)$user['is_seller'] === 1
            && $user['seller_status'] === 'pending';
    }

    public static function getSellerStatus() {
        $user = self::loadUser();
        return $user ? ($user['seller_status'] ?? 'none') : 'none';
    }

    public static function getUser() {
        return self::loadUser();
    }

    // =============================================
    // ACCESS GUARDS (API-AWARE)
    // =============================================

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            if (self::isApiRequest()) {
                self::apiError(401, 'Authentication required');
            }
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }

    /**
     * Legacy role check. Still works for 'admin' and 'buyer'.
     * For 'seller', use requireSeller() instead.
     */
    public static function requireRole($role) {
        self::requireLogin();

        if ($role === 'seller') {
            self::requireSeller();
            return;
        }

        if (self::getUserType() !== $role) {
            if (self::isApiRequest()) {
                self::apiError(403, 'Access denied');
            }
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }

    public static function requireSeller() {
        self::requireLogin();

        if (self::isSeller()) {
            return;
        }

        $status = self::getSellerStatus();

        if (self::isApiRequest()) {
            self::apiError(403, 'Seller access required');
        }

        if ($status === 'pending') {
            setError('Your seller account is still pending admin approval.');
            header('Location: ' . SITE_URL . '/buyer/dashboard.php');
            exit();
        }
        if ($status === 'rejected') {
            setError('Your seller application was not approved. Please contact support.');
            header('Location: ' . SITE_URL . '/buyer/dashboard.php');
            exit();
        }

        setInfo('To access seller pages, please apply to become a seller.');
        header('Location: ' . SITE_URL . '/buyer/become-seller.php');
        exit();
    }

    public static function isAdmin() {
        return self::isLoggedIn() && self::getUserType() === USER_TYPE_ADMIN;
    }

    public static function isBuyer() {
        return self::isLoggedIn();
    }

    public static function regenerateId() {
        self::init();
        session_regenerate_id(true);
    }

    // =============================================
    // CSRF PROTECTION
    // =============================================

    public static function generateCSRFToken() {
        self::init();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        self::init();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function getCSRFField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    public static function regenerateCSRFToken() {
        self::init();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

// Session helper
function session() {
    return Session::class;
}

Session::init();

// =============================================
// Session timeout check
// Skip entirely for API requests — they handle auth themselves.
// =============================================
if (!Session::isApiRequest() && Session::isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        Session::destroy();
        header('Location: ' . SITE_URL . '/auth/login.php?expired=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();