<?php
/**
 * API: Revert Scheduled Pre-Auth back to Approved (Admin Reschedule)
 * Path: /api/admin-pre-auth/reschedule-request.php
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
    // 3. Fetch record (Admin scope: no ownership check needed)
    $sql = "SELECT id, status FROM `pre-auth` WHERE id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Record not found.', 404);
        exit;
    }

    if ($record['status'] !== 'Scheduled') {
        Api::error('Only scheduled appointments can be sent for rescheduling.');
        exit;
    }

    // 4. Update the Database
    // Set status back to 'Approved' and nullify the appointment_date
    $updateData = [
        'status'           => 'Approved',
        'appointment_date' => null,
        'edited_by'        => $currentUser['id'],
        'edit_time'        => date('Y-m-d H:i:s')
    ];

    

    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 5. Success Response
    Api::success([
        'id' => $preAuthId,
        'new_status' => 'Approved'
    ], "Appointment has been cleared for rescheduling via Admin override.");

} catch (Exception $e) {
    Api::error('Admin server error: ' . $e->getMessage());
}