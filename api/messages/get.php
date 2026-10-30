<?php
// marketplace/api/messages/get.php

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

$conversationId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$userId = Session::getUserId();

try {
    if ($conversationId > 0) {
        // Get conversation with specific user
        $messages = Message::getConversation($userId, $conversationId, $limit);
        
        // Mark messages as read
        $db = Database::getInstance();
        $db->update(
            "UPDATE messages SET is_read = 1 
             WHERE sender_id = ? AND receiver_id = ? AND is_read = 0",
            [$conversationId, $userId]
        );
        
        echo json_encode([
            'success' => true,
            'conversation' => $messages,
            'other_user_id' => $conversationId
        ]);
    } else {
        // Get all conversations
        $conversations = Message::getUserConversations($userId);
        
        echo json_encode([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}