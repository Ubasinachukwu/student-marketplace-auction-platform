<?php
// marketplace/index.php

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/database.php';

$db = db();

/**
 * Format values safely for HTML.
 */
if (!function_exists('homeEscape')) {
    function homeEscape($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Return the visual label and colour for a listing type.
 */
if (!function_exists('homeSaleType')) {
    function homeSaleType($type): array
    {
        switch (strtolower((string)$type)) {
            case 'auction':
                return ['Auction', 'auction'];
            case 'hybrid':
                return ['Hybrid', 'hybrid'];
            case 'fixed':
            default:
                return ['Fixed Price', 'fixed'];
        }
    }
}

/**
 * Select a meaningful price without inventing values.
 */
if (!function_exists('homeListingPrice')) {
    function homeListingPrice(array $listing): array
    {
        $type = strtolower((string)($listing['sale_type'] ?? 'fixed'));

        if ($type === 'auction') {
            $amount = $listing['current_bid'] ?? null;
            $label = ($amount !== null && (float)$amount > 0)
                ? 'Current bid'
                : 'Starting price';

            if ($amount === null || (float)$amount <= 0) {
                $amount = $listing['starting_bid'] ?? $listing['price'] ?? null;
            }
        } elseif ($type === 'hybrid') {
            $amount = $listing['current_bid'] ?? $listing['price'] ?? null;
            $label = 'Current Bid';
        } else {
            $amount = $listing['price'] ?? null;
            $label = 'Price';
        }

        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return ['Price unavailable', $label, false];
        }

        return [formatNaira((float)$amount), $label, true];
    }
}

// Featured listings
$featuredListings = $db->fetchAll(
    "SELECT l.*, u.full_name AS seller_name,
            (SELECT li.image_url
             FROM listing_images li
             WHERE li.listing_id = l.id AND li.is_primary = 1
             LIMIT 1) AS primary_image
     FROM listings l
     LEFT JOIN users u ON l.seller_id = u.id
     WHERE l.is_featured = 1 AND l.status = 'active'
     ORDER BY l.created_at DESC
     LIMIT 6"
);

// Recent listings
$recentListings = $db->fetchAll(
    "SELECT l.*, u.full_name AS seller_name,
            (SELECT li.image_url
             FROM listing_images li
             WHERE li.listing_id = l.id AND li.is_primary = 1
             LIMIT 1) AS primary_image
     FROM listings l
     LEFT JOIN users u ON l.seller_id = u.id
     WHERE l.status = 'active'
     ORDER BY l.created_at DESC
     LIMIT 12"
);

// Active top-level categories
$categories = $db->fetchAll(
    "SELECT *
     FROM categories
     WHERE parent_id IS NULL AND is_active = 1
     ORDER BY sort_order
     LIMIT 6"
);

// 🚨 CAROUSEL: Fetch Top 3 Auctions ending soon
$heroAuctions = $db->fetchAll(
    "SELECT l.*, u.full_name AS seller_name,
            (SELECT li.image_url FROM listing_images li WHERE li.listing_id = l.id AND li.is_primary = 1 LIMIT 1) AS primary_image
     FROM listings l 
     LEFT JOIN users u ON l.seller_id = u.id 
     WHERE l.sale_type IN ('auction', 'hybrid') AND l.status = 'active' AND l.auction_end > NOW()
     ORDER BY l.auction_end ASC LIMIT 3"
);

// Fallback if no live auctions
if (empty($heroAuctions)) {
    $fallback = $featuredListings[0] ?? $recentListings[0] ?? null;
    if ($fallback) { $heroAuctions[] = $fallback; }
}

// SEO metadata
$pageTitle = SITE_NAME . ' | Bid Smart, Get More';
$pageDescription = 'Discover student-friendly deals on used items. Browse fixed-price products, bid in auctions, or buy instantly on ' . SITE_NAME . '.';
$pageImage = SITE_URL . '/assets/images/og-homepage.jpg';
$pageUrl = SITE_URL . '/';
$pageType = 'website';

include __DIR__ . '/includes/header.php';
?>

<main class="market-home">

    <!-- HERO -->
    <section class="market-hero">
        <div class="market-hero-glow market-hero-glow-one"></div>
        <div class="market-hero-glow market-hero-glow-two"></div>

        <!-- Left Side: Copy -->
        <div class="market-hero-copy">
            <span class="market-eyebrow">
                <i class="bi bi-stars"></i> THE STUDENT MARKETPLACE
            </span>

            <h1>Bid Smart,<br><span>Get More!</span></h1>

            <p>Find great deals, unique items and quality products from fellow students at student-friendly prices.</p>

            <div class="market-trust-points">
                <span><i class="bi bi-shield-check"></i> Safer trading</span>
                <span><i class="bi bi-people-fill"></i> Student sellers</span>
                <span><i class="bi bi-lightning-charge-fill"></i> Easy browsing</span>
            </div>

            <div class="market-hero-actions">
                <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-btn market-btn-gold">
                    Explore Listings <i class="bi bi-arrow-right"></i>
                </a>

                <?php
                $sellUrl = Session::isLoggedIn() ? SITE_URL . '/seller/create-listing.php' : SITE_URL . '/auth/login.php';
                ?>
                <a href="<?php echo homeEscape($sellUrl); ?>" class="market-btn market-btn-glass">
                    <i class="bi bi-plus-circle"></i> Sell an Item
                </a>
            </div>
        </div>

        <!-- Center: Bootstrap Carousel -->
        <div class="market-hero-feature">
            <?php if (!empty($heroAuctions)): ?>
                
                <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                    <!-- Indicators -->
                    <div class="carousel-indicators">
                        <?php foreach ($heroAuctions as $index => $auction): ?>
                            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $index + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slides -->
                    <div class="carousel-inner" style="border-radius: 21px; overflow: hidden;">
                        <?php foreach ($heroAuctions as $index => $auction): 
                            [$heroTypeLabel, $heroTypeClass] = homeSaleType($auction['sale_type'] ?? 'fixed');
                            [$heroPrice, $heroPriceLabel, $heroHasPrice] = homeListingPrice($auction);
                            $heroImage = getImageUrl($auction['primary_image'] ?? null, 'listing');
                            $isAuction = in_array($auction['sale_type'], ['auction', 'hybrid']) && !empty($auction['auction_end']) && strtotime($auction['auction_end']) > time();
                        ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                
                                <div class="hero-feature-image-wrap" style="height: 250px;">
                                    <div class="hero-live-pill">
                                        <i class="bi bi-lightning-charge-fill"></i> <?php echo $isAuction ? 'LIVE AUCTION' : 'FEATURED LISTING'; ?>
                                    </div>
                                    <span class="sale-badge sale-badge-<?php echo homeEscape($heroTypeClass); ?>">
                                        <?php echo homeEscape($heroTypeLabel); ?>
                                    </span>
                                    <a href="<?php echo homeEscape(listingUrl($auction)); ?>">
                                        <img class="hero-feature-image" src="<?php echo homeEscape($heroImage); ?>" alt="<?php echo homeEscape($auction['title'] ?? 'Featured product'); ?>" style="object-fit: cover;">
                                    </a>
                                </div>

                                <div class="hero-feature-details" style="background: white;">
                                    <div>
                                        <span class="hero-product-kicker">TODAY'S PICK</span>
                                        <h2 class="text-truncate"><?php echo homeEscape($auction['title'] ?? 'Featured item'); ?></h2>
                                        <p class="hero-seller">
                                            <i class="bi bi-person-circle"></i> Seller: <?php echo homeEscape($auction['seller_name'] ?? 'Marketplace seller'); ?>
                                        </p>
                                    </div>

                                    <div class="hero-price-block">
                                        <span><?php echo homeEscape($heroPriceLabel); ?></span>
                                        <strong><?php echo $heroHasPrice ? homeEscape($heroPrice) : 'Contact seller'; ?></strong>
                                    </div>

                                    <?php if ($isAuction): ?>
                                        <!-- THE LIVE COUNTDOWN TIMER -->
                                        <div class="p-2 rounded text-center mb-2" style="background:#071735; color:white; grid-column: 1 / -1;">
                                            <div class="small text-white-50">Ends In</div>
                                            <div class="hero-timer-target fw-bold fs-5 text-warning" data-end="<?php echo $auction['auction_end']; ?>">--:--:--</div>
                                        </div>
                                    <?php endif; ?>

                                    <a href="<?php echo homeEscape(listingUrl($auction)); ?>" class="market-btn market-btn-purple">
                                        <?php echo $isAuction ? 'Place Bid' : 'View Listing'; ?> <i class="bi bi-arrow-up-right"></i>
                                    </a>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Manual Controls (Arrows) -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" style="left: -15px; width: 40px; filter: drop-shadow(0px 0px 4px rgba(0,0,0,0.5));">
                        <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" style="right: -15px; width: 40px; filter: drop-shadow(0px 0px 4px rgba(0,0,0,0.5));">
                        <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>

            <?php else: ?>
                <div class="hero-empty-state">
                    <div class="hero-empty-icon"><i class="bi bi-bag-heart"></i></div>
                    <h2>Your next great deal starts here.</h2>
                    <p>Explore listings from students and discover something useful.</p>
                    <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-btn market-btn-purple">Browse Listings <i class="bi bi-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Features -->
        <div class="market-hero-types">
            <div class="hero-type-item">
                <span class="hero-type-icon type-icon-auction"><i class="bi bi-hammer"></i></span>
                <div>
                    <strong>Auction</strong>
                    <p>Compete with bids on listed items.</p>
                </div>
            </div>

            <div class="hero-type-item">
                <span class="hero-type-icon type-icon-fixed"><i class="bi bi-tag-fill"></i></span>
                <div>
                    <strong>Fixed Price</strong>
                    <p>Buy at the seller's listed price.</p>
                </div>
            </div>

            <div class="hero-type-item">
                <!-- FIXED HANDSHAKE HERE -->
                <span class="hero-type-icon type-icon-hybrid"><i class="bi bi-arrow-left-right"></i></span>
                <div>
                    <strong>Hybrid</strong>
                    <p>Bid or buy instantly when available.</p>
                </div>
            </div>

            <p class="hero-tagline">Great items. Real students. Better deals.</p>
        </div>
    </section>

    <!-- 🔴 LIVE BID TICKER (RESTORED) -->
   <!-- <section class="my-4">
        <div class="live-ticker" style="background: #071735; border-radius: 20px; border: 1px solid #102852;">
            <div class="d-flex align-items-center gap-3 px-3">
                <span class="badge bg-danger flex-shrink-0"><i class="bi bi-broadcast"></i> LIVE</span>
                <div class="live-ticker-inner flex-grow-1" id="liveTicker">
                    <span class="live-ticker-item text-white-50">Loading live bids...</span>
                </div>
            </div>
        </div>
    </section>-->

    <!-- CATEGORIES -->
    <section class="market-section market-category-section">
        <div class="market-section-heading">
            <div>
                <span class="market-section-kicker">FIND YOUR NEXT FAVOURITE</span>
                <h2>Shop by Category</h2>
                <p>From everyday essentials to useful second-hand finds.</p>
            </div>
            <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-view-all">View all <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php
        $categoryThemes = [
            ['category-blue', 'bi-laptop'], ['category-peach', 'bi-house-door-fill'],
            ['category-mint', 'bi-fan'], ['category-lilac', 'bi-bag-fill'],
            ['category-yellow', 'bi-cup-hot-fill'], ['category-pink', 'bi-grid-3x3-gap-fill'],
        ];
        ?>

        <div class="market-category-grid">
            <?php foreach ($categories as $index => $cat): ?>
                <?php
                $theme = $categoryThemes[$index % count($categoryThemes)];
                $icon = getCategoryIcon($cat['slug'] ?? '');
                if (empty($icon)) { $icon = $theme[1]; }
                ?>
                <a class="market-category-card <?php echo homeEscape($theme[0]); ?>" href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php?category=<?php echo (int)$cat['id']; ?>">
                    <span class="market-category-icon"><i class="bi <?php echo homeEscape($icon); ?>"></i></span>
                    <strong><?php echo homeEscape($cat['name'] ?? 'Category'); ?></strong>
                    <span class="category-explore">Explore <i class="bi bi-arrow-up-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- PROMO STRIP -->
    <section class="market-promo-strip">
        <div class="market-promo-art"><i class="bi bi-cart-check-fill"></i></div>
        <div>
            <span>BUY SMARTER. SELL EASIER.</span>
            <h2>Quality used items at <strong>student-friendly prices.</strong></h2>
            <p>Sell <b>•</b> Bid <b>•</b> Buy <b>•</b> Save</p>
        </div>
        <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-promo-arrow" aria-label="Browse marketplace">
            <i class="bi bi-arrow-right"></i>
        </a>
    </section>

    <!-- 🚨 RECENT LISTINGS (Moved Up!) -->
    <section class="market-section">
        <div class="market-section-heading">
            <div>
                <span class="market-section-kicker">FRESH ON THE MARKET</span>
                <h2><i class="bi bi-fire market-heading-icon"></i> Recent Listings</h2>
                <p>See what students have listed recently.</p>
            </div>
            <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-view-all">View all <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php if (!empty($recentListings)): ?>
            <div class="market-product-grid">
                <?php foreach ($recentListings as $listing): ?>
                    <?php
                    [$typeLabel, $typeClass] = homeSaleType($listing['sale_type'] ?? 'fixed');
                    [$price, $priceLabel, $hasPrice] = homeListingPrice($listing);
                    $image = getImageUrl($listing['primary_image'] ?? null, 'listing');
                    ?>
                    <article class="market-product-card">
                        <div class="market-product-image-wrap">
                            <a href="<?php echo homeEscape(listingUrl($listing)); ?>">
                                <img src="<?php echo homeEscape($image); ?>" alt="<?php echo homeEscape($listing['title'] ?? 'Product'); ?>" class="market-product-image" loading="lazy">
                            </a>
                            <span class="sale-badge sale-badge-<?php echo homeEscape($typeClass); ?>"><?php echo homeEscape($typeLabel); ?></span>
                        </div>

                        <div class="market-product-body">
                            <p class="market-product-seller"><i class="bi bi-person-circle"></i> <?php echo homeEscape($listing['seller_name'] ?? 'Marketplace seller'); ?></p>
                            <h3><a href="<?php echo homeEscape(listingUrl($listing)); ?>"><?php echo homeEscape($listing['title'] ?? 'Untitled listing'); ?></a></h3>

                            <div class="market-product-price-row">
                                <div>
                                    <span class="market-price-caption"><?php echo homeEscape($priceLabel); ?></span>
                                    <strong class="market-product-price"><?php echo $hasPrice ? homeEscape($price) : 'Price unavailable'; ?></strong>
                                </div>
                                <span class="market-price-icon"><i class="bi bi-arrow-up-right"></i></span>
                            </div>

                            <a href="<?php echo homeEscape(listingUrl($listing)); ?>" class="market-product-action action-<?php echo homeEscape($typeClass); ?>">
                                <?php echo $typeClass === 'auction' ? 'View Auction' : ($typeClass === 'hybrid' ? 'View Buy / Bid Options' : 'View Item'); ?>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="market-no-listings">
                <i class="bi bi-bag-x"></i>
                <h3>No active listings yet</h3>
                <p>Be the first to add an item to the marketplace.</p>
                <a class="market-btn market-btn-purple" href="<?php echo homeEscape($sellUrl); ?>">List an Item</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- FEATURED LISTINGS -->
    <?php if (!empty($featuredListings)): ?>
    <section class="market-section">
        <div class="market-section-heading">
            <div>
                <span class="market-section-kicker">HAND-PICKED FOR YOU</span>
                <h2><i class="bi bi-stars market-heading-icon"></i> Featured Listings</h2>
                <p>Standout finds from your student marketplace.</p>
            </div>
            <a href="<?php echo homeEscape(SITE_URL); ?>/buyer/browse.php" class="market-view-all">View all <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="market-product-grid">
            <?php foreach ($featuredListings as $listing): ?>
                <?php
                [$typeLabel, $typeClass] = homeSaleType($listing['sale_type'] ?? 'fixed');
                [$price, $priceLabel, $hasPrice] = homeListingPrice($listing);
                $image = getImageUrl($listing['primary_image'] ?? null, 'listing');
                ?>
                <article class="market-product-card">
                    <div class="market-product-image-wrap">
                        <a href="<?php echo homeEscape(listingUrl($listing)); ?>">
                            <img src="<?php echo homeEscape($image); ?>" alt="<?php echo homeEscape($listing['title'] ?? 'Product'); ?>" class="market-product-image" loading="lazy">
                        </a>
                        <span class="sale-badge sale-badge-<?php echo homeEscape($typeClass); ?>"><?php echo homeEscape($typeLabel); ?></span>
                        <span class="market-featured-star"><i class="bi bi-star-fill"></i></span>
                    </div>

                    <div class="market-product-body">
                        <p class="market-product-seller"><i class="bi bi-person-circle"></i> <?php echo homeEscape($listing['seller_name'] ?? 'Marketplace seller'); ?></p>
                        <h3><a href="<?php echo homeEscape(listingUrl($listing)); ?>"><?php echo homeEscape($listing['title'] ?? 'Untitled listing'); ?></a></h3>

                        <div class="market-product-price-row">
                            <div>
                                <span class="market-price-caption"><?php echo homeEscape($priceLabel); ?></span>
                                <strong class="market-product-price"><?php echo $hasPrice ? homeEscape($price) : 'Price unavailable'; ?></strong>
                            </div>
                            <span class="market-price-icon"><i class="bi bi-arrow-up-right"></i></span>
                        </div>

                        <a href="<?php echo homeEscape(listingUrl($listing)); ?>" class="market-product-action action-<?php echo homeEscape($typeClass); ?>">
                            <?php echo $typeClass === 'auction' ? 'View Auction' : ($typeClass === 'hybrid' ? 'View Buy / Bid Options' : 'View Item'); ?>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- HOW IT WORKS -->
    <section class="market-how-section">
        <div class="market-section-heading market-how-heading">
            <div>
                <span class="market-section-kicker">GET STARTED IN MINUTES</span>
                <h2>How It Works</h2>
                <p>Your next great deal is only a few steps away.</p>
            </div>
        </div>
        <div class="market-how-grid">
            <article class="market-how-card">
                <span class="market-how-number number-blue">01</span>
                <span class="market-how-icon"><i class="bi bi-person-plus-fill"></i></span>
                <h3>Create an Account</h3>
                <p>Register to explore listings and connect with the marketplace.</p>
            </article>
            <article class="market-how-card">
                <span class="market-how-number number-purple">02</span>
                <span class="market-how-icon"><i class="bi bi-search"></i></span>
                <h3>Browse or List</h3>
                <p>Find something useful or publish an item you want to sell.</p>
            </article>
            <article class="market-how-card">
                <span class="market-how-number number-green">03</span>
                <span class="market-how-icon"><i class="bi bi-bag-check-fill"></i></span>
                <h3>Buy, Bid or Sell</h3>
                <p>Choose the available purchase or bidding option for each item.</p>
            </article>
        </div>
    </section>

    <!-- SELLER CTA -->
    <section class="market-seller-cta">
        <div class="market-seller-cta-icon"><i class="bi bi-shop-window"></i></div>
        <div>
            <span>HAVE SOMETHING TO SELL?</span>
            <h2>Give your items a new home.</h2>
            <p>List your item and connect with potential student buyers.</p>
        </div>
        <a href="<?php echo homeEscape($sellUrl); ?>" class="market-btn market-btn-gold">Start Selling <i class="bi bi-arrow-right"></i></a>
    </section>

</main>

<!-- HERO CAROUSEL TIMER SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find all timer elements inside the carousel
    const timerElements = document.querySelectorAll('.hero-timer-target');
    
    function updateTimers() {
        const now = new Date().getTime();
        
        timerElements.forEach(el => {
            const endStr = el.getAttribute('data-end');
            if (!endStr) return;
            
            const endTime = new Date(endStr.replace(' ', 'T')).getTime();
            const distance = endTime - now;

            if (distance <= 0) {
                el.innerHTML = 'Ended';
                return;
            }
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((distance % (1000 * 60)) / 1000);
            el.innerHTML = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        });
    }
    
    if (timerElements.length > 0) {
        updateTimers();
        setInterval(updateTimers, 1000);
    }
});
</script>

<!-- LIVE BID TICKER — POLLING JS -->
<script>
(function() {
    const tickerUrl = '<?php echo SITE_URL; ?>/api/auction/live-activity.php';
    const tickerEl  = document.getElementById('liveTicker');
    if (!tickerEl) return;
    let lastSig = '', failCount = 0;

    function renderTicker(data) {
        if (!data.recent_bids || !data.recent_bids.length) {
            const msg = '<span class="live-ticker-item text-white-50">No bids in the last hour — be the first!</span>';
            if (tickerEl.innerHTML !== msg) tickerEl.innerHTML = msg;
            return;
        }
        const doubled = data.recent_bids.concat(data.recent_bids);
        const html = doubled.map(b => `
            <span class="live-ticker-item">
                <i class="bi bi-gavel text-warning"></i>
                <strong>${b.bidder}</strong> bid
                <span class="ticker-price">${b.amount}</span>
                on <a href="${b.url}" class="text-white text-decoration-none">${b.title}</a>
                <small class="text-white-50">${b.time}</small>
            </span>
        `).join('');
        const sig = JSON.stringify(data.recent_bids.map(b => b.bidder + b.amount + b.time));
        if (sig !== lastSig) { tickerEl.innerHTML = html; lastSig = sig; }
    }

    function pollTicker() {
        fetch(tickerUrl + '?t=' + Date.now(), { cache: 'no-store' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => { failCount = 0; if (data.success) renderTicker(data); })
            .catch(err => {
                failCount++;
                if (failCount === 1) { tickerEl.innerHTML = '<span class="live-ticker-item text-warning">⚠ Live ticker offline</span>'; }
            });
    }
    pollTicker();
    setInterval(pollTicker, 10000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>