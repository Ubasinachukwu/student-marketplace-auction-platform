<?php
// marketplace/classes/Message.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Listing.php';
require_once __DIR__ . '/Order.php';

class Message {
    private $db;
    private $id;
    private $senderId;
    private $receiverId;
    private $listingId;
    private $orderId;
    private $subject;
    private $messageText;
    private $isRead;
    private $parentId;
    private $createdAt;
    private $sender;
    private $receiver;
    private $listing;
    private $order;
    private $replies = [];

    public function __construct($messageId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($messageId) {
            $this->load($messageId);
        }
    }

    /**
     * Load message from database
     */
    public function load($messageId) {
        $message = $this->db->fetchOne(
            "SELECT * FROM messages WHERE id = ?",
            [$messageId]
        );
        
        if ($message) {
            $this->id = $message['id'];
            $this->senderId = $message['sender_id'];
            $this->receiverId = $message['receiver_id'];
            $this->listingId = $message['listing_id'];
            $this->orderId = $message['order_id'];
            $this->subject = $message['subject'];
            $this->messageText = $message['message_text'];
            $this->isRead = (bool)$message['is_read'];
            $this->parentId = $message['parent_id'];
            $this->createdAt = $message['created_at'];
            
            // Load related data
            $this->sender = new User($this->senderId);
            $this->receiver = new User($this->receiverId);
            
            if ($this->listingId) {
                $this->listing = new Listing($this->listingId);
            }
            
            if ($this->orderId) {
                $this->order = new Order($this->orderId);
            }
            
            // Load replies
            $this->loadReplies();
            
            return true;
        }
        return false;
    }

    /**
     * Send new message
     */
    public function send($data) {
        $required = ['sender_id', 'receiver_id', 'message_text'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if ($data['sender_id'] == $data['receiver_id']) {
            throw new Exception('Cannot send message to yourself');
        }

        $this->senderId = $data['sender_id'];
        $this->receiverId = $data['receiver_id'];
        $this->messageText = $data['message_text'];
        $this->listingId = $data['listing_id'] ?? null;
        $this->orderId = $data['order_id'] ?? null;
        $this->subject = $data['subject'] ?? null;
        $this->parentId = $data['parent_id'] ?? null;
        $this->isRead = false;

        // Validate receiver exists
        $receiver = $this->db->fetchOne(
            "SELECT id FROM users WHERE id = ?",
            [$this->receiverId]
        );
        if (!$receiver) {
            throw new Exception('Receiver not found');
        }

        // Validate listing if provided
        if ($this->listingId) {
            $listing = $this->db->fetchOne(
                "SELECT id FROM listings WHERE id = ?",
                [$this->listingId]
            );
            if (!$listing) {
                throw new Exception('Listing not found');
            }
        }

        // Validate order if provided
        if ($this->orderId) {
            $order = $this->db->fetchOne(
                "SELECT id FROM orders WHERE id = ?",
                [$this->orderId]
            );
            if (!$order) {
                throw new Exception('Order not found');
            }
        }

        // Insert message
        $this->id = $this->db->insert(
            "INSERT INTO messages (sender_id, receiver_id, listing_id, order_id, subject, message_text, parent_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $this->senderId,
                $this->receiverId,
                $this->listingId,
                $this->orderId,
                $this->subject,
                $this->messageText,
                $this->parentId
            ]
        );

        // Create notification
        $notificationTitle = 'New Message';
        if ($this->orderId) {
            $order = $this->db->fetchOne("SELECT order_number FROM orders WHERE id = ?", [$this->orderId]);
            $notificationTitle = 'New Message about Order #' . $order['order_number'];
        } elseif ($this->listingId) {
            $listing = $this->db->fetchOne("SELECT title FROM listings WHERE id = ?", [$this->listingId]);
            $notificationTitle = 'New Message about "' . $listing['title'] . '"';
        }

        // =============================================
        // FIX: Use SITE_URL for notification link
        // =============================================
        $notificationLink = SITE_URL . '/messages.php';

        $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
             VALUES (?, 'message', ?, ?, ?, NOW())",
            [
                $this->receiverId,
                $notificationTitle,
                substr($this->messageText, 0, 100) . (strlen($this->messageText) > 100 ? '...' : ''),
                $notificationLink
            ]
        );

        return $this->id;
    }

    /**
     * Reply to message
     */
    public function reply($senderId, $messageText) {
        if (!$this->id) {
            throw new Exception('Original message not loaded');
        }

        $replyData = [
            'sender_id' => $senderId,
            'receiver_id' => $this->senderId == $senderId ? $this->receiverId : $this->senderId,
            'message_text' => $messageText,
            'listing_id' => $this->listingId,
            'order_id' => $this->orderId,
            'subject' => 'Re: ' . ($this->subject ?? 'Message'),
            'parent_id' => $this->id
        ];

        return $this->send($replyData);
    }

    /**
     * Load replies
     */
    private function loadReplies() {
        $replies = $this->db->fetchAll(
            "SELECT * FROM messages WHERE parent_id = ? ORDER BY created_at ASC",
            [$this->id]
        );
        
        $this->replies = [];
        foreach ($replies as $reply) {
            $msg = new Message();
            $msg->load($reply['id']);
            $this->replies[] = $msg;
        }
    }

    /**
     * Mark as read
     */
    public function markAsRead() {
        if (!$this->id) {
            throw new Exception('Message not loaded');
        }

        return $this->db->update(
            "UPDATE messages SET is_read = 1 WHERE id = ?",
            [$this->id]
        );
    }

    /**
     * Get conversation thread
     */
    public static function getThread($messageId) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        $message = $db->fetchOne(
            "SELECT * FROM messages WHERE id = ?",
            [$messageId]
        );
        
        if (!$message) {
            return null;
        }

        $thread = [];
        
        // Get root message
        $rootId = $message['parent_id'] ?? $message['id'];
        $root = $db->fetchOne(
            "SELECT * FROM messages WHERE id = ?",
            [$rootId]
        );
        
        if ($root) {
            $msg = new Message();
            $msg->load($root['id']);
            $thread[] = $msg;
        }

        // Get all replies
        $replies = $db->fetchAll(
            "SELECT * FROM messages WHERE parent_id = ? ORDER BY created_at ASC",
            [$rootId]
        );
        
        foreach ($replies as $reply) {
            $msg = new Message();
            $msg->load($reply['id']);
            $thread[] = $msg;
        }

        return $thread;
    }

    /**
     * Get conversation between two users
     */
    public static function getConversation($user1Id, $user2Id, $limit = 50) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        return $db->fetchAll(
            "SELECT * FROM messages 
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at ASC
             LIMIT ?",
            [$user1Id, $user2Id, $user2Id, $user1Id, $limit]
        );
    }

    /**
     * Get user's conversations
     */
    public static function getUserConversations($userId) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        return $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id
                    ELSE m.sender_id
                END as other_user_id,
                u.full_name as other_user_name,
                u.email as other_user_email,
                u.profile_pic as other_user_profile_pic,
                u.user_type as other_user_type,
                COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 END) as unread_count,
                MAX(m.created_at) as last_message_time,
                (SELECT message_text FROM messages 
                 WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) 
                 ORDER BY created_at DESC LIMIT 1) as last_message
            FROM messages m
            JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            GROUP BY other_user_id
            ORDER BY last_message_time DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
    }

    /**
     * Get message data as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'sender_id' => $this->senderId,
            'sender' => $this->sender ? $this->sender->toArray() : null,
            'receiver_id' => $this->receiverId,
            'receiver' => $this->receiver ? $this->receiver->toArray() : null,
            'listing_id' => $this->listingId,
            'listing' => $this->listing ? $this->listing->toArray() : null,
            'order_id' => $this->orderId,
            'order' => $this->order ? $this->order->toArray() : null,
            'subject' => $this->subject,
            'message_text' => $this->messageText,
            'is_read' => $this->isRead,
            'parent_id' => $this->parentId,
            'created_at' => $this->createdAt,
            'replies' => array_map(function($msg) { return $msg->toArray(); }, $this->replies)
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getSenderId() { return $this->senderId; }
    public function getReceiverId() { return $this->receiverId; }
    public function getListingId() { return $this->listingId; }
    public function getOrderId() { return $this->orderId; }
    public function getSubject() { return $this->subject; }
    public function getMessageText() { return $this->messageText; }
    public function isRead() { return $this->isRead; }
    public function getParentId() { return $this->parentId; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getSender() { return $this->sender; }
    public function getReceiver() { return $this->receiver; }
    public function getListing() { return $this->listing; }
    public function getOrder() { return $this->order; }
    public function getReplies() { return $this->replies; }
}