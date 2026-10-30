<?php
// marketplace/api/listings/create.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Listing.php';

header('Content-Type: application/json');

// Require login and seller role
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if (Session::getUserType() !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Only sellers can create listings']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Handle file uploads
$imagePaths = [];
if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $uploadedFiles = $_FILES['images'];
    
    for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
        if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $uploadedFiles['name'][$i],
                'type' => $uploadedFiles['type'][$i],
                'tmp_name' => $uploadedFiles['tmp_name'][$i],
                'error' => $uploadedFiles['error'][$i],
                'size' => $uploadedFiles['size'][$i]
            ];

            $result = uploadFile($file, LISTING_IMAGES_PATH);
            if ($result['success']) {
                $imagePaths[] = $result['filename'];
            }
        }
    }
}

$data = [
    'seller_id' => Session::getUserId(),
    'title' => sanitize($_POST['title'] ?? ''),
    'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0,
    'description' => sanitize($_POST['description'] ?? ''),
    'condition' => sanitize($_POST['condition'] ?? 'good'),
    'sale_type' => sanitize($_POST['sale_type'] ?? 'fixed'),
    'price' => isset($_POST['price']) ? (float)$_POST['price'] : 0,
    'starting_bid' => isset($_POST['starting_bid']) ? (float)$_POST['starting_bid'] : 0,
    'reserve_price' => isset($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : 0,
    'bid_increment' => isset($_POST['bid_increment']) ? (float)$_POST['bid_increment'] : 1,
    'auction_duration' => isset($_POST['auction_duration']) ? (int)$_POST['auction_duration'] : 7,
    'auto_extend' => isset($_POST['auto_extend']) ? 1 : 0,
    'buy_it_now_price' => isset($_POST['buy_it_now_price']) ? (float)$_POST['buy_it_now_price'] : 0,
    'quantity' => isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1,
    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    'images' => $imagePaths
];

// Validate
$errors = [];

if (empty($data['title'])) {
    $errors['title'] = 'Title is required';
} elseif (strlen($data['title']) < 5) {
    $errors['title'] = 'Title must be at least 5 characters';
}

if (empty($data['description'])) {
    $errors['description'] = 'Description is required';
} elseif (strlen($data['description']) < 20) {
    $errors['description'] = 'Description must be at least 20 characters';
}

if ($data['sale_type'] === 'fixed' && $data['price'] <= 0) {
    $errors['price'] = 'Price must be greater than 0';
}

if (in_array($data['sale_type'], ['auction', 'hybrid']) && $data['starting_bid'] <= 0) {
    $errors['starting_bid'] = 'Starting bid must be greater than 0';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $listing = new Listing();
    $listingId = $listing->create($data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Listing created successfully',
        'listing_id' => $listingId,
        'redirect' => '/seller/manage-listings.php'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}