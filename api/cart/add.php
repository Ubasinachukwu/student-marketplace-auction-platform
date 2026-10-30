<?php
// marketplace/api/cart/add.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId    = Session::getUserId();
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$replace   = isset($_POST['replace']) && $_POST['replace'] == '1';

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit();
}

$listing = $db->fetchOne(
    "SELECT id, seller_id, status FROM listings WHERE id = ?",
    [$listingId]
);

if (!$listing) {
    echo json_encode(['success' => false, 'message' => 'Listing not found']);
    exit();
}

if ((int)$listing['seller_id'] === (int)$userId) {
    echo json_encode(['success' => false, 'message' => 'You cannot add your own listing to cart']);
    exit();
}

if ($listing['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'This listing is no longer available']);
    exit();
}

try {
    $db->beginTransaction();

    $cart = $db->fetchOne("SELECT id, seller_id FROM carts WHERE user_id = ?", [$userId]);

    // If cart exists but seller differs → require replace
    if ($cart && (int)$cart['seller_id'] !== (int)$listing['seller_id']) {
        if (!$replace) {
            $currentSeller = $db->fetchOne("SELECT full_name FROM users WHERE id = ?", [$cart['seller_id']]);
            $db->rollback();
            echo json_encode([
                'success'         => false,
                'conflict'        => true,
                'message'         => 'Your cart contains items from a different seller.',
                'current_seller'  => $currentSeller['full_name'] ?? 'another seller'
            ]);
            exit();
        }
        // Clear existing cart
        $db->delete("DELETE FROM cart_items WHERE cart_id = ?", [$cart['id']]);
        $db->update("UPDATE carts SET seller_id = ? WHERE id = ?", [$listing['seller_id'], $cart['id']]);
    }

    // Create cart if none
    if (!$cart) {
        $cartId = $db->insert(
            "INSERT INTO carts (user_id, seller_id) VALUES (?, ?)",
            [$userId, $listing['seller_id']]
        );
    } else {
        $cartId = $cart['id'];
    }

    // Add item (INSERT IGNORE prevents duplicates)
    $db->insert(
        "INSERT IGNORE INTO cart_items (cart_id, listing_id) VALUES (?, ?)",
        [$cartId, $listingId]
    );

    $count = $db->fetchOne(
        "SELECT COUNT(*) as c FROM cart_items WHERE cart_id = ?",
        [$cartId]
    )['c'] ?? 0;

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Added to cart',
        'count'   => (int)$count
    ]);

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) $db->rollback();
    error_log('Cart add error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
}