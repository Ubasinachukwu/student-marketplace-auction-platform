<?php
// marketplace/api/auth/login.php

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

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember']) ? true : false;

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

try {
    $user = User::login($email, $password);
    
    // Set session
    Session::set('user_id', $user->getId());
    Session::set('user_type', $user->getUserType());
    Session::set('full_name', $user->getFullName());
    Session::set('email', $user->getEmail());
    Session::set('email_verified', $user->isEmailVerified());
    Session::regenerateId();

    // Remember me
    if ($remember) {
        $token = generateToken(64);
        $expires = time() + (86400 * 30);
        setcookie('remember_token', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
        // Store token in database (you'll need a remember_tokens table)
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'full_name' => $user->getFullName(),
            'user_type' => $user->getUserType(),
            'email_verified' => $user->isEmailVerified()
        ],
        'redirect' => $user->getUserType() === 'admin' ? '/admin/dashboard.php' : 
                      ($user->getUserType() === 'seller' ? '/seller/dashboard.php' : '/buyer/dashboard.php')
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}