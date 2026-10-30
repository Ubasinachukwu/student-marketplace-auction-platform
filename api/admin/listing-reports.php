<?php
// marketplace/api/admin/listing-reports.php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

Session::requireRole('admin');

if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$db        = db();
$adminId   = Session::getUserId();
$reportId  = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
$action    = isset($_POST['action'])    ? sanitize($_POST['action']) : '';
$notes     = isset($_POST['notes'])     ? trim((string)$_POST['notes']) : '';

if (!$reportId || !in_array($action, ['reviewed', 'dismissed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$report = $db->fetchOne(
    "SELECT id, listing_id, reported_by, status FROM reports WHERE id = ?",
    [$reportId]
);

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit();
}

if ($report['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This report has already been handled']);
    exit();
}

try {
    $db->beginTransaction();

    $db->update(
        "UPDATE reports SET status = ? WHERE id = ?",
        [$action, $reportId]
    );

    $db->insert(
        "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
         VALUES (?, ?, 'listing_report', ?, ?)",
        [
            $adminId,
            $action === 'reviewed' ? 'review_listing_report' : 'dismiss_listing_report',
            $reportId,
            $notes !== '' ? $notes : null,
        ]
    );

    $db->commit();

} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    error_log('Listing report action error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update report']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => $action === 'reviewed'
        ? 'Report marked as reviewed'
        : 'Report dismissed',
]);