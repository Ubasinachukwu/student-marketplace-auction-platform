<?php
// marketplace/api/admin/listings.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require admin role — inline, JSON-safe
if (!Session::isLoggedIn() || !Session::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// CSRF Protection
if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db        = db();
$action    = isset($_POST['action'])     ? sanitize($_POST['action'])     : '';
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id']      : 0;
$reason    = isset($_POST['reason'])     ? sanitize($_POST['reason'])     : '';

if (!$action || !$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $db->beginTransaction();

    // Get listing info
    $listing = $db->fetchOne(
        "SELECT l.*, u.full_name AS seller_name 
         FROM listings l 
         JOIN users u ON l.seller_id = u.id 
         WHERE l.id = ?",
        [$listingId]
    );

    if (!$listing) {
        throw new Exception('Listing not found');
    }

    $message = '';

    switch ($action) {
        case 'approve':
            $db->update(
                "UPDATE listings SET status = 'active' WHERE id = ?",
                [$listingId]
            );
            $message = "Listing '{$listing['title']}' has been approved";

            $notifMsg = "Your listing \"{$listing['title']}\" has been approved and is now live!";
            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link) 
                 VALUES (?, 'listing', 'Listing Approved', ?, '/seller/manage-listings.php')",
                [$listing['seller_id'], $notifMsg]
            );
            break;

        case 'reject':
            $db->update(
                "UPDATE listings SET status = 'cancelled' WHERE id = ?",
                [$listingId]
            );
            $message = "Listing '{$listing['title']}' has been rejected";

            $notifMsg = "Your listing \"{$listing['title']}\" has been rejected." . ($reason ? " Reason: {$reason}" : '');
            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link) 
                 VALUES (?, 'listing', 'Listing Rejected', ?, '/seller/manage-listings.php')",
                [$listing['seller_id'], $notifMsg]
            );
            break;

        case 'feature':
            $current = $listing['is_featured'] ? 0 : 1;
            $db->update(
                "UPDATE listings SET is_featured = ? WHERE id = ?",
                [$current, $listingId]
            );
            $message = "Listing '{$listing['title']}' has been " . ($current ? 'featured' : 'unfeatured');
            break;

        case 'delete':
            // Check for linked orders first (foreign key protection)
            $orderCount = $db->fetchOne(
                "SELECT COUNT(*) AS c FROM orders WHERE listing_id = ?",
                [$listingId]
            )['c'] ?? 0;

            if ($orderCount > 0) {
                throw new Exception(
                    'Cannot delete this listing because it has ' . $orderCount .
                    ' associated order(s). Cancel or archive the order(s) first.'
                );
            }

            // Delete images from disk (best-effort)
            $images = $db->fetchAll(
                "SELECT image_url FROM listing_images WHERE listing_id = ?",
                [$listingId]
            );
            foreach ($images as $img) {
                $filePath = LISTING_IMAGES_PATH . $img['image_url'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // Cascading deletes handle images, bids, watchlist, notifications
            $db->delete("DELETE FROM listings WHERE id = ?", [$listingId]);
            $message = "Listing '{$listing['title']}' has been deleted";
            break;

        default:
            throw new Exception('Invalid action');
    }

    // Log admin action
    $db->insert(
        "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
         VALUES (?, ?, 'listing', ?, ?)",
        [Session::getUserId(), $action, $listingId, $message]
    );

    $db->commit();

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin listings API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}