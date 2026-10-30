-- marketplace/sql/schema.sql
-- Complete Database Schema for Marketplace

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `marketplace_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `marketplace_db`;

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `user_type` ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `profile_pic` VARCHAR(255) DEFAULT NULL,
    `email_verified` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `verification_code` VARCHAR(64) DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_type` (`user_type`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. SELLER PROFILES TABLE
-- =====================================================
CREATE TABLE `seller_profiles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `store_name` VARCHAR(100) DEFAULT NULL,
    `store_description` TEXT DEFAULT NULL,
    `business_license` VARCHAR(255) DEFAULT NULL,
    `payout_method` ENUM('bank') DEFAULT 'bank',
    `payout_details` TEXT DEFAULT NULL, -- JSON data
    `total_sales` INT(11) DEFAULT 0,
    `total_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
    `response_time` INT(11) DEFAULT 0, -- Average response time in hours
    `is_verified` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_seller_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. CATEGORIES TABLE
-- =====================================================
CREATE TABLE `categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `parent_id` INT(11) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT NULL,
    `sort_order` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    INDEX `idx_categories_parent` (`parent_id`),
    INDEX `idx_categories_slug` (`slug`),
    INDEX `idx_categories_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. LISTINGS TABLE (CORE)
-- =====================================================
CREATE TABLE `listings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `seller_id` INT(11) NOT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `condition` ENUM('new', 'like_new', 'good', 'fair', 'poor') NOT NULL DEFAULT 'good',
    `sale_type` ENUM('fixed', 'auction', 'hybrid') NOT NULL DEFAULT 'fixed',
    
    -- Fixed Price Fields
    `price` DECIMAL(10,2) DEFAULT NULL,
    
    -- Auction Fields
    `starting_bid` DECIMAL(10,2) DEFAULT NULL,
    `reserve_price` DECIMAL(10,2) DEFAULT NULL,
    `current_bid` DECIMAL(10,2) DEFAULT NULL,
    `bid_increment` DECIMAL(10,2) DEFAULT 1.00,
    `auction_start` TIMESTAMP NULL DEFAULT NULL,
    `auction_end` TIMESTAMP NULL DEFAULT NULL,
    `auto_extend` TINYINT(1) DEFAULT 1,
    
    -- Hybrid Fields
    `buy_it_now_price` DECIMAL(10,2) DEFAULT NULL,
    
    -- Common Fields
    `quantity` INT(11) DEFAULT 1,
    `views_count` INT(11) DEFAULT 0,
    `watch_count` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_featured` TINYINT(1) DEFAULT 0,
    `status` ENUM('draft', 'active', 'sold', 'ended', 'cancelled') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_listings_seller` (`seller_id`),
    INDEX `idx_listings_category` (`category_id`),
    INDEX `idx_listings_status` (`status`),
    INDEX `idx_listings_sale_type` (`sale_type`),
    INDEX `idx_listings_auction_end` (`auction_end`),
    INDEX `idx_listings_slug` (`slug`),
    INDEX `idx_listings_active` (`is_active`),
    INDEX `idx_listings_featured` (`is_featured`),
    INDEX `idx_listings_current_bid` (`current_bid`),
    INDEX `idx_listings_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. LISTING IMAGES TABLE
-- =====================================================
CREATE TABLE `listing_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `listing_id` INT(11) NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
    INDEX `idx_images_listing` (`listing_id`),
    INDEX `idx_images_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. BIDS TABLE (AUCTION ENGINE)
-- =====================================================
CREATE TABLE `bids` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `listing_id` INT(11) NOT NULL,
    `buyer_id` INT(11) NOT NULL,
    `bid_amount` DECIMAL(10,2) NOT NULL,
    `is_proxy_bid` TINYINT(1) DEFAULT 0,
    `max_proxy_amount` DECIMAL(10,2) DEFAULT NULL,
    `is_winning` TINYINT(1) DEFAULT 0,
    `is_retracted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_bids_listing` (`listing_id`),
    INDEX `idx_bids_buyer` (`buyer_id`),
    INDEX `idx_bids_winning` (`is_winning`),
    INDEX `idx_bids_amount` (`bid_amount`),
    INDEX `idx_bids_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. ORDERS TABLE
-- =====================================================
CREATE TABLE `orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `listing_id` INT(11) NOT NULL,
    `buyer_id` INT(11) NOT NULL,
    `seller_id` INT(11) NOT NULL,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `shipping_cost` DECIMAL(10,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
    `platform_fee` DECIMAL(10,2) DEFAULT 0.00,
    `net_amount` DECIMAL(10,2) DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    `payment_id` VARCHAR(100) DEFAULT NULL,
    `order_status` ENUM('pending', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned') DEFAULT 'pending',
    `shipping_address` TEXT DEFAULT NULL,
    `tracking_number` VARCHAR(100) DEFAULT NULL,
    `tracking_carrier` VARCHAR(50) DEFAULT NULL,
    `shipped_date` TIMESTAMP NULL DEFAULT NULL,
    `delivered_date` TIMESTAMP NULL DEFAULT NULL,
    `buyer_message` TEXT DEFAULT NULL,
    `seller_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_orders_buyer` (`buyer_id`),
    INDEX `idx_orders_seller` (`seller_id`),
    INDEX `idx_orders_listing` (`listing_id`),
    INDEX `idx_orders_status` (`order_status`),
    INDEX `idx_orders_payment` (`payment_status`),
    INDEX `idx_orders_number` (`order_number`),
    INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. REVIEWS TABLE
-- =====================================================
CREATE TABLE `reviews` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `reviewer_id` INT(11) NOT NULL,
    `target_user_id` INT(11) NOT NULL,
    `rating` INT(11) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `review_text` TEXT DEFAULT NULL,
    `review_type` ENUM('buyer_to_seller', 'seller_to_buyer') NOT NULL,
    `is_approved` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_reviews_order` (`order_id`),
    INDEX `idx_reviews_reviewer` (`reviewer_id`),
    INDEX `idx_reviews_target` (`target_user_id`),
    INDEX `idx_reviews_type` (`review_type`),
    INDEX `idx_reviews_rating` (`rating`),
    INDEX `idx_reviews_approved` (`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. WATCHLIST TABLE
-- =====================================================
CREATE TABLE `watchlist` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `listing_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_watch` (`user_id`, `listing_id`),
    INDEX `idx_watchlist_user` (`user_id`),
    INDEX `idx_watchlist_listing` (`listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. MESSAGES TABLE
-- =====================================================
CREATE TABLE `messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sender_id` INT(11) NOT NULL,
    `receiver_id` INT(11) NOT NULL,
    `listing_id` INT(11) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `parent_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_messages_sender` (`sender_id`),
    INDEX `idx_messages_receiver` (`receiver_id`),
    INDEX `idx_messages_listing` (`listing_id`),
    INDEX `idx_messages_read` (`is_read`),
    INDEX `idx_messages_parent` (`parent_id`),
    INDEX `idx_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. DISPUTES TABLE
-- =====================================================
CREATE TABLE `disputes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `raised_by` INT(11) NOT NULL,
    `raised_by_type` ENUM('buyer', 'seller') NOT NULL,
    `dispute_type` ENUM(
        'item_not_received',
        'item_not_as_described',
        'payment_issue',
        'other',
        'buyer_no_payment',
        'buyer_unresponsive',
        'buyer_fraudulent_claim',
        'shipping_problem'
    ) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('open', 'under_review', 'resolved', 'closed') DEFAULT 'open',
    `resolution_notes` TEXT DEFAULT NULL,
    `resolved_by` INT(11) DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`raised_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_disputes_order` (`order_id`),
    INDEX `idx_disputes_raised` (`raised_by`),
    INDEX `idx_disputes_status` (`status`),
    INDEX `idx_disputes_type` (`dispute_type`),
    INDEX `idx_disputes_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE `transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) DEFAULT NULL,
    `user_id` INT(11) NOT NULL,
    `type` ENUM('payment', 'refund', 'fee', 'payout') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `balance_before` DECIMAL(10,2) DEFAULT 0.00,
    `balance_after` DECIMAL(10,2) DEFAULT 0.00,
    `description` VARCHAR(255) DEFAULT NULL,
    `gateway_transaction_id` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_transactions_order` (`order_id`),
    INDEX `idx_transactions_user` (`user_id`),
    INDEX `idx_transactions_type` (`type`),
    INDEX `idx_transactions_status` (`status`),
    INDEX `idx_transactions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_read` (`is_read`),
    INDEX `idx_notifications_type` (`type`),
    INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. ADMIN LOGS TABLE (AUDIT TRAIL)
-- =====================================================
CREATE TABLE `admin_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` INT(11) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(50) DEFAULT NULL,
    `target_id` INT(11) DEFAULT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_logs_admin` (`admin_id`),
    INDEX `idx_admin_logs_action` (`action`),
    INDEX `idx_admin_logs_target` (`target_type`, `target_id`),
    INDEX `idx_admin_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. SITE SETTINGS TABLE
-- =====================================================
CREATE TABLE `settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_settings_key` (`setting_key`),
    INDEX `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default Admin User (password: Admin@123)
INSERT INTO `users` (`email`, `password_hash`, `full_name`, `user_type`, `email_verified`, `is_active`) VALUES
('admin@marketplace.com', '$2y$12$7Q9X8Y5Z6W4V3U2T1S0R9O8N7M6L5K4J3H2G1F0E9D8C7B6A5Z4Y3W2X1', 'Super Admin', 'admin', 1, 1);

-- Default Categories
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`) VALUES
('Electronics', 'electronics', 'Phones, laptops, cameras, and other gadgets', 1),
('Fashion', 'fashion', 'Clothing, shoes, accessories, and jewelry', 2),
('Furniture', 'furniture', 'Home and office furniture', 3),
('Books', 'books', 'Textbooks, novels, and magazines', 4),
('Collectibles', 'collectibles', 'Antiques, art, and rare items', 5),
('Vehicles', 'vehicles', 'Cars, motorcycles, and parts', 6),
('Sports', 'sports', 'Equipment and gear for sports', 7),
('Toys', 'toys', 'Toys, games, and hobbies', 8),
('Home & Garden', 'home-garden', 'Decor, tools, and garden supplies', 9),
('Other', 'other', 'Items that don\'t fit elsewhere', 10);

-- Sub-categories for Electronics
INSERT INTO `categories` (`name`, `slug`, `parent_id`, `description`, `sort_order`) VALUES
('Smartphones', 'smartphones', 1, 'Mobile phones and accessories', 1),
('Laptops', 'laptops', 1, 'Notebooks and computers', 2),
('Cameras', 'cameras', 1, 'Digital cameras and lenses', 3),
('Audio', 'audio', 1, 'Headphones, speakers, and audio equipment', 4);

-- Sub-categories for Fashion
INSERT INTO `categories` (`name`, `slug`, `parent_id`, `description`, `sort_order`) VALUES
('Men', 'men', 2, 'Men\'s clothing and accessories', 1),
('Women', 'women', 2, 'Women\'s clothing and accessories', 2),
('Kids', 'kids', 2, 'Children\'s clothing', 3),
('Shoes', 'shoes', 2, 'Footwear for all ages', 4);

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `is_public`) VALUES
('site_name', 'MarketPlace', 'general', 1),
('site_description', 'Buy and sell used items', 'general', 1),
('platform_fee_percentage', '10', 'fees', 0),
('auction_listing_fee', '1.00', 'fees', 0),
('reserve_price_fee', '2.00', 'fees', 0),
('free_shipping_threshold', '50.00', 'shipping', 1),
('return_policy_days', '14', 'policies', 1),
('payment_window_hours', '48', 'auction', 0),
('auto_extend_minutes', '2', 'auction', 0);

-- =====================================================
-- INDEXES FOR PERFORMANCE (Additional)
-- =====================================================

-- Composite indexes for search
CREATE INDEX idx_listings_search ON listings(seller_id, status, sale_type, price, current_bid);
CREATE INDEX idx_orders_search ON orders(buyer_id, seller_id, order_status, created_at);
CREATE INDEX idx_bids_search ON bids(listing_id, buyer_id, is_winning, created_at);

-- Full-text search indexes
ALTER TABLE listings ADD FULLTEXT INDEX ft_listings_title (title);
ALTER TABLE listings ADD FULLTEXT INDEX ft_listings_description (description);
ALTER TABLE listings ADD FULLTEXT INDEX ft_listings_all (title, description);

-- Add password reset columns to users table
ALTER TABLE `users` 
ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `verification_code`,
ADD COLUMN `reset_expires` TIMESTAMP NULL DEFAULT NULL AFTER `reset_token`,
ADD INDEX `idx_users_reset_token` (`reset_token`);

-- =====================================================
-- END OF SCHEMA
-- =====================================================