<?php
// marketplace/buyer/cart.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireLogin();

$db = db();
$userId = Session::getUserId();

// Load cart
$cart = $db->fetchOne(
    "SELECT c.id as cart_id, c.seller_id, u.full_name as seller_name
     FROM carts c
     JOIN users u ON u.id = c.seller_id
     WHERE c.user_id = ?",
    [$userId]
);

$items = [];
$subtotal = 0;
$sellerName = '';
$allAvailable = true;

if ($cart) {
    $sellerName = $cart['seller_name'];
    $items = $db->fetchAll(
        "SELECT ci.listing_id, ci.added_at,
                l.title, l.price, l.sale_type, l.status as listing_status,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as image
         FROM cart_items ci
         JOIN listings l ON l.id = ci.listing_id
         WHERE ci.cart_id = ?
         ORDER BY ci.added_at ASC",
        [$cart['cart_id']]
    );

    foreach ($items as $item) {
        $subtotal += (float)($item['price'] ?? 0);
        if ($item['listing_status'] !== 'active') {
            $allAvailable = false;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <?php include __DIR__ . '/../includes/buyer-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-cart"></i> Your Cart</h1>
                <?php if (!empty($items)): ?>
                    <button id="clearCartBtn" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i> Clear Cart
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-cart display-1 text-muted"></i>
                    <h3 class="mt-3">Your cart is empty</h3>
                    <p class="text-muted">Browse listings and click "Add to Cart" to save them here.</p>
                    <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-primary">
                        <i class="bi bi-search"></i> Browse Items
                    </a>
                </div>
            <?php else: ?>

                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i>
                    All items in your cart are from
                    <strong><?php echo htmlspecialchars($sellerName); ?></strong>.
                    You'll check out once for all <?php echo count($items); ?> items.
                </div>

                <?php if (!$allAvailable): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        One or more items in your cart are no longer available. Please remove them before checking out.
                    </div>
                <?php endif; ?>

                <div class="card mb-3">
                    <div class="card-body p-0">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex align-items-center p-3 border-bottom cart-item" data-listing-id="<?php echo $item['listing_id']; ?>">
                                <img src="<?php echo getImageUrl($item['image'] ?? null, 'listing'); ?>"
                                     alt=""
                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px;">

                                <div class="ms-3 flex-grow-1">
                                    <a href="<?php echo SITE_URL; ?>/buyer/listing-detail.php?id=<?php echo $item['listing_id']; ?>"
                                       class="fw-bold text-decoration-none">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                    <div class="text-muted small">
                                        <i class="bi bi-tag"></i> <?php echo ucfirst($item['sale_type']); ?>
                                        <?php if ($item['listing_status'] !== 'active'): ?>
                                            <span class="badge bg-warning text-dark ms-2">No longer available</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-primary fw-bold mt-1">
                                        <?php echo formatNaira($item['price']); ?>
                                    </div>
                                </div>

                                <button class="btn btn-sm btn-outline-danger remove-item-btn"
                                        data-listing-id="<?php echo $item['listing_id']; ?>"
                                        title="Remove">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span>Items</span>
                            <span><?php echo count($items); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Platform Fee (<?php echo PLATFORM_FEE_PERCENTAGE; ?>%)</span>
                            <span class="text-muted"><?php echo formatNaira(calculateFee($subtotal, PLATFORM_FEE_PERCENTAGE)); ?></span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5 mt-2 pt-2 border-top">
                            <span>Total</span>
                            <span class="text-primary"><?php echo formatNaira($subtotal); ?></span>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <?php if ($allAvailable): ?>
                                <a href="<?php echo SITE_URL; ?>/buyer/checkout.php?from_cart=1"
                                   class="btn btn-success btn-lg">
                                    <i class="bi bi-lock"></i> Checkout Cart — <?php echo formatNaira($subtotal); ?>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg" disabled>
                                    <i class="bi bi-x-circle"></i> Remove Unavailable Items First
                                </button>
                            <?php endif; ?>

                            <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const csrfToken = <?php echo json_encode(Session::generateCSRFToken()); ?>;
const siteUrl   = <?php echo json_encode(SITE_URL); ?>;

// Remove single item
document.querySelectorAll('.remove-item-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Remove this item from your cart?')) return;
        const listingId = this.dataset.listingId;

        fetch(siteUrl + '/api/cart/remove.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'listing_id=' + encodeURIComponent(listingId)
                + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.message || 'Failed to remove');
        })
        .catch(() => alert('An error occurred'));
    });
});

// Clear cart
document.getElementById('clearCartBtn')?.addEventListener('click', function() {
    if (!confirm('Remove all items from your cart?')) return;

    fetch(siteUrl + '/api/cart/clear.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Failed to clear cart');
    })
    .catch(() => alert('An error occurred'));
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>