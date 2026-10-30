<?php
// marketplace/classes/Review.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Order.php';

class Review {
    private $db;
    private $id;
    private $orderId;
    private $reviewerId;
    private $targetUserId;
    private $rating;
    private $reviewText;
    private $reviewType;
    private $isApproved;
    private $sellerResponse;
    private $responseDate;
    private $createdAt;
    private $updatedAt;
    private $order;
    private $reviewer;
    private $targetUser;

    public function __construct($reviewId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($reviewId) {
            $this->load($reviewId);
        }
    }

    /**
     * Load review from database
     */
    public function load($reviewId) {
        $review = $this->db->fetchOne(
            "SELECT * FROM reviews WHERE id = ?",
            [$reviewId]
        );
        
        if ($review) {
            $this->id = $review['id'];
            $this->orderId = $review['order_id'];
            $this->reviewerId = $review['reviewer_id'];
            $this->targetUserId = $review['target_user_id'];
            $this->rating = $review['rating'];
            $this->reviewText = $review['review_text'];
            $this->reviewType = $review['review_type'];
            $this->isApproved = (bool)$review['is_approved'];
            $this->sellerResponse = $review['seller_response'];
            $this->responseDate = $review['response_date'];
            $this->createdAt = $review['created_at'];
            $this->updatedAt = $review['updated_at'];
            
            // Load related data
            $this->order = new Order($this->orderId);
            $this->reviewer = new User($this->reviewerId);
            $this->targetUser = new User($this->targetUserId);
            
            return true;
        }
        return false;
    }

    /**
     * Create new review
     */
    public function create($data) {
        $required = ['order_id', 'reviewer_id', 'target_user_id', 'rating', 'review_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if ($data['rating'] < 1 || $data['rating'] > 5) {
            throw new Exception('Rating must be between 1 and 5');
        }

        $this->orderId = $data['order_id'];
        $this->reviewerId = $data['reviewer_id'];
        $this->targetUserId = $data['target_user_id'];
        $this->rating = $data['rating'];
        $this->reviewText = $data['review_text'] ?? null;
        $this->reviewType = $data['review_type'];
        $this->isApproved = true;

        // Validate order
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = ? AND order_status = 'completed'",
            [$this->orderId]
        );
        if (!$order) {
            throw new Exception('Order not found or not completed');
        }

        // Check if user can review
        if ($this->reviewType === 'buyer_to_seller') {
            if ($order['buyer_id'] != $this->reviewerId) {
                throw new Exception('Only the buyer can review the seller');
            }
            if ($order['seller_id'] != $this->targetUserId) {
                throw new Exception('Invalid target user');
            }
        } elseif ($this->reviewType === 'seller_to_buyer') {
            if ($order['seller_id'] != $this->reviewerId) {
                throw new Exception('Only the seller can review the buyer');
            }
            if ($order['buyer_id'] != $this->targetUserId) {
                throw new Exception('Invalid target user');
            }
        } else {
            throw new Exception('Invalid review type');
        }

        // Check if already reviewed
        $existing = $this->db->fetchOne(
            "SELECT id FROM reviews WHERE order_id = ? AND reviewer_id = ? AND review_type = ?",
            [$this->orderId, $this->reviewerId, $this->reviewType]
        );
        if ($existing) {
            throw new Exception('You have already reviewed this order');
        }

        // Insert review
        $this->id = $this->db->insert(
            "INSERT INTO reviews (order_id, reviewer_id, target_user_id, rating, review_text, review_type) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->orderId,
                $this->reviewerId,
                $this->targetUserId,
                $this->rating,
                $this->reviewText,
                $this->reviewType
            ]
        );

        // Update target user's average rating
        $this->updateUserRating($this->targetUserId);

        return $this->id;
    }

    /**
     * Update review
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception('Review not loaded');
        }

        $allowedFields = ['rating', 'review_text'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'rating' && ($data[$field] < 1 || $data[$field] > 5)) {
                    throw new Exception('Rating must be between 1 and 5');
                }
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $this->id;
        $query = "UPDATE reviews SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $result = $this->db->update($query, $params);
        
        // Reload review
        $this->load($this->id);
        
        // Update user rating
        $this->updateUserRating($this->targetUserId);
        
        return $result;
    }

    /**
     * Add seller response
     */
    public function addResponse($response) {
        if (!$this->id) {
            throw new Exception('Review not loaded');
        }

        if ($this->reviewType !== 'buyer_to_seller') {
            throw new Exception('Only seller reviews can be responded to');
        }

        $this->sellerResponse = $response;
        $this->responseDate = date('Y-m-d H:i:s');

        return $this->db->update(
            "UPDATE reviews SET seller_response = ?, response_date = ? WHERE id = ?",
            [$this->sellerResponse, $this->responseDate, $this->id]
        );
    }

    /**
     * Update user's average rating
     */
    private function updateUserRating($userId) {
        $avgRating = $this->db->fetchOne(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as total 
             FROM reviews 
             WHERE target_user_id = ? AND review_type = 'buyer_to_seller' AND is_approved = 1",
            [$userId]
        );

        if ($avgRating['total'] > 0) {
            $this->db->update(
                "UPDATE seller_profiles SET rating_avg = ? WHERE user_id = ?",
                [$avgRating['avg_rating'], $userId]
            );
        }
    }

    /**
     * Get user's rating summary
     */
    public static function getUserRating($userId) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        return $db->fetchOne(
            "SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM reviews 
            WHERE target_user_id = ? AND review_type = 'buyer_to_seller' AND is_approved = 1",
            [$userId]
        );
    }

    /**
     * Get user's reviews
     */
    public static function getUserReviews($userId, $limit = 20) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        return $db->fetchAll(
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
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get review data as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'order' => $this->order ? $this->order->toArray() : null,
            'reviewer_id' => $this->reviewerId,
            'reviewer' => $this->reviewer ? $this->reviewer->toArray() : null,
            'target_user_id' => $this->targetUserId,
            'target_user' => $this->targetUser ? $this->targetUser->toArray() : null,
            'rating' => $this->rating,
            'review_text' => $this->reviewText,
            'review_type' => $this->reviewType,
            'is_approved' => $this->isApproved,
            'seller_response' => $this->sellerResponse,
            'response_date' => $this->responseDate,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getOrderId() { return $this->orderId; }
    public function getReviewerId() { return $this->reviewerId; }
    public function getTargetUserId() { return $this->targetUserId; }
    public function getRating() { return $this->rating; }
    public function getReviewText() { return $this->reviewText; }
    public function getReviewType() { return $this->reviewType; }
    public function isApproved() { return $this->isApproved; }
    public function getSellerResponse() { return $this->sellerResponse; }
    public function getResponseDate() { return $this->responseDate; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getOrder() { return $this->order; }
    public function getReviewer() { return $this->reviewer; }
    public function getTargetUser() { return $this->targetUser; }
}