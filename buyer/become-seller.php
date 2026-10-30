<?php
// marketplace/buyer/become-seller.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireLogin();

// Admins shouldn't see this page
if (Session::isAdmin()) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

// Already a seller? Go to seller dashboard
if (Session::isSeller()) {
    redirect(SITE_URL . '/seller/dashboard.php');
}

$db = db();
$userId = Session::getUserId();
$status = Session::getSellerStatus();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif ($status === 'pending') {
        $error = 'Your application is already pending approval.';
    } elseif ($status === 'active') {
        redirect(SITE_URL . '/seller/dashboard.php');
    } else {
        try {
            $db->update(
                "UPDATE users 
                 SET is_seller = 1, 
                     seller_status = 'pending', 
                     seller_rejected_reason = NULL
                 WHERE id = ?",
                [$userId]
            );

            // Notify admins
            $admins = $db->fetchAll("SELECT id FROM users WHERE user_type = 'admin'");
            $userRow = Session::getUser();
            foreach ($admins as $admin) {
                $db->insert(
                    "INSERT INTO notifications (user_id, type, title, message, link) 
                     VALUES (?, 'seller_request', 'New Seller Application', 
                             CONCAT(?, ' has applied to become a seller.'), 
                             '/admin/pending-sellers.php')",
                    [$admin['id'], $userRow['full_name'] ?? 'A user']
                );
            }

            setSuccess('Your seller application has been submitted! An admin will review it shortly.');
            redirect(SITE_URL . '/buyer/become-seller.php');

        } catch (Exception $e) {
            error_log('Become seller error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$csrfToken = Session::generateCSRFToken();
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-shop"></i> Become a Seller</h4>
                </div>
                <div class="card-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($status === 'pending'): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Application Pending</h3>
                            <p class="text-muted">
                                Your seller application is being reviewed by our team.
                                We'll notify you once it's approved — usually within 24 hours.
                            </p>
                            <a href="<?php echo SITE_URL; ?>/buyer/dashboard.php" class="btn btn-outline-primary mt-3">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>

                    <?php elseif ($status === 'rejected'): ?>
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-x-circle"></i> Application Not Approved</h5>
                            <p class="mb-0">Your previous seller application was not approved. You may apply again.</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-arrow-clockwise"></i> Re-apply as Seller
                            </button>
                        </form>

                    <?php else: ?>
                        <p class="lead">Start selling on <?php echo SITE_NAME; ?>.</p>
                        <p class="text-muted">
                            One account, both roles. Your buyer account stays the same — selling just adds a new capability.
                        </p>

                        <h6 class="mt-4">As a seller, you can:</h6>
                        <ul class="mb-4">
                            <li><i class="bi bi-check-circle text-success"></i> List items for fixed price, auction, or hybrid</li>
                            <li><i class="bi bi-check-circle text-success"></i> Receive orders and manage shipping</li>
                            <li><i class="bi bi-check-circle text-success"></i> Message buyers directly</li>
                            <li><i class="bi bi-check-circle text-success"></i> Get paid for your sales</li>
                        </ul>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Please note:</strong> Seller applications are reviewed by an admin to ensure quality.
                            You'll be notified once approved.
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="d-flex gap-2">
                                <a href="<?php echo SITE_URL; ?>/buyer/dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-shop"></i> Apply to Become a Seller
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>