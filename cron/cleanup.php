<?php
// marketplace/cron/cleanup.php
// Run this script daily via cron job

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';

// Check if running from command line (cron)
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$db = Database::getInstance();

// Log start
$logFile = __DIR__ . '/../logs/cron.log';
$logMessage = date('Y-m-d H:i:s') . " - Cleanup Processor started\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

try {
    // 1. Clean up temporary uploads older than 7 days
    $tempDir = sys_get_temp_dir() . '/marketplace_uploads/';
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '*');
        $deleted = 0;
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 7 * 24 * 60 * 60) {
                unlink($file);
                $deleted++;
            }
        }

        $logMessage = date('Y-m-d H:i:s') . " - Deleted {$deleted} temporary files\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    // 2. Clean up orphaned listing images (images not linked to any listing)
    $orphanedImages = $db->fetchAll(
        "SELECT image_url FROM listing_images li 
         LEFT JOIN listings l ON li.listing_id = l.id 
         WHERE l.id IS NULL"
    );

    $deletedImages = 0;
    foreach ($orphanedImages as $img) {
        $filePath = LISTING_IMAGES_PATH . $img['image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
            $deletedImages++;
        }
        // Delete from database
        $db->delete(
            "DELETE FROM listing_images WHERE image_url = ?",
            [$img['image_url']]
        );
    }

    $logMessage = date('Y-m-d H:i:s') . " - Deleted {$deletedImages} orphaned images\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // 3. Clean up old logs (keep last 30 days)
    $logDir = __DIR__ . '/../logs/';
    if (is_dir($logDir)) {
        $files = glob($logDir . '*.log');
        $deletedLogs = 0;
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 30 * 24 * 60 * 60) {
                unlink($file);
                $deletedLogs++;
            }
        }

        $logMessage = date('Y-m-d H:i:s') . " - Deleted {$deletedLogs} old log files\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    // 4. Clean up expired sessions
    $db->delete(
        "DELETE FROM sessions WHERE expires < NOW()"
    );

    $logMessage = date('Y-m-d H:i:s') . " - Cleanup Processor completed\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

} catch (Exception $e) {
    $logMessage = date('Y-m-d H:i:s') . " - CRITICAL ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

echo "Done.\n";