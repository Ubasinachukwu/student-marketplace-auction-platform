<?php
// marketplace/admin/dashboard.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// SAFE QUERY HELPERS
function safeCount($db, $sql) {
    try { return (int)($db->fetchOne($sql)['c'] ?? 0); } catch (Exception $e) { return 0; }
}
function safeSum($db, $sql) {
    try { return (float)($db->fetchOne($sql)['s'] ?? 0); } catch (Exception $e) { return 0.0; }
}

// STATS
$totalUsers     = safeCount($db, "SELECT COUNT(*) as c FROM users");
$totalListings  = safeCount($db, "SELECT COUNT(*) as c FROM listings");
$totalOrders    = safeCount($db, "SELECT COUNT(*) as c FROM orders");
$totalRevenue   = safeSum(  $db, "SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE payment_status='paid'");
$activeAuctions = safeCount($db, "SELECT COUNT(*) as c FROM listings WHERE sale_type IN ('auction','hybrid') AND status='active' AND auction_end > NOW()");

// RECENT ORDERS
$recentOrders = [];
try {
    $recentOrders = $db->fetchAll(
        "SELECT o.id, o.order_number, o.total_amount, o.payment_status, o.created_at, u.full_name as buyer_name
         FROM orders o LEFT JOIN users u ON o.buyer_id = u.id
         ORDER BY o.created_at DESC LIMIT 5"
    );
} catch (Exception $e) {}

// RECENT LISTINGS
$recentListings = [];
try {
    $recentListings = $db->fetchAll(
        "SELECT l.id, l.title, l.sale_type, l.price, l.current_bid, l.created_at,
                u.full_name as seller_name,
                (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image
         FROM listings l
         LEFT JOIN users u ON l.seller_id = u.id
         ORDER BY l.created_at DESC LIMIT 5"
    );
} catch (Exception $e) {}

$adminName = Session::get('full_name') ?? 'Admin';
$pageTitle = 'Dashboard | ' . SITE_NAME;

include __DIR__ . '/../includes/header.php';
?>

<!-- 🚨 FORCED INLINE CSS TO FIX CACHING AND DARK MODE ISSUES -->
<style>
    /* Base backgrounds */
    body { background-color: #f4f7f6; color: #1e293b; transition: background-color 0.2s, color 0.2s; }
    [data-bs-theme="dark"] body { background-color: #0f172a !important; color: #f8fafc !important; }

    /* Dashboard Cards */
    .dash-card-custom {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
    }
    [data-bs-theme="dark"] .dash-card-custom {
        background: #1e293b !important;
        border-color: #334155 !important;
    }

    /* Icon Colors */
    .icon-box {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; margin-bottom: 1rem;
    }
    .bg-blue { background: #e0f2fe; color: #0284c7; }
    .bg-purple { background: #f3e8ff; color: #9333ea; }
    .bg-orange { background: #ffedd5; color: #ea580c; }
    .bg-green { background: #dcfce7; color: #16a34a; }

    [data-bs-theme="dark"] .bg-blue { background: rgba(2,132,199,0.2); color: #38bdf8; }
    [data-bs-theme="dark"] .bg-purple { background: rgba(147,51,234,0.2); color: #c084fc; }
    [data-bs-theme="dark"] .bg-orange { background: rgba(234,88,12,0.2); color: #fb923c; }
    [data-bs-theme="dark"] .bg-green { background: rgba(22,163,74,0.2); color: #4ade80; }

    /* Fix the giant image issue */
    .thumb-fixed {
        width: 64px !important;
        height: 64px !important;
        max-width: 64px !important;
        max-height: 64px !important;
        object-fit: cover !important;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    [data-bs-theme="dark"] .thumb-fixed { border-color: #475569; }

    /* Force text colors in dark mode */
    [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, 
    [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6, 
    [data-bs-theme="dark"] strong, [data-bs-theme="dark"] .text-body {
        color: #f8fafc !important;
    }
    [data-bs-theme="dark"] .text-muted { color: #94a3b8 !important; }

    /* Main Cards (Live feed & tables) */
    .main-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
    [data-bs-theme="dark"] .main-panel { background: #1e293b !important; border-color: #334155 !important; }
    
    .border-bottom-custom { border-bottom: 1px solid #e2e8f0; }
    [data-bs-theme="dark"] .border-bottom-custom { border-color: #334155 !important; }
    
    /* Feed Items */
    .feed-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .feed-icon.bid { background: #3b82f6; color: white; }
    .feed-icon.order { background: #10b981; color: white; }
    .feed-icon.user { background: #f59e0b; color: white; }
    .feed-icon.listing { background: #8b5cf6; color: white; }
    .feed-icon.payment { background: #14b8a6; color: white; }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
            
            <!-- Top Bar -->
            <div class="d-flex justify-content-between align-items-center pt-3 pb-4 mb-2 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($adminName); ?> 👋</h2>
                    <p class="text-muted mb-0 small">Here is what's happening in your marketplace today.</p>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <button id="themeToggle" class="btn btn-outline-secondary rounded-circle p-2 d-flex justify-content-center align-items-center" style="width: 40px; height: 40px;" title="Toggle Dark/Light Mode">
                        <i class="bi bi-moon-fill" id="themeIcon"></i>
                    </button>
                    <span class="text-muted small d-none d-md-block"><i class="bi bi-calendar3"></i> <?php echo date('d M, Y'); ?></span>
                    <a href="<?php echo SITE_URL; ?>/admin/listings.php" class="btn btn-success rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg"></i> Manage Store
                    </a>
                </div>
            </div>

            <!-- Top Stats -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card-custom">
                        <div class="icon-box bg-blue"><i class="bi bi-people"></i></div>
                        <h3 class="fw-bold mb-1"><?php echo number_format($totalUsers); ?></h3>
                        <div class="text-muted small">Total Users</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card-custom">
                        <div class="icon-box bg-purple"><i class="bi bi-box-seam"></i></div>
                        <h3 class="fw-bold mb-1"><?php echo number_format($totalListings); ?></h3>
                        <div class="text-muted small">Total Listings</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card-custom">
                        <div class="icon-box bg-orange"><i class="bi bi-gavel"></i></div>
                        <h3 class="fw-bold mb-1"><?php echo number_format($activeAuctions); ?></h3>
                        <div class="text-muted small">Active Auctions</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card-custom">
                        <div class="icon-box bg-green"><i class="bi bi-cash-coin"></i></div>
                        <h3 class="fw-bold mb-1 text-success"><?php echo formatNaira($totalRevenue); ?></h3>
                        <div class="text-muted small">Total Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Activity & Products -->
            <div class="row g-4 mb-4">
                
                <!-- Live Feed -->
                <div class="col-lg-6">
                    <div class="main-panel h-100">
                        <div class="p-3 border-bottom-custom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-broadcast text-danger me-2"></i> Live Activity</h6>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">● LIVE</span>
                        </div>
                        <div class="p-3" style="height: 380px; overflow-y: auto;">
                            <div class="admin-live-feed" id="adminLiveFeed">
                                <div class="text-muted text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-success me-2"></div> Loading...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Products -->
                <div class="col-lg-6">
                    <div class="main-panel h-100">
                        <div class="p-3 border-bottom-custom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Recent Products</h6>
                            <a href="<?php echo SITE_URL; ?>/admin/listings.php" class="text-success text-decoration-none small fw-bold">View All</a>
                        </div>
                        <div class="p-2">
                            <?php if (empty($recentListings)): ?>
                                <div class="text-center text-muted py-4">No listings yet</div>
                            <?php else: ?>
                                <?php foreach ($recentListings as $l): 
                                    $isAuc = in_array($l['sale_type'], ['auction','hybrid']);
                                    $price = $l['sale_type'] === 'fixed' ? $l['price'] : $l['current_bid'];
                                    $img = getImageUrl($l['primary_image'] ?? null, 'listing');
                                ?>
                                    <div class="d-flex align-items-center p-2 border-bottom-custom">
                                        
                                        <!-- FIXED SMALL IMAGE -->
                                        <img src="<?php echo $img; ?>" class="thumb-fixed me-3" alt="Product">
                                        
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small text-truncate" style="max-width: 180px;">
                                                <?php echo htmlspecialchars($l['title']); ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars($l['seller_name'] ?? '—'); ?> • <?php echo timeAgo($l['created_at']); ?>
                                            </div>
                                        </div>

                                        <div class="text-end d-flex flex-column align-items-end gap-1">
                                            <div class="fw-bold text-success small"><?php echo formatNaira($price); ?></div>
                                            
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#productImageModal"
                                                    data-img="<?php echo $img; ?>"
                                                    data-title="<?php echo htmlspecialchars($l['title']); ?>"
                                                    data-price="<?php echo formatNaira($price); ?>"
                                                    data-seller="<?php echo htmlspecialchars($l['seller_name'] ?? 'Unknown'); ?>"
                                                    title="View Image">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="row">
                <div class="col-12">
                    <div class="main-panel">
                        <div class="p-3 border-bottom-custom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Recent Orders</h6>
                            <a href="<?php echo SITE_URL; ?>/admin/orders.php" class="text-success text-decoration-none small fw-bold">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4 border-0 text-muted small text-uppercase">Order #</th>
                                        <th class="border-0 text-muted small text-uppercase">Customer</th>
                                        <th class="border-0 text-muted small text-uppercase">Status</th>
                                        <th class="text-end pe-4 border-0 text-muted small text-uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-5 border-0">No orders yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $o): 
                                            $ps = $o['payment_status'] ?? 'pending';
                                            $badge = $ps === 'paid' ? 'success' : ($ps === 'failed' ? 'danger' : 'warning');
                                        ?>
                                            <tr>
                                                <td class="ps-4 border-bottom-custom fw-bold">#<?php echo htmlspecialchars($o['order_number']); ?></td>
                                                <td class="border-bottom-custom">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-secondary bg-opacity-10 rounded-circle d-flex justify-content-center align-items-center" style="width: 28px; height: 28px; font-size: 0.8rem; font-weight: bold;">
                                                            <?php echo substr($o['buyer_name'] ?? 'U', 0, 1); ?>
                                                        </div>
                                                        <span class="small"><?php echo htmlspecialchars($o['buyer_name'] ?? 'Guest'); ?></span>
                                                    </div>
                                                </td>
                                                <td class="border-bottom-custom">
                                                    <span class="badge bg-<?php echo $badge; ?> bg-opacity-10 text-<?php echo $badge; ?> rounded-pill">
                                                        <?php echo ucfirst($ps); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4 border-bottom-custom fw-bold text-success">
                                                    <?php echo formatNaira($o['total_amount']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL: Product Image Viewer -->
<div class="modal fade" id="productImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Product Name</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="" id="modalProductImage" alt="Product Large View" style="width: 100%; max-height: 450px; object-fit: contain; border-radius: 12px; background: #f8f9fa;">
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <span class="text-muted small">Seller: </span>
                        <strong id="modalSeller" class="text-body"></strong>
                    </div>
                    <h4 class="text-success mb-0 fw-bold" id="modalPrice"></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script>
// THEME TOGGLE FIX
(function() {
    const htmlEl = document.documentElement;
    const toggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    
    // Check local storage
    const storedTheme = localStorage.getItem('adminTheme');
    if (storedTheme === 'dark') {
        htmlEl.setAttribute('data-bs-theme', 'dark');
        if(themeIcon) themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
    }

    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (htmlEl.getAttribute('data-bs-theme') === 'dark') {
                htmlEl.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('adminTheme', 'light');
                themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
            } else {
                htmlEl.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('adminTheme', 'dark');
                themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
            }
        });
    }
})();

// MODAL POPULATOR
document.addEventListener('DOMContentLoaded', function() {
    const productModal = document.getElementById('productImageModal');
    if(productModal) {
        productModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('modalProductImage').src = button.getAttribute('data-img');
            document.getElementById('modalTitle').textContent = button.getAttribute('data-title');
            document.getElementById('modalPrice').textContent = button.getAttribute('data-price');
            document.getElementById('modalSeller').textContent = button.getAttribute('data-seller');
        });
    }
});

// LIVE ACTIVITY POLLING
(function() {
    const feedUrl = '<?php echo SITE_URL; ?>/api/admin/live-activity.php';
    const feedEl  = document.getElementById('adminLiveFeed');
    let lastSig   = '';
    let failCount = 0;

    function render(feed) {
        if (!feedEl) return;
        const sig = JSON.stringify(feed.map(f => f.text + f.time));
        if (sig === lastSig) return;
        lastSig = sig;

        if (!feed.length) {
            feedEl.innerHTML = '<div class="text-muted text-center py-5">No activity in the last 5 minutes</div>';
            return;
        }

        feedEl.innerHTML = feed.map(f => `
            <div class="feed-item d-flex align-items-start gap-3 pb-3 mb-3 border-bottom-custom">
                <div class="feed-icon ${f.type} rounded-circle d-flex justify-content-center align-items-center text-white flex-shrink-0">
                    <i class="bi bi-${f.icon || 'activity'}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="small text-body" style="line-height: 1.4;">${f.text}</div>
                    <div class="text-muted mt-1" style="font-size: 0.75rem;">${f.time}</div>
                </div>
            </div>
        `).join('');
    }

    function poll() {
        fetch(feedUrl + '?t=' + Date.now(), { cache: 'no-store' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                failCount = 0;
                if (data.success && Array.isArray(data.feed)) { render(data.feed); }
            })
            .catch(err => {
                failCount++;
                if (failCount === 1 && feedEl) {
                    feedEl.innerHTML = '<div class="text-danger small text-center py-3">⚠ Live feed offline</div>';
                }
            });
    }

    poll();
    setInterval(poll, 5000);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>