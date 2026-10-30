<?php
// marketplace/api/common/get-subcategories.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

$db = db();
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (!$categoryId) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$subCategories = $db->fetchAll(
    "SELECT id, name FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order",
    [$categoryId]
);

header('Content-Type: application/json');
echo json_encode($subCategories);