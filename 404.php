<?php
// marketplace/404.php
// Rendered when Apache returns a 404 Not Found (via ErrorDocument 404 in .htaccess)

// ---- API detection: return JSON, not HTML ----
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/api/') !== false) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found',
    ]);
    exit();
}

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';

http_response_code(404);

$pageTitle = 'Page Not Found | ' . (defined('SITE_NAME') ? SITE_NAME : 'MarketPlace');
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">
            <div class="display-1 fw-bold text-warning">404</div>
            <h2 class="mt-3">Page Not Found</h2>
            <p class="text-muted mt-3">
                The page you're looking for doesn't exist, has been moved,
                or is temporarily unavailable.
            </p>
            <div class="mt-4">
                <a href="<?php echo SITE_URL; ?>/" class="btn btn-primary">
                    <i class="bi bi-house"></i> Return to Homepage
                </a>
                <a href="<?php echo SITE_URL; ?>/buyer/browse.php" class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Browse Listings
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>