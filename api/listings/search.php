<?php
// marketplace/api/listings/search.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();

// Get search parameters
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$saleType = isset($_GET['sale_type']) ? sanitize($_GET['sale_type']) : '';
$condition = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ["l.status = 'active'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category > 0) {
    // Check subcategories
    $subCategories = $db->fetchAll(
        "SELECT id FROM categories WHERE parent_id = ? OR id = ?",
        [$category, $category]
    );
    $catIds = array_column($subCategories, 'id');
    if (!empty($catIds)) {
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $whereConditions[] = "l.category_id IN ($placeholders)";
        $params = array_merge($params, $catIds);
    }
}

if ($minPrice > 0) {
    $whereConditions[] = "(l.price >= ? OR l.current_bid >= ?)";
    $params[] = $minPrice;
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $whereConditions[] = "(l.price <= ? OR l.current_bid <= ?)";
    $params[] = $maxPrice;
    $params[] = $maxPrice;
}

if (!empty($saleType)) {
    $whereConditions[] = "l.sale_type = ?";
    $params[] = $saleType;
}

if (!empty($condition)) {
    $whereConditions[] = "l.condition = ?";
    $params[] = $condition;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Build ORDER BY clause
$orderBy = match($sort) {
    'price_low' => "COALESCE(l.price, l.current_bid) ASC",
    'price_high' => "COALESCE(l.price, l.current_bid) DESC",
    'ending_soon' => "l.auction_end ASC",
    'popular' => "l.views_count DESC",
    default => "l.created_at DESC"
};

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM listings l $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $limit);

// Get listings
$query = "
    SELECT l.*, 
           u.full_name as seller_name,
           (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM bids WHERE listing_id = l.id) as bid_count,
           (SELECT MAX(bid_amount) FROM bids WHERE listing_id = l.id) as highest_bid
    FROM listings l
    JOIN users u ON l.seller_id = u.id
    $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$limit, $offset]);
$listings = $db->fetchAll($query, $queryParams);

// Format listings
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
    'listings' => $formattedListings,
    'total' => $totalItems,
    'current_page' => $page,
    'total_pages' => $totalPages,
    'per_page' => $limit
]);