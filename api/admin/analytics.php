<?php
// marketplace/api/admin/analytics.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Admin.php';

header('Content-Type: application/json');

// Require admin role
Session::requireRole('admin');

$admin = new Admin(Session::getUserId());
$period = isset($_GET['period']) ? sanitize($_GET['period']) : 'week';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

try {
    // Get platform stats
    $stats = $admin->getPlatformStats();

    // Get sales report
    $salesReport = $admin->getSalesReport($dateFrom, $dateTo);

    // Prepare analytics data
    $analytics = [
        'overview' => [
            'total_users' => $stats['users']['total_users'] ?? 0,
            'total_listings' => $stats['listings']['total_listings'] ?? 0,
            'total_orders' => $stats['orders']['total_orders'] ?? 0,
            'total_revenue' => $stats['orders']['total_revenue'] ?? 0,
            'total_fees' => $stats['orders']['total_fees'] ?? 0,
            'net_revenue' => $stats['orders']['net_revenue'] ?? 0,
            'active_users' => $stats['users']['active_users'] ?? 0,
            'active_listings' => $stats['listings']['active_listings'] ?? 0,
            'pending_orders' => $stats['orders']['pending_orders'] ?? 0,
            'open_disputes' => $stats['disputes']['open_disputes'] ?? 0
        ],
        'users' => [
            'total' => $stats['users']['total_users'] ?? 0,
            'buyers' => $stats['users']['total_buyers'] ?? 0,
            'sellers' => $stats['users']['total_sellers'] ?? 0,
            'verified' => $stats['users']['verified_users'] ?? 0,
            'active' => $stats['users']['active_users'] ?? 0
        ],
        'listings' => [
            'total' => $stats['listings']['total_listings'] ?? 0,
            'active' => $stats['listings']['active_listings'] ?? 0,
            'pending' => $stats['listings']['pending_listings'] ?? 0,
            'sold' => $stats['listings']['sold_listings'] ?? 0,
            'featured' => $stats['listings']['featured_listings'] ?? 0,
            'total_views' => $stats['listings']['total_views'] ?? 0,
            'total_watches' => $stats['listings']['total_watches'] ?? 0
        ],
        'orders' => [
            'total' => $stats['orders']['total_orders'] ?? 0,
            'pending' => $stats['orders']['pending_orders'] ?? 0,
            'confirmed' => $stats['orders']['confirmed_orders'] ?? 0,
            'shipped' => $stats['orders']['shipped_orders'] ?? 0,
            'delivered' => $stats['orders']['delivered_orders'] ?? 0,
            'completed' => $stats['orders']['completed_orders'] ?? 0,
            'cancelled' => $stats['orders']['cancelled_orders'] ?? 0
        ],
        'sales_report' => $salesReport,
        'period' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]
    ];

    echo json_encode([
        'success' => true,
        'analytics' => $analytics
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}