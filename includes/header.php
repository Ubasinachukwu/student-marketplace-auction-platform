<?php
// marketplace/includes/header.php

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = Session::isLoggedIn();
$userType = Session::getUserType();
$userName = Session::get('full_name');

// Cart count (for badge)
$cartCount = 0;
if ($isLoggedIn && $userType !== 'admin') {
    global $db;
    if (!isset($db)) { require_once __DIR__ . '/../config/database.php'; $db = db(); }
    $row = $db->fetchOne(
        "SELECT COUNT(ci.id) as c 
         FROM carts c 
         LEFT JOIN cart_items ci ON ci.cart_id = c.id 
         WHERE c.user_id = ?",
        [Session::getUserId()]
    );
    $cartCount = (int)($row['c'] ?? 0);
}

// =============================================
// SEO / META DEFAULTS
// Pages can override these before including header.php
// =============================================
$pageTitle       = $pageTitle       ?? (SITE_NAME . ' — Buy & Sell Used Items in Nigeria');
$pageDescription = $pageDescription ?? 'Buy and sell used items safely on ' . SITE_NAME . '. Great deals on electronics, fashion, furniture, vehicles, and more across Nigeria.';
$pageImage       = $pageImage       ?? (SITE_URL . '/assets/images/og-default.jpg');
$pageUrl         = $pageUrl         ?? currentUrl();
$pageType        = $pageType        ?? 'website';
$pageJSONLD      = $pageJSONLD      ?? null;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light"> <!-- ADDED THEME ATTRIBUTE -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ============================================= -->
    <!-- PRIMARY META TAGS                             -->
    <!-- ============================================= -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

    <!-- Canonical URL (prevents duplicate content) -->
    <link rel="canonical" href="<?php echo htmlspecialchars($pageUrl); ?>">


    <!-- Base URL -->
    <base href="<?php echo SITE_URL; ?>/">

    <!-- ============================================= -->
    <!-- OPEN GRAPH (Facebook, WhatsApp, LinkedIn)     -->
    <!-- ============================================= -->
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
    <meta property="og:type"      content="<?php echo htmlspecialchars($pageType); ?>">
    <meta property="og:title"     content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:url"       content="<?php echo htmlspecialchars($pageUrl); ?>">
    <meta property="og:image"     content="<?php echo htmlspecialchars($pageImage); ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale"    content="en_NG">

    <!-- ============================================= -->
    <!-- TWITTER CARD                                  -->
    <!-- ============================================= -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="twitter:image"       content="<?php echo htmlspecialchars($pageImage); ?>">

    <!-- ============================================= -->
    <!-- JSON-LD STRUCTURED DATA (Google Rich Results) -->
    <!-- ============================================= -->
    <?php if (!empty($pageJSONLD)): ?>
    <script type="application/ld+json">
    <?php echo json_encode($pageJSONLD, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    <?php endif; ?>

    <!-- ============================================= -->
    <!-- STYLESHEETS                                   -->
    <!-- ============================================= -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Core styles (ALL pages) -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/auction-effects.css" rel="stylesheet">

    <!-- Admin-only styles -->
    <?php if (isset($isAdminPage) && $isAdminPage): ?>
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    <?php endif; ?>

    <style>
        .bi, .fas, .far, .fab {
            display: inline-block;
            font-style: normal;
            font-weight: normal;
            line-height: 1;
        }
        #togglePassword { border-left: none; z-index: 10; }
        #togglePassword:hover { background-color: #f8f9fa; }
        #togglePassword:active { background-color: #e9ecef; }
        .input-group .btn i { font-size: 1.1rem; pointer-events: none; }
        .input-group-text i, .btn i, .nav-link i { font-size: 1.1rem; }

        .cart-link { position: relative; }
        .cart-badge {
            position: absolute;
            top: -4px;
            right: -6px;
            font-size: 0.65rem;
            padding: 2px 5px;
            border-radius: 10px;
        }
    </style>

    <!-- 🚨 THEME TOGGLE SCRIPT (Runs instantly before page draws) -->
    <script>
        (function() {
            const storedTheme = localStorage.getItem('adminTheme');
            if (storedTheme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
            <i class="bi bi-shop"></i> <?php echo SITE_NAME; ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <form class="d-flex mx-auto" style="flex: 1; max-width: 500px;" action="<?php echo SITE_URL; ?>/buyer/browse.php" method="GET">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" placeholder="Search items..." aria-label="Search">
                    <button class="btn btn-outline-light" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage == 'browse.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/buyer/browse.php">
                        <i class="bi bi-grid"></i> Browse
                    </a>
                </li>

                <?php if ($isLoggedIn && $userType !== 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link cart-link <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>"
                           href="<?php echo SITE_URL; ?>/buyer/cart.php">
                            <i class="bi bi-cart"></i> Cart
                            <?php if ($cartCount > 0): ?>
                                <span class="badge bg-danger cart-badge"><?php echo $cartCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <?php if ($userType == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                                <i class="bi bi-shield-lock"></i> Admin
                            </a>
                        </li>
                    <?php else: ?>
                        <?php if (Session::isSeller()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>/seller/create-listing.php">
                                    <i class="bi bi-plus-circle"></i> Sell
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="sellerMenu" data-bs-toggle="dropdown">
                                    <i class="bi bi-shop"></i> Seller
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/seller/dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Seller Dashboard
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/seller/manage-listings.php">
                                        <i class="bi bi-grid"></i> My Listings
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/seller/orders.php">
                                        <i class="bi bi-box"></i> Seller Orders
                                    </a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/buyer/dashboard.php">
                                <i class="bi bi-person"></i> My Account
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($userType != 'admin' && !Session::isSeller()): ?>
                                <li>
                                    <a class="dropdown-item text-success fw-bold" href="<?php echo SITE_URL; ?>/buyer/become-seller.php">
                                        <i class="bi bi-shop"></i> Become a Seller
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/buyer/settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/auth/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3" href="<?php echo SITE_URL; ?>/auth/register.php">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main>
    <div class="container mt-3">
        <?php echo displayFlashMessages(); ?>
    </div>