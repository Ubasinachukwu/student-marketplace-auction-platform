<?php
// marketplace/classes/User.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $id;
    private $email;
    private $fullName;
    private $userType;
    private $phone;
    private $address;
    private $profilePic;
    private $emailVerified;
    private $isActive;
    private $createdAt;
    private $updatedAt;
    private $lastLogin;

    public function __construct($userId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($userId) {
            $this->load($userId);
        }
    }

    /**
     * Load user data from database
     */
    public function load($userId) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );
        
        if ($user) {
            $this->id = $user['id'];
            $this->email = $user['email'];
            $this->fullName = $user['full_name'];
            $this->userType = $user['user_type'];
            $this->phone = $user['phone'];
            $this->address = $user['address'];
            $this->profilePic = $user['profile_pic'];
            $this->emailVerified = (bool)$user['email_verified'];
            $this->isActive = (bool)$user['is_active'];
            $this->createdAt = $user['created_at'];
            $this->updatedAt = $user['updated_at'];
            $this->lastLogin = $user['last_login'];
            return true;
        }
        return false;
    }

    /**
     * Create new user
     */
    public function create($data) {
        $required = ['email', 'password', 'full_name', 'user_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Check if email exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$data['email']]
        );
        if ($existing) {
            throw new Exception('Email already registered');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $verificationCode = bin2hex(random_bytes(32));

        $userId = $this->db->insert(
            "INSERT INTO users (email, password_hash, full_name, user_type, phone, address, verification_code) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['email'],
                $passwordHash,
                $data['full_name'],
                $data['user_type'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $verificationCode
            ]
        );

        // If seller, create seller profile
        if ($data['user_type'] === 'seller') {
            $this->db->insert(
                "INSERT INTO seller_profiles (user_id, store_name) VALUES (?, ?)",
                [$userId, $data['full_name'] . "'s Store"]
            );
        }

        $this->load($userId);
        return $userId;
    }

    /**
     * Update user
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        $allowedFields = ['full_name', 'phone', 'address', 'profile_pic'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $this->id;
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        return $this->db->update($query, $params);
    }

    /**
     * Change password
     */
    public function changePassword($oldPassword, $newPassword) {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        // Verify old password
        $user = $this->db->fetchOne(
            "SELECT password_hash FROM users WHERE id = ?",
            [$this->id]
        );
        
        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new Exception('Current password is incorrect');
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        return $this->db->update(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$newHash, $this->id]
        );
    }

    /**
     * Verify email
     */
    public function verifyEmail($code) {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        $user = $this->db->fetchOne(
            "SELECT verification_code FROM users WHERE id = ?",
            [$this->id]
        );

        if ($user['verification_code'] !== $code) {
            throw new Exception('Invalid verification code');
        }

        return $this->db->update(
            "UPDATE users SET email_verified = 1, verification_code = NULL WHERE id = ?",
            [$this->id]
        );
    }

    /**
     * Reset password request
     */
    public function requestPasswordReset() {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        $resetToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        return $this->db->update(
            "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
            [$resetToken, $expires, $this->id]
        );
    }

    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        $user = $this->db->fetchOne(
            "SELECT reset_token, reset_expires FROM users WHERE id = ? AND reset_token = ? AND reset_expires > NOW()",
            [$this->id, $token]
        );

        if (!$user) {
            throw new Exception('Invalid or expired reset token');
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        return $this->db->update(
            "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$newHash, $this->id]
        );
    }

    /**
     * Get seller profile
     */
    public function getSellerProfile() {
        if ($this->userType !== 'seller') {
            return null;
        }

        return $this->db->fetchOne(
            "SELECT * FROM seller_profiles WHERE user_id = ?",
            [$this->id]
        );
    }

    /**
     * Update seller profile
     */
    public function updateSellerProfile($data) {
        if ($this->userType !== 'seller') {
            throw new Exception('User is not a seller');
        }

        $allowedFields = ['store_name', 'store_description', 'payout_method', 'payout_details'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $this->id;
        $query = "UPDATE seller_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?";
        
        return $this->db->update($query, $params);
    }

    /**
     * Get user statistics
     */
    public function getStats() {
        if (!$this->id) {
            throw new Exception('User not loaded');
        }

        $stats = [
            'total_listings' => 0,
            'active_listings' => 0,
            'total_orders' => 0,
            'total_spent' => 0,
            'total_earned' => 0,
            'total_reviews' => 0,
            'avg_rating' => 0
        ];

        // If seller
        if ($this->userType === 'seller') {
            $sellerStats = $this->db->fetchOne(
                "SELECT 
                    (SELECT COUNT(*) FROM listings WHERE seller_id = ?) as total_listings,
                    (SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'active') as active_listings,
                    (SELECT COUNT(*) FROM orders WHERE seller_id = ?) as total_orders,
                    (SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE seller_id = ? AND payment_status = 'paid') as total_earned,
                    (SELECT COUNT(*) FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller') as total_reviews,
                    (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE target_user_id = ? AND review_type = 'buyer_to_seller') as avg_rating
                ",
                [$this->id, $this->id, $this->id, $this->id, $this->id, $this->id]
            );
            
            $stats = array_merge($stats, $sellerStats);
        }

        // If buyer
        if ($this->userType === 'buyer') {
            $buyerStats = $this->db->fetchOne(
                "SELECT 
                    (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) as total_orders,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE buyer_id = ? AND payment_status = 'paid') as total_spent
                ",
                [$this->id, $this->id]
            );
            
            $stats = array_merge($stats, $buyerStats);
        }

        return $stats;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmail() { return $this->email; }
    public function getFullName() { return $this->fullName; }
    public function getUserType() { return $this->userType; }
    public function getPhone() { return $this->phone; }
    public function getAddress() { return $this->address; }
    public function getProfilePic() { return $this->profilePic; }
    public function isEmailVerified() { return $this->emailVerified; }
    public function isActive() { return $this->isActive; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getLastLogin() { return $this->lastLogin; }

    /**
     * Login user
     */
    public static function login($email, $password) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            throw new Exception('Invalid email or password');
        }

        if (!$user['is_active']) {
            throw new Exception('Account is deactivated');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password');
        }

        // Update last login
        $db->update(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        return new self($user['id']);
    }
}