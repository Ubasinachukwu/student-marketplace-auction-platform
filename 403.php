<?php
// marketplace/403.php
// Rendered when Apache returns a 403 Forbidden (via ErrorDocument 403 in .htaccess)

// ---- API detection: return JSON, not HTML ----
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/api/') !== false) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access forbidden',
    ]);
    exit();
}

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/functions.php';

http_response_code(403);

$pageTitle = 'Access Forbidden | ' . (defined('SITE_NAME') ? SITE_NAME : 'MarketPlace');
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">
            <div class="display-1 fw-bold text-danger">403</div>
            <h2 class="mt-3">Access Forbidden</h2>
            <p class="text-muted mt-3">
                You don't have permission to access this page or resource.
                If you followed a link from within the site, please contact support.
            </p>
            <div class="mt-4">
                <a href="<?php echo SITE_URL; ?>/" class="btn btn-primary">
                    <i class="bi bi-house"></i> Return to Homepage
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>