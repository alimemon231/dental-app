<?php
/**
 * API: Update Pre-Auth Status (Approve/Reject)
 * Path: /api/m-pre-auth/update-status.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Role check
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Elevated privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture Input
$preAuthId      = (int)($_POST['id'] ?? 0);
$newStatus      = trim($_POST['status'] ?? ''); 
$reviewerId     = $_SESSION['user_id']; 
$expiryDate     = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
$rejectionNotes = !empty($_POST['rejection_notes']) ? trim($_POST['rejection_notes']) : null;

// 2. Initial Parameters Validation
if (!$preAuthId || !in_array($newStatus, ['Approved', 'Rejected'])) {
    Api::error('Invalid request parameters matching status state profiles.');
    exit;
}

// 3. MANDATORY FIELD VALIDATION: Force expiry_date if status is Approved
if ($newStatus === 'Approved' && empty($expiryDate)) {
    Api::error('An approval expiration date is required to process this request.');
    exit;
}

try {
    // 4. Fetch the existing record
    $sql = "SELECT id, case_id FROM `pre-auth` WHERE id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Pre-auth target record could not be found.', 404);
        exit;
    }

    // 5. Build safe database payload
    $updateData = [
        'status'               => $newStatus,
        'approved_by'          => $reviewerId,
        'approval_expire_date' => ($newStatus === 'Approved') ? $expiryDate : null,
        'notes'                => ($newStatus === 'Rejected') ? $rejectionNotes : null,
        'edit_time'            => date('Y-m-d H:i:s'),
        'edited_by'            => $reviewerId
    ];

    // Execute update
    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    Api::success(null, "Pre-Auth has been processed and saved as {$newStatus}.");

} catch (Exception $e) {
    Api::error('Server state mutation structural failure: ' . $e->getMessage(), 500);
}