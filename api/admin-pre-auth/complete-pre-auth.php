<?php
/**
 * API: Mark Pre-Auth Procedure as Completed (Admin)
 * Path: /api/admin-pre-auth/complete-procedure.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Enforcement
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Input
$preAuthId = (int)($_POST['id'] ?? 0);

if ($preAuthId <= 0) {
    Api::error('Invalid request parameters. ID is required.');
    exit;
}

try {
    // 3. Fetch record (Admin scope: No ownership check required)
    $sql = "SELECT id, status FROM `pre-auth` WHERE id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Record not found.', 404);
        exit;
    }

    // Workflow validation: Ensure it is currently 'Scheduled'
    if ($record['status'] !== 'Scheduled') {
        Api::error('Only scheduled appointments can be marked as completed. Current status: ' . $record['status']);
        exit;
    }

    // 4. Update the Database
    $updateData = [
        'status'    => 'Completed',
        'edited_by' => $currentUser['id'],
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 5. Success Response
    Api::success([
        'id' => $preAuthId,
        'new_status' => 'Completed'
    ], "Procedure marked as Completed via Admin.");

} catch (Exception $e) {
    Api::error('Admin server error: ' . $e->getMessage());
}