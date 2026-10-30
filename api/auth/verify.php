<?php
// marketplace/api/auth/verify.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';

header('Content-Type: application/json');

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
$code = isset($_POST['code']) ? sanitize($_POST['code']) : '';

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Email and verification code are required']);
    exit();
}

try {
    $user = new User();
    $user->loadByEmail($email); // You'll need to add this method
    
    if (!$user->getId()) {
        throw new Exception('User not found');
    }

    if ($user->isEmailVerified()) {
        echo json_encode(['success' => true, 'message' => 'Email already verified']);
        exit();
    }

    $user->verifyEmail($code);
    
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}