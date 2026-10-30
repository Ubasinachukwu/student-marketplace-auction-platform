<?php
// marketplace/seller/reviews.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Get seller rating
$rating = $db->fetchOne(
    "SELECT 
        AVG(rating) as avg_rating,
        COUNT(*) as total_reviews,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM reviews 
    WHERE target_user_id = ? AND review_type = 'buyer_to_seller'",
    [$userId]
);

// Get all reviews
$reviews = $db->fetchAll(
    "SELECT r.*, 
            u.full_name as reviewer_name,
            u.email as reviewer_email,
            u.profile_pic as reviewer_profile_pic,
            o.order_number,
            l.title as listing_title,
            l.id as listing_id
     FROM reviews r
     JOIN users u ON r.reviewer_id = u.id
     JOIN orders o ON r.order_id = o.id
     JOIN listings l ON o.listing_id = l.id
     WHERE r.target_user_id = ? AND r.review_type = 'buyer_to_seller'
     ORDER BY r.created_at DESC",
    [$userId]
);

// Handle review response (if implemented)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/reviews.php');
    }

    $reviewId = (int)$_POST['review_id'];
    $response = sanitize($_POST['response'] ?? '');

    if (empty($response)) {
        setError('Please enter a response');
        redirect(SITE_URL . '/seller/reviews.php');
    }

    try {
        $db->update(
            "UPDATE reviews SET seller_response = ?, response_date = NOW() WHERE id = ? AND target_user_id = ?",
            [$response, $reviewId, $userId]
        );
        setSuccess('Response added successfully');
    } catch (Exception $e) {
        setError('Failed to add response: ' . $e->getMessage());
        error_log('Review Response Error: ' . $e->getMessage());
    }

    redirect(SITE_URL . '/seller/reviews.php');
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'reviews.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-star"></i> Reviews & Ratings</h1>
                <span class="text-muted"><?php echo $rating['total_reviews']; ?> reviews</span>
            </div>

            <!-- Rating Summary -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="display-1 fw-bold text-warning">
                                <?php echo number_format($rating['avg_rating'] ?? 0, 1); ?>
                            </div>
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= ($rating['avg_rating'] ?? 0) ? '-fill text-warning' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="text-muted">Based on <?php echo $rating['total_reviews']; ?> reviews</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span>5 ★</span>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $rating['total_reviews'] > 0 ? ($rating['five_star'] / $rating['total_reviews']) * 100 : 0; ?>%;">
                                                <?php echo $rating['five_star']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span>4 ★</span>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: <?php echo $rating['total_reviews'] > 0 ? ($rating['four_star'] / $rating['total_reviews']) * 100 : 0; ?>%;">
                                                <?php echo $rating['four_star']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span>3 ★</span>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                 style="width: <?php echo $rating['total_reviews'] > 0 ? ($rating['three_star'] / $rating['total_reviews']) * 100 : 0; ?>%;">
                                                <?php echo $rating['three_star']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span>2 ★</span>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                 style="width: <?php echo $rating['total_reviews'] > 0 ? ($rating['two_star'] / $rating['total_reviews']) * 100 : 0; ?>%;">
                                                <?php echo $rating['two_star']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span>1 ★</span>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $rating['total_reviews'] > 0 ? ($rating['one_star'] / $rating['total_reviews']) * 100 : 0; ?>%;">
                                                <?php echo $rating['one_star']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews List -->
            <?php if (empty($reviews)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-star display-1 text-muted"></i>
                    <h3 class="mt-3">No reviews yet</h3>
                    <p class="text-muted">You haven't received any reviews from buyers.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <?php foreach ($reviews as $review): ?>
                            <div class="d-flex align-items-start mb-4 pb-4 border-bottom">
                                <div class="flex-shrink-0">
                                    <img src="<?php echo getProfileImageUrl($review['reviewer_profile_pic'] ?? null); ?>" 
                                         class="rounded-circle" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                            <div>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php echo formatDate($review['created_at']); ?>
                                                <span class="mx-1">•</span>
                                                <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $review['listing_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($review['listing_title']); ?>
                                                </a>
                                                <span class="mx-1">•</span>
                                                Order #<?php echo $review['order_number']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></div>
                                    
                                    <!-- Seller Response -->
                                    <?php if (!empty($review['seller_response'])): ?>
                                        <div class="mt-2 ms-4 p-2 bg-light rounded">
                                            <div class="text-muted small fw-bold">Your Response:</div>
                                            <div><?php echo nl2br(htmlspecialchars($review['seller_response'])); ?></div>
                                            <div class="text-muted small"><?php echo timeAgo($review['response_date']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary mt-2 respond-btn" 
                                                data-review-id="<?php echo $review['id']; ?>"
                                                data-reviewer="<?php echo htmlspecialchars($review['reviewer_name']); ?>">
                                            <i class="bi bi-reply"></i> Respond
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo SITE_URL; ?>/seller/reviews.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-reply"></i> Respond to Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="respond">
                    <input type="hidden" name="review_id" id="responseReviewId">
                    
                    <div class="mb-3">
                        <label class="form-label">Responding to: <span id="responseReviewer" class="fw-bold"></span></label>
                        <textarea class="form-control" name="response" rows="4" required placeholder="Write your response..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send Response
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.respond-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('responseReviewId').value = this.dataset.reviewId;
            document.getElementById('responseReviewer').textContent = this.dataset.reviewer;
            new bootstrap.Modal(document.getElementById('responseModal')).show();
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>