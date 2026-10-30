<?php
// marketplace/sitemap.php
// Dynamic XML sitemap for search engines.

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/database.php';

$db = db();

// Tell the browser this is XML
header('Content-Type: application/xml; charset=utf-8');

// Build the XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// =============================================
// 1. STATIC PAGES
// =============================================
$staticPages = [
    ['url' => SITE_URL . '/',                          'priority' => '1.0', 'freq' => 'daily'],
    ['url' => SITE_URL . '/buyer/browse.php',          'priority' => '0.9', 'freq' => 'hourly'],
    ['url' => SITE_URL . '/buyer/become-seller.php',   'priority' => '0.7', 'freq' => 'weekly'],
    ['url' => SITE_URL . '/auth/register.php',         'priority' => '0.6', 'freq' => 'monthly'],
    ['url' => SITE_URL . '/auth/login.php',            'priority' => '0.4', 'freq' => 'monthly'],
];

foreach ($staticPages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($page['url']) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>" . $page['freq'] . "</changefreq>\n";
    echo "    <priority>" . $page['priority'] . "</priority>\n";
    echo "  </url>\n";
}

// =============================================
// 2. PARENT CATEGORIES
// =============================================
$categories = $db->fetchAll(
    "SELECT id, name, slug, updated_at 
     FROM categories 
     WHERE parent_id IS NULL AND is_active = 1 
     ORDER BY sort_order"
);

foreach ($categories as $cat) {
    $url  = SITE_URL . '/buyer/browse.php?category=' . (int)$cat['id'];
    $date = !empty($cat['updated_at']) ? date('Y-m-d', strtotime($cat['updated_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "    <lastmod>" . $date . "</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

// =============================================
// 3. ACTIVE LISTINGS
// =============================================
// Limit to keep the sitemap fast — increase as you scale
$listings = $db->fetchAll(
    "SELECT id, title, slug, updated_at 
     FROM listings 
     WHERE status = 'active' 
     ORDER BY updated_at DESC 
     LIMIT 1000"
);

foreach ($listings as $listing) {
    $url  = listingUrl($listing);
    $date = !empty($listing['updated_at']) ? date('Y-m-d', strtotime($listing['updated_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "    <lastmod>" . $date . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
exit();