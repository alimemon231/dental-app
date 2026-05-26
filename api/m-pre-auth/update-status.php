<?php
/**
 * API: Update Pre-Auth Status (Approve/Reject)
 * Path: /api/m-pre-auth/update-status.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Check: Only management or admin can process requests
if (!$auth->hasRole('m-staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Reviewer privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Input from JavaScript AJAX Payload securely
$preAuthId      = (int)($_POST['id'] ?? 0);
$newStatus      = trim($_POST['status'] ?? ''); 
$reviewerId     = $_SESSION['user_id']; 
$expiryDate     = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
$rejectionNotes = !empty($_POST['rejection_notes']) ? trim($_POST['rejection_notes']) : null;

// 3. Parameters Range Check Validation
if (!$preAuthId || !in_array($newStatus, ['Approved', 'Rejected'])) {
    Api::error('Invalid request parameters matching status state profiles.');
    exit;
}

try {
    // 4. Fetch the existing record to guarantee presence safety
    $sql = "SELECT pa.id FROM `pre-auth` pa WHERE pa.id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Pre-auth target record could not be found.', 404);
        exit;
    }

    // 5. Build safe database payload matrix mapping array variables 
    // This structure prevents errors by falling back cleanly to NULL fields
    $updateData = [
        'status'               => $newStatus,
        'approved_by'          => $reviewerId,
        'approval_expire_date' => ($newStatus === 'Approved') ? $expiryDate : null,
        
        'notes'          => ($newStatus === 'Rejected') ? $rejectionNotes : null
    ];

    // Execute safe parameter bindings query execution
    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 6. Return standard API operational response block mapping parameters back to frontend
    Api::success(null, "Pre-Auth has been processed and saved as {$newStatus}.");

} catch (Exception $e) {
    Api::error('Server state mutation structural failure: ' . $e->getMessage(), 500);
}