<?php
// marketplace/api/cart/count.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['count' => 0]);
    exit();
}

$db = db();
$userId = Session::getUserId();

$row = $db->fetchOne(
    "SELECT COUNT(ci.id) as c 
     FROM carts c 
     LEFT JOIN cart_items ci ON ci.cart_id = c.id 
     WHERE c.user_id = ?",
    [$userId]
);

echo json_encode(['count' => (int)($row['c'] ?? 0)]);