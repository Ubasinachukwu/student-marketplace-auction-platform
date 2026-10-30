<?php
// marketplace/api/common/notification.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$db = Database::getInstance();
$userId = Session::getUserId();
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'get';

if ($action === 'get') {
    // Get unread notifications
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 20",
        [$userId]
    );

    $unreadCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE user_id = ? AND is_read = 0",
        [$userId]
    );

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount['count'] ?? 0
    ]);

} elseif ($action === 'mark-read') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }

    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

    if ($notificationId > 0) {
        $db->update(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
    } else {
        $db->update(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as read'
    ]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}