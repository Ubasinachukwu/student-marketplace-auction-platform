<?php
// marketplace/buyer/buy-now.php (AJAX Handler)

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
if (!Session::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db = db();
$userId = Session::getUserId();
$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if (!$listingId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit();
}

// Get listing details
$listing = $db->fetchOne(
    "SELECT * FROM listings WHERE id = ? AND status = 'active'",
    [$listingId]
);

if (!$listing) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Listing not found or not active']);
    exit();
}

if ($listing['seller_id'] == $userId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You cannot buy your own item']);
    exit();
}

// Check if it's a buy now listing
if ($listing['sale_type'] == 'fixed') {
    $price = $listing['price'];
} elseif ($listing['sale_type'] == 'hybrid' && $listing['buy_it_now_price'] > 0) {
    $price = $listing['buy_it_now_price'];
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Buy Now not available for this listing']);
    exit();
}

// Check if quantity is available
if ($listing['quantity'] <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'This item is out of stock']);
    exit();
}

// =============================================
// FIX: Build redirect URL using SITE_URL
// =============================================
$redirectUrl = SITE_URL . '/buyer/checkout.php?listing_id=' . $listingId . '&type=buynow&csrf_token=' . Session::generateCSRFToken();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'listing_id' => $listingId,
    'price' => $price,
    'price_formatted' => formatNaira($price),
    'redirect_url' => $redirectUrl
]);
?>