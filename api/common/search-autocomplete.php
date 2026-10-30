<?php
// marketplace/api/common/search-autocomplete.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => true, 'suggestions' => []]);
    exit();
}

try {
    // Search listings
    $listings = $db->fetchAll(
        "SELECT 
            id, 
            title, 
            slug,
            'listing' as type,
            price,
            current_bid,
            sale_type
         FROM listings 
         WHERE title LIKE ? AND status = 'active'
         LIMIT ?",
        ["%$query%", $limit]
    );

    // Search categories
    $categories = $db->fetchAll(
        "SELECT 
            id, 
            name, 
            slug,
            'category' as type
         FROM categories 
         WHERE name LIKE ? AND is_active = 1
         LIMIT ?",
        ["%$query%", 5]
    );

    // Format suggestions
    $suggestions = [];

    foreach ($listings as $listing) {
        $suggestions[] = [
            'id' => $listing['id'],
            'label' => $listing['title'],
            'type' => $listing['type'],
            'price' => $listing['sale_type'] == 'fixed' ? $listing['price'] : $listing['current_bid'],
            'sale_type' => $listing['sale_type'],
            'url' => '/buyer/listing-detail.php?id=' . $listing['id']
        ];
    }

    foreach ($categories as $category) {
        $suggestions[] = [
            'id' => $category['id'],
            'label' => $category['name'],
            'type' => $category['type'],
            'url' => '/buyer/browse.php?category=' . $category['id']
        ];
    }

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}