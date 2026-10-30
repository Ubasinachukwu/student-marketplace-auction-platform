<?php
// marketplace/api/common/upload.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require login
if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$type = isset($_POST['type']) ? sanitize($_POST['type']) : 'listing';
$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

// Determine upload directory
$uploadDir = match($type) {
    'profile' => PROFILE_IMAGES_PATH,
    'listing' => LISTING_IMAGES_PATH,
    default => LISTING_IMAGES_PATH
};

try {
    $result = uploadFile($file, $uploadDir);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'filename' => $result['filename'],
            'url' => getImageUrl($result['filename'], $type),
            'message' => 'File uploaded successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error']
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}