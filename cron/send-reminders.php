<?php
// marketplace/cron/send-reminders.php
// Run every 15 minutes via cron:
//   */15 * * * * /usr/bin/php /path/to/marketplace/cron/send-reminders.php

require_once __DIR__ . '/../config/database.php';   // pulls in constants.php
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/mailer.php';      // sendEmail()

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$db = db();

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$cronLog  = $logDir . '/cron.log';
$emailLog = $logDir . '/email.log';



cronLog($cronLog, "Auction Reminder Processor started");

try {
    $endingSoon = $db->fetchAll(
        "SELECT l.*, u.email AS seller_email, u.full_name AS seller_name
         FROM listings l
         JOIN users u ON l.seller_id = u.id
         WHERE l.status = 'active'
           AND l.sale_type IN ('auction', 'hybrid')
           AND l.auction_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
           AND l.reminder_sent_at IS NULL"
    );

    $totalSent = 0;

    foreach ($endingSoon as $auction) {
        $sentThisAuction = 0;
        $listingTitle    = $auction['title'];
        $listingId       = $auction['id'];
        $currentBidText  = formatNaira($auction['current_bid'] ?? $auction['starting_bid']);
        $listingLink     = SITE_URL . '/buyer/listing-detail.php?id=' . $listingId
                         . (!empty($auction['slug']) ? '&slug=' . urlencode($auction['slug']) : '');

        // --- Seller in-app notification ---
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, 'auction', 'Auction Ending Soon', ?, ?)",
            [
                $auction['seller_id'],
                "Your auction \"{$listingTitle}\" ends in 1 hour.",
                "/seller/manage-listings.php",
            ]
        );

        // --- Seller email ---
        $sellerBody = "Hi {$auction['seller_name']},\n\n"
                    . "Your auction \"{$listingTitle}\" ends in 1 hour.\n"
                    . "Current bid: {$currentBidText}\n\n"
                    . "View it: " . SITE_URL . "/seller/manage-listings.php\n\n"
                    . SITE_NAME . " Team";
        $ok = sendEmail(
            $auction['seller_email'],
            "Your auction is ending soon - " . SITE_NAME,
            $sellerBody,
            $auction['seller_name']
        );
        cronLog($emailLog, ($ok ? "OK" : "FAIL") . " seller -> {$auction['seller_email']} (listing #{$listingId})");

        // --- Bidders ---
        $bidders = $db->fetchAll(
            "SELECT DISTINCT u.id, u.email, u.full_name
             FROM bids b
             JOIN users u ON b.buyer_id = u.id
             WHERE b.listing_id = ? AND b.is_retracted = 0",
            [$listingId]
        );

        foreach ($bidders as $bidder) {
            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link)
                 VALUES (?, 'auction', 'Auction Ending Soon!', ?, ?)",
                [
                    $bidder['id'],
                    "The auction for \"{$listingTitle}\" ends in 1 hour. Current bid: {$currentBidText}",
                    '/buyer/listing-detail.php?id=' . $listingId,
                ]
            );

            $bidderBody = "Hi {$bidder['full_name']},\n\n"
                        . "The auction for \"{$listingTitle}\" ends in 1 hour!\n"
                        . "Current bid: {$currentBidText}\n\n"
                        . "View the auction: {$listingLink}\n\n"
                        . "Don't miss out!\n\n"
                        . SITE_NAME . " Team";
            $ok = sendEmail(
                $bidder['email'],
                "Auction Ending Soon - " . SITE_NAME,
                $bidderBody,
                $bidder['full_name']
            );
            cronLog($emailLog, ($ok ? "OK" : "FAIL") . " bidder -> {$bidder['email']} (listing #{$listingId})");

            $sentThisAuction++;
            $totalSent++;
        }

        // Mark as reminded
        $db->update(
            "UPDATE listings SET reminder_sent_at = NOW() WHERE id = ?",
            [$listingId]
        );

        cronLog($cronLog, "Reminded #{$listingId} ({$listingTitle}) -> {$sentThisAuction} bidders + seller");
    }

    cronLog($cronLog, "Completed. Total emails attempted: {$totalSent}");

} catch (Throwable $e) {
    cronLog($cronLog, "CRITICAL ERROR: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
}

echo "Done.\n";