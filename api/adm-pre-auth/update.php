<?php
/**
 * API: Update Pre-Auth (Admin Panel Override Model)
 * Path: /api/admin/pre-auth/update.php
 * Updates parent metadata, tracks structural overrides, and syncs itemized sub-procedures.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce role permission protection layers (Admin or Staff)
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Session Parameters & Validate Target Payload ID
$currentUserId = $_SESSION['user_id'] ?? 0;
$preAuthId     = (int)($_POST['id'] ?? ($_POST['preauth_id'] ?? 0));

if ($currentUserId <= 0) {
    Api::error('User session context not found.', 401);
    exit;
}

if ($preAuthId <= 0) {
    Api::error('Record Identification ID is required for updating.');
    exit;
}

// 2. Fetch current record validation context (Admins transcend strict session office confinement)
$currentRecord = $db->queryOne(
    "SELECT id, status, approved_by, appointment_date, approval_expire_date FROM `pre-auth` WHERE id = ? LIMIT 1", 
    [$preAuthId]
);

if (!$currentRecord) {
    Api::error('Record not found or structural database tracking resolution error.', 404);
    exit;
}

// 3. Capture Dynamic Input Target Matrix Parameters from $_POST
$patientId       = (int)($_POST['patient_id'] ?? 0);
$officeId        = (int)($_POST['office_id'] ?? 0);
$pInsurancePlan  = (int)($_POST['p_insurance_plan'] ?? 0);
$status          = trim($_POST['status'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// Capture form dates supplied by layout
$formCreatedAt   = trim($_POST['created_at'] ?? '');
$appointmentDate = trim($_POST['appointment_date'] ?? ($_POST['scheduled_date'] ?? ''));
$approvalExpire  = trim($_POST['approval_expire_date'] ?? ($_POST['scheduled_expiry_date'] ?? ''));

// Capture your structured objects array layout matches: [{"procedure_id":"X","tooth_number":"Y"}]
$procedures      = $_POST['procedures'] ?? []; 

if ($patientId <= 0 || $officeId <= 0 || $pInsurancePlan <= 0 || empty($status) || empty($procedures)) {
    Api::error('Patient selection, Office, Insurance Plan, Status, and at least one procedure item row are required.');
    exit;
}

// 4. Format and Prepare Date Inputs Structurally
$finalCreatedAt = !empty($formCreatedAt) ? date('Y-m-d H:i:s', strtotime($formCreatedAt)) : null;
$finalApptDate  = !empty($appointmentDate) ? date('Y-m-d H:i:s', strtotime($appointmentDate)) : null;
$finalExpireDate = !empty($approvalExpire) ? date('Y-m-d H:i:s', strtotime($approvalExpire)) : null;

// 5. BUSINESS RULE ENGINE RULE CHECK:
// Catch changes passing out of ('Sent', 'Appealed', 'Rejected') into ('Scheduled', 'Completed', 'Approved')
$oldStatus = trim($currentRecord['status']);
$newStatus = $status;

$sourceStatusContext = ['Sent', 'Appealed', 'Rejected'];
$targetApprovalContext = ['Scheduled', 'Completed', 'Approved'];

// Retain the existing approver ID as fallback tracking parameter defaults
$assignedApproverId = !empty($currentRecord['approved_by']) ? (int)$currentRecord['approved_by'] : null;

if (in_array($oldStatus, $sourceStatusContext, true) && in_array($newStatus, $targetApprovalContext, true)) {
    $assignedApproverId = $currentUserId; // Elevate Admin as the authorized tracking processing user
}

// 6. Assemble Master Parent Tracking Payload Sequence Match
$parentUpdateData = [
    'patient_id'           => $patientId,
    'office_id'            => $officeId,
    'p_insurance_plan'     => $pInsurancePlan,
    'status'               => $newStatus,
    'notes'                => $notes,
    'appointment_date'     => $finalApptDate,
    'approval_expire_date' => $finalExpireDate,
    'approved_by'          => $assignedApproverId, // Contextually resolved via processing flow check rules above
    'edited_by'            => $currentUserId,      // Audit logger stamp for who changed value
    'edit_time'            => date('Y-m-d H:i:s')  // Current system synchronization timestamp
];

// If form includes a modified root record registration date, enable tracking modification override rules
if (!empty($finalCreatedAt)) {
    $parentUpdateData['created_at'] = $finalCreatedAt;
}

try {
    // Start Transaction to guarantee absolute database reference atomic consistency across tables
    $db->beginTransaction();

    // 7. Push mutation parameters target to master record database mapping row
    $db->update('pre-auth', $parentUpdateData, ['id' => $preAuthId]);

    // 8. Purge historical map associations completely to perform a clean sub-procedure sync
    $db->query("DELETE FROM `pre_auth_procedures` WHERE pre_auth_id = ?", [$preAuthId]);

    // 9. Reconstruct clean dynamic row elements step sequences looping the procedural parameters
    foreach ($procedures as $item) {
        $procId  = (int)($item['procedure_id'] ?? 0);
        $toothNo = trim($item['tooth_number'] ?? '');

        if ($procId <= 0) {
            continue; // Safely skip invalid indices components
        }

        $db->insert('pre_auth_procedures', [
            'pre_auth_id'  => $preAuthId,
            'procedure_id' => $procId,
            'tooth_number' => $toothNo
        ]);
    }

    // Safely save atomic ledger operations sequence tracking permanent to standard layout records
    $db->commit();

    Api::success(['pre_auth_id' => $preAuthId], 'Pre-Authorization records and audit-logs updated contextually.');

} catch (Exception $e) {
    // Gracefully contain state anomalies, rollback operations instantly to safeguard database consistency
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}