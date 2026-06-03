<?php
/**
 * API: Update Pre-Auth Pipeline Status Context (Admin Override Matrix)
 * Path: /api/admin-pre-auth/modify-status.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin & Management Role Enforcement
$currentUser = $auth->user();
if (!in_array($currentUser['role'], ['admin', 'management'])) {
    Api::error('Unauthorized access. Administrative privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Clean Input Parameters
$preAuthId      = (int)($_POST['id'] ?? 0);
$incomingStatus = trim($_POST['status'] ?? '');

if ($preAuthId <= 0) {
    Api::error('Invalid request parameters. Case ID tracking number is required.');
    exit;
}

if (empty($incomingStatus)) {
    Api::error('Missing status assignment destination payload.');
    exit;
}

// Allowed system statuses validation filter array
$validStatuses = ['Requested', 'Sent', 'Processing', 'Approved', 'Appealed', 'Scheduled', 'Completed', 'Expired', 'Rejected'];
if (!in_array($incomingStatus, $validStatuses)) {
    Api::error('Invalid pipeline status configuration passed to server engine.');
    exit;
}

try {
    // 3. Fetch record data validation scope (Admin global access bounds)
    $sql = "SELECT id, status FROM `pre-auth` WHERE id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Pre-authorization profile row record data target not found.', 404);
        exit;
    }

    // 4. Determine Dynamic Database Updates Payload Matrix
    $updateData = [
        'status'    => $incomingStatus,
        'edited_by' => $currentUser['id'],
        'edit_time' => date('Y-m-d H:i:s')
    ];

    // If workflow shifts state to Approved, Rejected, or Appealed, wipe the scheduled execution timestamp
    $wipeDateStatuses = ['Approved', 'Rejected', 'Appealed'];
    if (in_array($incomingStatus, $wipeDateStatuses)) {
        $updateData['appointment_date'] = null;
    }

    // Execute state alterations structural transaction bound
    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 5. Build Dynamic Feedback Narrative Notice and Return JSON Success Meta
    $statusWipedMessage = in_array($incomingStatus, $wipeDateStatuses) 
        ? " with appointment date mapping safely un-linked." 
        : ".";

    Api::success([
        'id' => $preAuthId,
        'new_status' => $incomingStatus
    ], "Record status successfully updated to " . $incomingStatus . $statusWipedMessage);

} catch (Exception $e) {
    Api::error('Global admin database management tier pipeline runtime error: ' . $e->getMessage());
}