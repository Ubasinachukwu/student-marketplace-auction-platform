<?php
// marketplace/buyer/reviews.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Get order ID for review
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$reviewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/buyer/reviews.php');
    }

    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';
    
    if ($action === 'submit') {
        $orderId = (int)$_POST['order_id'];
        $rating = (int)$_POST['rating'];
        $reviewText = sanitize($_POST['review_text'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            setError('Please select a valid rating');
            redirect(SITE_URL . '/buyer/reviews.php?order_id=' . $orderId);
        }
        
        if (empty($reviewText)) {
            setError('Please write a review');
            redirect(SITE_URL . '/buyer/reviews.php?order_id=' . $orderId);
        }
        
        try {
            // Get order details
            $order = $db->fetchOne(
                "SELECT * FROM orders WHERE id = ? AND buyer_id = ? AND order_status = 'completed'",
                [$orderId, $userId]
            );
            
            if (!$order) {
                throw new Exception('Order not found or not completed');
            }
            
            // Check if already reviewed
            $existing = $db->fetchOne(
                "SELECT id FROM reviews WHERE order_id = ? AND reviewer_id = ?",
                [$orderId, $userId]
            );
            
            if ($existing) {
                throw new Exception('You have already reviewed this order');
            }
            
            $db->beginTransaction();
            
            // Insert review
            $db->insert(
                "INSERT INTO reviews (order_id, reviewer_id, target_user_id, rating, review_text, review_type) 
                 VALUES (?, ?, ?, ?, ?, 'buyer_to_seller')",
                [$orderId, $userId, $order['seller_id'], $rating, $reviewText]
            );
            
            // Update seller rating
            $avgRating = $db->fetchOne(
                "SELECT AVG(rating) as avg_rating FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller'",
                [$order['seller_id']]
            );
            
            $db->update(
                "UPDATE seller_profiles SET rating_avg = ? WHERE user_id = ?",
                [$avgRating['avg_rating'] ?? 0, $order['seller_id']]
            );
            
            $db->commit();
            
            setSuccess('Thank you for your review!');
            redirect(SITE_URL . '/buyer/orders.php');
            
        } catch (Exception $e) {
            $db->rollback();
            setError($e->getMessage());
            error_log('Review Error: ' . $e->getMessage());
            redirect(SITE_URL . '/buyer/reviews.php?order_id=' . $orderId);
        }
    } elseif ($action === 'edit') {
        // Edit review
        $reviewId = (int)$_POST['review_id'];
        $rating = (int)$_POST['rating'];
        $reviewText = sanitize($_POST['review_text'] ?? '');
        
        try {
            $db->beginTransaction();
            
            $db->update(
                "UPDATE reviews SET rating = ?, review_text = ? WHERE id = ? AND reviewer_id = ?",
                [$rating, $reviewText, $reviewId, $userId]
            );
            
            // Recalculate seller rating
            $review = $db->fetchOne("SELECT target_user_id FROM reviews WHERE id = ?", [$reviewId]);
            if ($review) {
                $avgRating = $db->fetchOne(
                    "SELECT AVG(rating) as avg_rating FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller'",
                    [$review['target_user_id']]
                );
                $db->update(
                    "UPDATE seller_profiles SET rating_avg = ? WHERE user_id = ?",
                    [$avgRating['avg_rating'] ?? 0, $review['target_user_id']]
                );
            }
            
            $db->commit();
            
            setSuccess('Review updated successfully!');
            redirect(SITE_URL . '/buyer/orders.php');
            
        } catch (Exception $e) {
            $db->rollback();
            setError($e->getMessage());
            error_log('Review Edit Error: ' . $e->getMessage());
        }
    }
}

// Get order details for review
$order = null;
$existingReview = null;

if ($orderId > 0) {
    $order = $db->fetchOne(
        "SELECT o.*, l.title as listing_title, l.id as listing_id,
                u.full_name as seller_name,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as listing_image
         FROM orders o
         JOIN listings l ON o.listing_id = l.id
         JOIN users u ON o.seller_id = u.id
         WHERE o.id = ? AND o.buyer_id = ? AND o.order_status = 'completed'",
        [$orderId, $userId]
    );
    
    if ($order) {
        $existingReview = $db->fetchOne(
            "SELECT * FROM reviews WHERE order_id = ? AND reviewer_id = ?",
            [$orderId, $userId]
        );
    }
}

// Get user's reviews
$userReviews = $db->fetchAll(
    "SELECT r.*, o.order_number, l.title as listing_title,
            u.full_name as target_name
     FROM reviews r
     JOIN orders o ON r.order_id = o.id
     JOIN listings l ON o.listing_id = l.id
     JOIN users u ON r.target_user_id = u.id
     WHERE r.reviewer_id = ? AND r.review_type = 'buyer_to_seller'
     ORDER BY r.created_at DESC
     LIMIT 20",
    [$userId]
);

$csrfToken = Session::generateCSRFToken();
$pageJS = 'reviews.js';
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

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-star"></i> My Reviews</h1>
                <span class="text-muted"><?php echo count($userReviews); ?> reviews written</span>
            </div>

            <?php if ($orderId > 0 && $order): ?>
                <!-- Review Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil"></i> 
                            <?php echo $existingReview ? 'Edit Review' : 'Write a Review'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?php echo getImageUrl($order['listing_image'] ?? null, 'listing'); ?>" 
                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div class="ms-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($order['listing_title']); ?></div>
                                <div class="text-muted small">
                                    Seller: <?php echo htmlspecialchars($order['seller_name']); ?>
                                    <br>Order #<?php echo $order['order_number']; ?>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="<?php echo SITE_URL; ?>/buyer/reviews.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="<?php echo $existingReview ? 'edit' : 'submit'; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <?php if ($existingReview): ?>
                                <input type="hidden" name="review_id" value="<?php echo $existingReview['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Rating <span class="text-danger">*</span></label>
                                <div class="rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" 
                                               name="rating" 
                                               id="star<?php echo $i; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo ($existingReview && $existingReview['rating'] == $i) ? 'checked' : ''; ?>
                                               required>
                                        <label for="star<?php echo $i; ?>" class="star-label">
                                            <i class="bi bi-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="review_text" class="form-label">Review <span class="text-danger">*</span></label>
                                <textarea class="form-control" 
                                          id="review_text" 
                                          name="review_text" 
                                          rows="4" 
                                          required
                                          placeholder="Share your experience with this seller..."><?php echo $existingReview ? htmlspecialchars($existingReview['review_text']) : ''; ?></textarea>
                                <small class="text-muted">Minimum 10 characters. Be honest and helpful.</small>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> 
                                    <?php echo $existingReview ? 'Update Review' : 'Submit Review'; ?>
                                </button>
                                <a href="<?php echo SITE_URL; ?>/buyer/orders.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to Orders
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- My Reviews List -->
            <?php if (!empty($userReviews)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-list"></i> My Reviews</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($userReviews as $review): ?>
                            <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px;">
                                        <span class="display-6 text-muted">
                                            <?php echo strtoupper(substr($review['target_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($review['target_name']); ?></div>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($review['listing_title']); ?>
                                                <span class="mx-1">•</span>
                                                Order #<?php echo $review['order_number']; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="mt-1"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></div>
                                    <div class="text-muted small mt-1">
                                        <?php echo timeAgo($review['created_at']); ?>
                                        <?php if ($review['updated_at'] != $review['created_at']): ?>
                                            <span class="text-muted">(edited)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>/buyer/reviews.php?order_id=<?php echo $review['order_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input .star-label {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input .star-label:hover,
.rating-input .star-label:hover ~ .star-label,
.rating-input input[type="radio"]:checked ~ .star-label {
    color: #ffc107;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>