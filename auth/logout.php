<?php
// marketplace/auth/logout.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// Destroy session
Session::destroy();

// Redirect to home with message
setInfo('You have been logged out successfully.');
redirect('/');
?>