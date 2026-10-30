<?php
// marketplace/api/auth/register.php

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

$data = [
    'email' => sanitize($_POST['email'] ?? ''),
    'password' => $_POST['password'] ?? '',
    'full_name' => sanitize($_POST['full_name'] ?? ''),
    'user_type' => sanitize($_POST['user_type'] ?? 'buyer'),
    'phone' => sanitize($_POST['phone'] ?? ''),
    'address' => sanitize($_POST['address'] ?? '')
];

// Validate
$errors = [];

if (empty($data['email'])) {
    $errors['email'] = 'Email is required';
} elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address';
}

if (empty($data['password'])) {
    $errors['password'] = 'Password is required';
} elseif (strlen($data['password']) < 8) {
    $errors['password'] = 'Password must be at least 8 characters';
}

if (empty($data['full_name'])) {
    $errors['full_name'] = 'Full name is required';
}

if (!in_array($data['user_type'], ['buyer', 'seller'])) {
    $errors['user_type'] = 'Invalid user type';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $user = new User();
    $userId = $user->create($data);
    
    // Auto-login
    Session::set('user_id', $userId);
    Session::set('user_type', $data['user_type']);
    Session::set('full_name', $data['full_name']);
    Session::set('email', $data['email']);
    Session::set('email_verified', false);
    Session::regenerateId();

    // Send verification email
    $verificationCode = bin2hex(random_bytes(32));
    $user->update(['verification_code' => $verificationCode]);
    
    $verifyLink = SITE_URL . '/auth/verify-email.php?code=' . $verificationCode . '&email=' . urlencode($data['email']);
    
    // Send email (you can use PHPMailer or mail function)
    $emailSubject = 'Verify Your Email - ' . SITE_NAME;
    $emailMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background: #0d6efd; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Welcome to " . SITE_NAME . "!</h2>
                <p>Hi " . htmlspecialchars($data['full_name']) . ",</p>
                <p>Thank you for registering. Please verify your email address:</p>
                <a href='" . $verifyLink . "' class='button'>Verify Email</a>
                <p>Or copy this link: <a href='" . $verifyLink . "'>" . $verifyLink . "</a></p>
                <p>Best regards,<br>" . SITE_NAME . " Team</p>
            </div>
        </body>
        </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . SMTP_FROM . ">\r\n";
    
    @mail($data['email'], $emailSubject, $emailMessage, $headers);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please check your email to verify your account.',
        'redirect' => $data['user_type'] === 'seller' ? '/seller/dashboard.php' : '/buyer/dashboard.php'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}