<?php
// marketplace/buyer/place-bid.php (AJAX Handler)

// This file is already handled by api/auction/place-bid.php
// Redirect to API endpoint

require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/constants.php';

header('Location: ' . SITE_URL . '/api/auction/place-bid.php');
exit();
?>