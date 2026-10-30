<?php
// marketplace/api/listings/update.php

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
    echo json_encode(['success' => false, 'message' => 'Only sellers can update listings']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Listing ID is required']);
    exit();
}

try {
    $listing = new Listing($listingId);
    
    if (!$listing->getId()) {
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        exit();
    }

    // Check ownership
    if ($listing->getSellerId() !== Session::getUserId()) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to update this listing']);
        exit();
    }

    // Prepare update data
    $updateData = [];
    
    if (isset($_POST['title'])) {
        $updateData['title'] = sanitize($_POST['title']);
    }
    if (isset($_POST['category_id'])) {
        $updateData['category_id'] = (int)$_POST['category_id'];
    }
    if (isset($_POST['description'])) {
        $updateData['description'] = sanitize($_POST['description']);
    }
    if (isset($_POST['condition'])) {
        $updateData['condition'] = sanitize($_POST['condition']);
    }
    if (isset($_POST['price']) && $listing->getSaleType() === 'fixed') {
        $updateData['price'] = (float)$_POST['price'];
    }
    if (isset($_POST['quantity'])) {
        $updateData['quantity'] = (int)$_POST['quantity'];
    }
    if (isset($_POST['is_featured'])) {
        $updateData['is_featured'] = (int)$_POST['is_featured'];
    }

    // Update listing
    $result = $listing->update($updateData);

    // Handle image uploads if any
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploadedFiles = $_FILES['images'];
        $imagePaths = [];

        for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
            if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $uploadedFiles['name'][$i],
                    'type' => $uploadedFiles['type'][$i],
                    'tmp_name' => $uploadedFiles['tmp_name'][$i],
                    'error' => $uploadedFiles['error'][$i],
                    'size' => $uploadedFiles['size'][$i]
                ];

                $uploadResult = uploadFile($file, LISTING_IMAGES_PATH);
                if ($uploadResult['success']) {
                    $imagePaths[] = $uploadResult['filename'];
                }
            }
        }

        if (!empty($imagePaths)) {
            $listing->addImages($imagePaths);
        }
    }

    // Handle image deletion
    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $imageId) {
            $listing->removeImage((int)$imageId);
        }
    }

    // Handle primary image
    if (isset($_POST['primary_image']) && is_numeric($_POST['primary_image'])) {
        $listing->setPrimaryImage((int)$_POST['primary_image']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Listing updated successfully',
        'listing' => $listing->toArray()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}