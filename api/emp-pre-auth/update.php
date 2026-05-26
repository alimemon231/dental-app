<?php
/**
 * API: Update Pre-Auth
 * Path: /emp-pre-auth/update.php
 * Updates parent metadata and syncs structural dynamic multi-row itemized procedures.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Session parameters and Validate Master PreAuth ID target
$currentUserId   = $_SESSION['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$preAuthId       = (int)($_POST['preauth_id'] ?? 0);

if ($preAuthId <= 0) {
    Api::error('Record Identification ID is required for updating.');
    exit;
}

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session.', 400);
    exit;
}

// 2. Fetch current record validation context
$currentRecord = $db->queryOne(
    "SELECT id, status FROM `pre-auth` WHERE id = ? AND office_id = ? LIMIT 1", 
    [$preAuthId, $sessionOfficeId]
);

if (!$currentRecord) {
    Api::error('Record not found or workspace access validation denied.', 404);
    exit;
}

// 3. STRICT CHECK: Only allow structural modifications if the current status is exactly 'Create' or 'Rejected'
$currentStatus = strtolower($currentRecord['status']);
if ($currentStatus !== 'create' && $currentStatus !== 'rejected') {
    Api::error('This record cannot be modified because its processing status state is currently: ' . $currentRecord['status'] . '.', 400);
    exit;
}

// 4. Capture and Validate Relational Payload & Array Blocks
$patientId       = (int)($_POST['patient_id'] ?? 0);
$p_insurance     = trim($_POST['p_insurance_plan'] ?? '');
$treatment_types = $_POST['treatment_type'] ?? []; // Array stack
$tooth_numbers   = $_POST['tooth_numbers'] ?? [];   // Array stack

if ($patientId <= 0 || empty($p_insurance) || empty($treatment_types) || empty($tooth_numbers)) {
    Api::error('Patient selection, insurance code, and at least one itemized procedure row are required.');
    exit;
}

if (count($treatment_types) !== count($tooth_numbers)) {
    Api::error('Structural payload mismatch detected. Procedures data matrix length is uneven.');
    exit;
}

// 5. Prepare Master Parent Update Schema Block
$parentUpdateData = [
    'patient_id'       => $patientId,
    'p_insurance_plan' => $p_insurance,
    'edited_by'        => $currentUserId,
    'edit_time'        => date('Y-m-d H:i:s')
];

try {
    // Start Transaction to guarantee full integrity across parent and children tables
    $db->beginTransaction();

    // 6. Update master parent metadata row entries
    $db->update('pre-auth', $parentUpdateData, ['id' => $preAuthId]);

    // 7. Purge existing mapping rows cleanly to reset relations before insertion
    $db->query("DELETE FROM `pre_auth_procedures` WHERE pre_auth_id = ?", [$preAuthId]);

    // 8. Reconstruct dynamic rows layout stack mapping elements
    foreach ($treatment_types as $index => $procedureId) {
        $procId  = (int)$procedureId;
        $toothNo = trim($tooth_numbers[$index]);

        if ($procId <= 0 || $toothNo === '') {
            continue; // Ignore blank entries safely
        }

        // Write fresh, verified index tracking details cleanly
        $db->insert('pre_auth_procedures', [
            'pre_auth_id'  => $preAuthId,
            'procedure_id' => $procId,
            'tooth_number' => $toothNo
        ]);
    }

    // Safely commit all queries to permanent storage
    $db->commit();

    Api::success(null, 'Pre-Authorization modifications saved successfully.');

} catch (Exception $e) {
    // Safe transactional roll-back wrapper validation check
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}