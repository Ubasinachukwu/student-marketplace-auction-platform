<?php
// marketplace/api/messages/mark-read.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Message.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

if (!$messageId) {
    echo json_encode(['success' => false, 'message' => 'Message ID is required']);
    exit();
}

try {
    $message = new Message($messageId);
    
    if (!$message->getId()) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit();
    }

    $userId = Session::getUserId();

    // Check if user is the receiver
    if ($message->getReceiverId() !== $userId) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to mark this message as read']);
        exit();
    }

    $message->markAsRead();

    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}