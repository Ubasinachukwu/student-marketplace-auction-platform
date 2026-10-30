<?php
// marketplace/cron/auction-end.php
// Run this script every minute via cron job

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../classes/Auction.php';

// Check if running from command line (cron)
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$db = Database::getInstance();

// Log start
$logFile = __DIR__ . '/../logs/cron.log';
$logMessage = date('Y-m-d H:i:s') . " - Auction End Processor started\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

try {
    // Get all active auctions that have ended
    $endedAuctions = $db->fetchAll(
        "SELECT id FROM listings 
         WHERE status = 'active' 
         AND sale_type IN ('auction', 'hybrid')
         AND auction_end <= NOW()"
    );

    $processed = 0;
    $errors = 0;

    foreach ($endedAuctions as $auction) {
        try {
            $auctionObj = new Auction($auction['id']);
            $result = $auctionObj->processEndedAuction();
            
            if ($result['success']) {
                $processed++;
                $logMessage = date('Y-m-d H:i:s') . " - Processed auction #{$auction['id']}: {$result['message']}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            } else {
                $errors++;
                $logMessage = date('Y-m-d H:i:s') . " - Error processing auction #{$auction['id']}: {$result['message']}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        } catch (Exception $e) {
            $errors++;
            $logMessage = date('Y-m-d H:i:s') . " - Exception processing auction #{$auction['id']}: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }

    $logMessage = date('Y-m-d H:i:s') . " - Auction End Processor completed. Processed: {$processed}, Errors: {$errors}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

} catch (Exception $e) {
    $logMessage = date('Y-m-d H:i:s') . " - CRITICAL ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

echo "Done.\n";