<?php
// marketplace/buyer/messages.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Get parameters
$conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$withUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;
$listingId = isset($_GET['listing']) ? (int)$_GET['listing'] : 0;
$newMessage = isset($_GET['new']) ? true : false;

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/buyer/messages.php');
    }

    $receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $messageText = sanitize($_POST['message'] ?? '');
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

    if (empty($messageText)) {
        setError('Message cannot be empty');
        redirect(SITE_URL . '/buyer/messages.php' . ($conversationId ? '?id=' . $conversationId : ''));
    }

    if ($receiverId == $userId) {
        setError('You cannot message yourself');
        redirect(SITE_URL . '/buyer/messages.php');
    }

    try {
        $db->beginTransaction();

        // Send message
        $db->insert(
            "INSERT INTO messages (sender_id, receiver_id, listing_id, order_id, message_text) 
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $receiverId, $listingId ?: null, $orderId ?: null, $messageText]
        );

        // Mark previous messages as read
        $db->update(
            "UPDATE messages SET is_read = 1 
             WHERE sender_id = ? AND receiver_id = ? AND is_read = 0",
            [$receiverId, $userId]
        );

        $db->commit();

        setSuccess('Message sent successfully!');
        redirect(SITE_URL . '/buyer/messages.php');

    } catch (Exception $e) {
        $db->rollback();
        setError('Failed to send message: ' . $e->getMessage());
        error_log('Message Error: ' . $e->getMessage());
        redirect(SITE_URL . '/buyer/messages.php');
    }
}

// Get conversation threads
$threads = $db->fetchAll(
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
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT listing_id FROM messages 
         WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) 
         ORDER BY created_at DESC LIMIT 1) as last_listing_id,
        (SELECT order_id FROM messages 
         WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) 
         ORDER BY created_at DESC LIMIT 1) as last_order_id
    FROM messages m
    JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC",
    [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
);

// Get conversation messages
$conversation = null;
$messages = [];
$otherUser = null;

if ($conversationId > 0) {
    // Get other user from thread
    foreach ($threads as $thread) {
        if ($thread['other_user_id'] == $conversationId) {
            $otherUser = $thread;
            break;
        }
    }
    
    if ($otherUser) {
        // Mark messages as read
        $db->update(
            "UPDATE messages SET is_read = 1 
             WHERE sender_id = ? AND receiver_id = ? AND is_read = 0",
            [$conversationId, $userId]
        );
        
        // Get conversation messages
        $messages = $db->fetchAll(
            "SELECT m.*, 
                    u.full_name as sender_name,
                    u.user_type as sender_type,
                    u.profile_pic as sender_profile_pic,
                    l.title as listing_title
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             LEFT JOIN listings l ON m.listing_id = l.id
             WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC",
            [$userId, $conversationId, $conversationId, $userId]
        );
    }
} elseif ($withUser > 0) {
    // Start new conversation
    $otherUser = $db->fetchOne(
        "SELECT id as other_user_id, full_name as other_user_name, email as other_user_email, 
                profile_pic as other_user_profile_pic, user_type as other_user_type
         FROM users WHERE id = ?",
        [$withUser]
    );
    
    if ($otherUser) {
        // Get any existing messages
        $messages = $db->fetchAll(
            "SELECT m.*, 
                    u.full_name as sender_name,
                    u.user_type as sender_type,
                    u.profile_pic as sender_profile_pic,
                    l.title as listing_title
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             LEFT JOIN listings l ON m.listing_id = l.id
             WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC",
            [$userId, $withUser, $withUser, $userId]
        );
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'messages.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="row">
                <!-- Messages Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-chat"></i> Conversations</h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <?php if (empty($threads)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-chat display-4 text-muted"></i>
                                    <p class="text-muted">No conversations yet</p>
                                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-sm btn-primary">Browse Items</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($threads as $thread): ?>
                                    <a href="<?php echo SITE_URL; ?>/buyer/messages.php?id=<?php echo $thread['other_user_id']; ?>" 
                                       class="text-decoration-none">
                                        <div class="d-flex align-items-center p-3 border-bottom <?php echo ($conversationId == $thread['other_user_id']) ? 'bg-light' : ''; ?>">
                                            <div class="flex-shrink-0">
                                                <img src="<?php echo getProfileImageUrl($thread['other_user_profile_pic'] ?? null); ?>" 
                                                     class="rounded-circle" 
                                                     style="width: 45px; height: 45px; object-fit: cover;">
                                                <?php if ($thread['unread_count'] > 0): ?>
                                                    <span class="badge bg-danger position-absolute" 
                                                          style="margin-left: -10px; margin-top: -5px;">
                                                        <?php echo $thread['unread_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-3 flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($thread['other_user_name']); ?></div>
                                                    <small class="text-muted"><?php echo timeAgo($thread['last_message_time']); ?></small>
                                                </div>
                                                <div class="text-truncate text-muted small">
                                                    <?php echo htmlspecialchars($thread['last_message'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Conversation -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <?php if ($otherUser): ?>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getProfileImageUrl($otherUser['other_user_profile_pic'] ?? null); ?>" 
                                         class="rounded-circle" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                    <div class="ms-3">
                                        <div class="fw-bold"><?php echo htmlspecialchars($otherUser['other_user_name']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo ucfirst($otherUser['other_user_type']); ?>
                                            <?php if ($otherUser['last_order_id'] ?? 0): ?>
                                                <span class="mx-1">•</span>
                                                Order #<?php 
                                                    $orderNum = $db->fetchOne("SELECT order_number FROM orders WHERE id = ?", [$otherUser['last_order_id']]);
                                                    echo $orderNum['order_number'] ?? '';
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <h5 class="mb-0">
                                    <i class="bi bi-chat"></i> 
                                    <?php echo $newMessage ? 'New Message' : 'Select a Conversation'; ?>
                                </h5>
                            <?php endif; ?>
                        </div>

                        <div class="card-body" style="min-height: 400px;">
                            <?php if ($otherUser): ?>
                                <!-- Messages -->
                                <div class="messages-container" style="max-height: 400px; overflow-y: auto; padding: 10px;">
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-chat display-4 text-muted"></i>
                                            <p class="text-muted">No messages yet. Start the conversation!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="d-flex mb-3 <?php echo $msg['sender_id'] == $userId ? 'justify-content-end' : ''; ?>">
                                                <?php if ($msg['sender_id'] != $userId): ?>
                                                    <div class="flex-shrink-0 me-2">
                                                        <img src="<?php echo getProfileImageUrl($msg['sender_profile_pic'] ?? null); ?>" 
                                                             class="rounded-circle" 
                                                             style="width: 35px; height: 35px; object-fit: cover;">
                                                    </div>
                                                <?php endif; ?>
                                                <div style="max-width: 70%;">
                                                    <div class="card <?php echo $msg['sender_id'] == $userId ? 'bg-primary text-white' : 'bg-light'; ?>">
                                                        <div class="card-body p-2">
                                                            <?php if ($msg['listing_title']): ?>
                                                                <small class="<?php echo $msg['sender_id'] == $userId ? 'text-light opacity-75' : 'text-muted'; ?>">
                                                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($msg['listing_title']); ?>
                                                                </small>
                                                                <br>
                                                            <?php endif; ?>
                                                            <div><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                                            <small class="<?php echo $msg['sender_id'] == $userId ? 'text-light opacity-75' : 'text-muted'; ?>">
                                                                <?php echo timeAgo($msg['created_at']); ?>
                                                                <?php if ($msg['is_read'] && $msg['sender_id'] != $userId): ?>
                                                                    <i class="bi bi-check-all"></i> Read
                                                                <?php elseif ($msg['sender_id'] != $userId): ?>
                                                                    <i class="bi bi-check"></i> Sent
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($msg['sender_id'] == $userId): ?>
                                                    <div class="flex-shrink-0 ms-2">
                                                        <img src="<?php echo getProfileImageUrl($msg['sender_profile_pic'] ?? null); ?>" 
                                                             class="rounded-circle" 
                                                             style="width: 35px; height: 35px; object-fit: cover;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Reply Form -->
                                <form method="POST" action="<?php echo SITE_URL; ?>/buyer/messages.php" class="mt-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="receiver_id" value="<?php echo $otherUser['other_user_id']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">

                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               name="message" 
                                               placeholder="Type a message..." 
                                               required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send"></i> Send
                                        </button>
                                    </div>
                                </form>

                            <?php elseif ($newMessage): ?>
                                <!-- New Message Form -->
                                <form method="POST" action="<?php echo SITE_URL; ?>/buyer/messages.php" class="mt-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="receiver_id" class="form-label">Recipient <span class="text-danger">*</span></label>
                                        <select class="form-select" id="receiver_id" name="receiver_id" required>
                                            <option value="">Select user...</option>
                                            <?php 
                                            $users = $db->fetchAll(
                                                "SELECT id, full_name, email, user_type FROM users WHERE id != ? ORDER BY full_name",
                                                [$userId]
                                            );
                                            foreach ($users as $user): 
                                            ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                        <?php echo ($withUser == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['full_name']); ?> 
                                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                                    [<?php echo ucfirst($user['user_type']); ?>]
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="listing_id" class="form-label">Related Listing (Optional)</label>
                                        <select class="form-select" id="listing_id" name="listing_id">
                                            <option value="0">No listing</option>
                                            <?php 
                                            $listings = $db->fetchAll(
                                                "SELECT id, title FROM listings WHERE seller_id = ? OR buyer_id = ?",
                                                [$userId, $userId]
                                            );
                                            foreach ($listings as $listing): 
                                            ?>
                                                <option value="<?php echo $listing['id']; ?>"
                                                        <?php echo ($listingId == $listing['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($listing['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send"></i> Send Message
                                        </button>
                                        <a href="<?php echo SITE_URL; ?>/buyer/messages.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>

                            <?php else: ?>
                                <!-- No Conversation Selected -->
                                <div class="text-center py-5">
                                    <i class="bi bi-chat display-1 text-muted"></i>
                                    <h4 class="mt-3">Your Messages</h4>
                                    <p class="text-muted">Select a conversation from the left or start a new one.</p>
                                    <a href="<?php echo SITE_URL; ?>/buyer/messages.php?new=1" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> New Message
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to bottom of messages
    const container = document.querySelector('.messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>