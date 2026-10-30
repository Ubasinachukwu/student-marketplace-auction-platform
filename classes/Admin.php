<?php
// marketplace/classes/Admin.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Listing.php';
require_once __Order . '/Order.php';
require_once __DIR__ . '/Dispute.php';

class Admin {
    private $db;
    private $user;
    private $userId;

    public function __construct($userId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($userId) {
            $this->user = new User($userId);
            if ($this->user->getUserType() !== 'admin') {
                throw new Exception('User is not an admin');
            }
            $this->userId = $userId;
        }
    }

    /**
     * Get platform statistics
     */
    public function getPlatformStats() {
        $stats = [];

        // User stats
        $userStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN user_type = 'buyer' THEN 1 ELSE 0 END) as total_buyers,
                SUM(CASE WHEN user_type = 'seller' THEN 1 ELSE 0 END) as total_sellers,
                SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
            FROM users"
        );
        $stats['users'] = $userStats;

        // Listing stats
        $listingStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_listings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_listings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_listings,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_listings,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_listings,
                SUM(views_count) as total_views,
                SUM(watch_count) as total_watches
            FROM listings"
        );
        $stats['listings'] = $listingStats;

        // Order stats
        $orderStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
                SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(total_amount) as total_revenue,
                SUM(platform_fee) as total_fees,
                SUM(net_amount) as net_revenue
            FROM orders
            WHERE payment_status = 'paid'"
        );
        $stats['orders'] = $orderStats;

        // Dispute stats
        $disputeStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_disputes,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_disputes,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_disputes,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_disputes
            FROM disputes"
        );
        $stats['disputes'] = $disputeStats;

        // Recent activity
        $stats['recent_users'] = $this->db->fetchAll(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT 10"
        );
        
        $stats['recent_listings'] = $this->db->fetchAll(
            "SELECT l.*, u.full_name as seller_name 
             FROM listings l
             JOIN users u ON l.seller_id = u.id
             ORDER BY l.created_at DESC LIMIT 10"
        );
        
        $stats['recent_orders'] = $this->db->fetchAll(
            "SELECT o.*, l.title as listing_title, u.full_name as buyer_name
             FROM orders o
             JOIN listings l ON o.listing_id = l.id
             JOIN users u ON o.buyer_id = u.id
             ORDER BY o.created_at DESC LIMIT 10"
        );

        return $stats;
    }

    /**
     * Get sales report
     */
    public function getSalesReport($dateFrom, $dateTo) {
        $dailySales = $this->db->fetchAll(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(total_amount) as revenue,
                SUM(platform_fee) as fees,
                SUM(net_amount) as net_revenue
            FROM orders
            WHERE payment_status = 'paid'
                AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            [$dateFrom, $dateTo]
        );

        // Get top sellers
        $topSellers = $this->db->fetchAll(
            "SELECT 
                u.id,
                u.full_name,
                u.email,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                SUM(o.net_amount) as net_earnings,
                sp.rating_avg as seller_rating
            FROM users u
            JOIN orders o ON u.id = o.seller_id
            LEFT JOIN seller_profiles sp ON u.id = sp.user_id
            WHERE o.payment_status = 'paid'
                AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY u.id
            ORDER BY total_revenue DESC
            LIMIT 10",
            [$dateFrom, $dateTo]
        );

        // Get top buyers
        $topBuyers = $this->db->fetchAll(
            "SELECT 
                u.id,
                u.full_name,
                u.email,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_spent
            FROM users u
            JOIN orders o ON u.id = o.buyer_id
            WHERE o.payment_status = 'paid'
                AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY u.id
            ORDER BY total_spent DESC
            LIMIT 10",
            [$dateFrom, $dateTo]
        );

        // Get category sales
        $categorySales = $this->db->fetchAll(
            "SELECT 
                c.name as category_name,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue
            FROM orders o
            JOIN listings l ON o.listing_id = l.id
            LEFT JOIN categories c ON l.category_id = c.id
            WHERE o.payment_status = 'paid'
                AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total_revenue DESC",
            [$dateFrom, $dateTo]
        );

        return [
            'daily_sales' => $dailySales,
            'top_sellers' => $topSellers,
            'top_buyers' => $topBuyers,
            'category_sales' => $categorySales
        ];
    }

    /**
     * Moderate user
     */
    public function moderateUser($userId, $action, $reason = null) {
        $user = new User($userId);
        if (!$user->getId()) {
            throw new Exception('User not found');
        }

        try {
            $this->db->beginTransaction();

            switch ($action) {
                case 'suspend':
                    $user->update(['is_active' => 0]);
                    $message = "User {$user->getFullName()} has been suspended";
                    break;
                    
                case 'activate':
                    $user->update(['is_active' => 1]);
                    $message = "User {$user->getFullName()} has been activated";
                    break;
                    
                case 'verify':
                    $this->db->update(
                        "UPDATE users SET email_verified = 1 WHERE id = ?",
                        [$userId]
                    );
                    if ($user->getUserType() === 'seller') {
                        $this->db->update(
                            "UPDATE seller_profiles SET is_verified = 1 WHERE user_id = ?",
                            [$userId]
                        );
                    }
                    $message = "User {$user->getFullName()} has been verified";
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }

            // Log action
            $this->db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, ?, 'user', ?, ?)",
                [$this->userId, $action, $userId, $message . ($reason ? " Reason: {$reason}" : "")]
            );

            $this->db->commit();
            return ['success' => true, 'message' => $message];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Moderate listing
     */
    public function moderateListing($listingId, $action, $reason = null) {
        $listing = new Listing($listingId);
        if (!$listing->getId()) {
            throw new Exception('Listing not found');
        }

        try {
            $this->db->beginTransaction();

            switch ($action) {
                case 'approve':
                    $listing->updateStatus('active');
                    $message = "Listing '{$listing->getTitle()}' has been approved";
                    break;
                    
                case 'reject':
                    $listing->updateStatus('cancelled');
                    $message = "Listing '{$listing->getTitle()}' has been rejected";
                    break;
                    
                case 'feature':
                    $current = $listing->isFeatured() ? 0 : 1;
                    $listing->update(['is_featured' => $current]);
                    $message = "Listing '{$listing->getTitle()}' has been " . ($current ? 'featured' : 'unfeatured');
                    break;
                    
                case 'delete':
                    $listing->updateStatus('cancelled');
                    $message = "Listing '{$listing->getTitle()}' has been deleted";
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }

            // Log action
            $this->db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, ?, 'listing', ?, ?)",
                [$this->userId, $action, $listingId, $message . ($reason ? " Reason: {$reason}" : "")]
            );

            // Notify seller
            $this->db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link) 
                 VALUES (?, 'listing', 'Listing Update', ?, '/seller/manage-listings.php')",
                [$listing->getSellerId(), $message]
            );

            $this->db->commit();
            return ['success' => true, 'message' => $message];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get admin logs
     */
    public function getAdminLogs($limit = 100, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT al.*, 
                    u.full_name as admin_name,
                    u.email as admin_email
             FROM admin_logs al
             JOIN users u ON al.admin_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Get system settings
     */
    public function getSettings($group = null) {
        $query = "SELECT * FROM settings";
        $params = [];
        
        if ($group) {
            $query .= " WHERE setting_group = ?";
            $params[] = $group;
        }
        
        $query .= " ORDER BY setting_group, setting_key";
        
        return $this->db->fetchAll($query, $params);
    }

    /**
     * Update system settings
     */
    public function updateSettings($settings) {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                $this->db->update(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                    [$value, $key]
                );
                
                // If no rows updated, insert
                if ($this->db->rowCount() == 0) {
                    $this->db->insert(
                        "INSERT INTO settings (setting_key, setting_value, setting_group) 
                         VALUES (?, ?, 'general')",
                        [$key, $value]
                    );
                }
            }

            // Log action
            $this->db->insert(
                "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
                 VALUES (?, 'update_settings', 'settings', 0, 'Settings updated')",
                [$this->userId]
            );

            $this->db->commit();
            return ['success' => true, 'message' => 'Settings updated successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}