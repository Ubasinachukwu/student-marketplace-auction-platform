<?php
// marketplace/api/admin/users.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Send JSON header FIRST — before any redirect can be attempted
header('Content-Type: application/json');

// Admin check — inline, JSON-safe (no redirects)
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if (!Session::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// CSRF
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db      = db();
$adminId = Session::getUserId();
$action  = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$userId  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$userIds = isset($_POST['user_ids']) ? explode(',', (string)$_POST['user_ids']) : [];

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

$allowedActions = ['suspend', 'activate', 'verify', 'delete'];

/**
 * Perform a single user action inside an existing transaction.
 */
function performUserAction($db, $action, $userId, $adminId) {
    $user = $db->fetchOne(
        "SELECT id, email, full_name, user_type, is_active, email_verified FROM users WHERE id = ?",
        [$userId]
    );

    if (!$user) {
        throw new Exception('User not found');
    }

    if ($user['user_type'] === 'admin' && $action !== 'verify') {
        throw new Exception('Cannot modify admin users');
    }

    switch ($action) {
        case 'suspend':
            $db->update("UPDATE users SET is_active = 0 WHERE id = ?", [$userId]);
            $message = "User {$user['full_name']} has been suspended";
            break;

        case 'activate':
            $db->update("UPDATE users SET is_active = 1 WHERE id = ?", [$userId]);
            $message = "User {$user['full_name']} has been activated";
            break;

        case 'verify':
            $db->update(
                "UPDATE users SET email_verified = 1, verification_code = NULL WHERE id = ?",
                [$userId]
            );
            $db->update(
                "UPDATE seller_profiles SET is_verified = 1 WHERE user_id = ?",
                [$userId]
            );
            $message = "User {$user['full_name']} has been verified";
            break;

        case 'delete':
            if ($user['user_type'] === 'admin') {
                throw new Exception('Cannot delete admin users');
            }
            $db->delete("DELETE FROM users WHERE id = ?", [$userId]);
            $message = "User {$user['full_name']} has been deleted";
            break;

        default:
            throw new Exception('Invalid action');
    }

    if (function_exists('logActivity')) {
        logActivity($adminId, 'admin_' . $action, "Admin action '{$action}' on user #{$userId}");
    }

    return $message;
}

try {
    // ---------- SINGLE ----------
    if ($userId > 0) {
        $db->beginTransaction();
        $message = performUserAction($db, $action, $userId, $adminId);
        $db->commit();

        echo json_encode(['success' => true, 'message' => $message]);
        exit();
    }

    // ---------- BULK ----------
    if (!empty($userIds)) {
        if (!in_array($action, $allowedActions, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid bulk action']);
            exit();
        }

        $db->beginTransaction();
        $successCount = 0;
        $lastError    = '';

        foreach ($userIds as $rawId) {
            $id = (int)$rawId;
            if ($id <= 0) continue;

            try {
                performUserAction($db, $action, $id, $adminId);
                $successCount++;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        $db->commit();

        $msg = "{$action} completed for {$successCount} user(s)";
        if ($lastError) $msg .= ". Last skipped: {$lastError}";

        echo json_encode(['success' => true, 'message' => $msg]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'No user specified']);

} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin users API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}