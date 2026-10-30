<?php
// marketplace/api/listings/featured.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Listing.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$db = Database::getInstance();

try {
    $params = [];
    $whereConditions = ["l.is_featured = 1", "l.status = 'active'"];

    if ($category > 0) {
        $whereConditions[] = "l.category_id = ?";
        $params[] = $category;
    }

    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    $query = "
        SELECT l.*, 
               u.full_name as seller_name,
               (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
               (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
               (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as highest_bid
        FROM listings l
        JOIN users u ON l.seller_id = u.id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT ?
    ";

    $params[] = $limit;
    $listings = $db->fetchAll($query, $params);

    $formattedListings = [];
    foreach ($listings as $listing) {
        $formattedListings[] = [
            'id' => $listing['id'],
            'title' => $listing['title'],
            'description' => $listing['description'],
            'price' => $listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid'],
            'sale_type' => $listing['sale_type'],
            'condition' => $listing['condition'],
            'status' => $listing['status'],
            'seller_name' => $listing['seller_name'],
            'primary_image' => $listing['primary_image'],
            'bid_count' => $listing['bid_count'] ?? 0,
            'highest_bid' => $listing['highest_bid'] ?? null,
            'auction_end' => $listing['auction_end'],
            'views_count' => $listing['views_count'],
            'watch_count' => $listing['watch_count'],
            'created_at' => $listing['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'featured_listings' => $formattedListings,
        'total' => count($formattedListings)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}