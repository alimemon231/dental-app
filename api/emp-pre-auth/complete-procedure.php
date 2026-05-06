<?php
/**
 * API: Mark Pre-Auth Procedure as Completed
 * Path: /api/emp-pre-auth/complete-procedure.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Check: Only staff or admin can finalize appointments
if ($auth->userRole() !== 'staff' && $auth->userRole() !== 'admin') {
    Api::error('Unauthorized access. Staff privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Input
$preAuthId = (int)($_POST['id'] ?? 0);
$currentUserId = $_SESSION['user_id']; 


// 3. Basic Validation
if (!$preAuthId) {
    Api::error('Invalid request parameters. ID is required.');
    exit;
}

try {
    // 4. Fetch the existing record to ensure it belongs to this user/office
    // and is currently in 'Scheduled' status
    $sql = "SELECT id, status, p_first_name, p_last_name FROM `pre-auth` WHERE id = ? AND created_by = ?";
    $record = $db->queryOne($sql, [$preAuthId, $currentUserId]);

    if (!$record) {
        Api::error('Record not found or you do not have permission to modify it.', 404);
        exit;
    }

    if ($record['status'] !== 'Scheduled') {
        Api::error('Only scheduled appointments can be marked as completed.');
        exit;
    }

    // 5. Update the Database
    // Set status to 'Completed' and update the timestamp
    $updateData = [
        'status'     => 'Completed',
    ];

    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 6. Success Response
    Api::success([
        'id' => $preAuthId,
        'new_status' => 'Completed'
    ], "Procedure for {$record['p_first_name']} {$record['p_last_name']} marked as Completed.");

} catch (Exception $e) {
    Api::error('Server Error: ' . $e->getMessage());
}