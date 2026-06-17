<?php
/**
 * POST api/admin-pre-auth/update.php
 * Administrative Pre-Auth Multi-Row Component Differential Synchronizer.
 * Preserves historical row item statuses while applying global modifications, deletions, and additions.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Enforce Unified Administration Privilege Context Gateways
if (!$auth->hasRole('admin') && !$auth->hasRole('management')) {
    Api::error('Unauthorized administrative access credential clear failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Establish Session Context and Case Identifiers
$currentUserId   = $_SESSION['user_id'];
$caseId          = (int)($_POST['preauth_id'] ?? 0); // Payload 'preauth_id' maps to master case_id context

if ($caseId <= 0) {
    Api::error('A valid Master Case Profile Identification key is required for modification.');
    exit;
}

// Admins pass office_id via form payload dynamically, fall back to session variables if missing
$targetOfficeId = (int)($_POST['office_id'] ?? $_SESSION['office_id'] ?? 0);

if ($targetOfficeId <= 0) {
    Api::error('Active clinic location workspace scope assignment not provided or resolved.', 400);
    exit;
}

// 3. Verify Master Case Envelope Profile exists (Admin bypasses rigid session restrictions)
$currentCase = $db->queryOne(
    "SELECT id, office_id FROM `pre_auth_cases` WHERE id = ? LIMIT 1", 
    [$caseId]
);

if (!$currentCase) {
    Api::error('Master Case envelope tracking profile record not found.', 404);
    exit;
}

// 4. Capture Relational Field Matrices & Form Payload Array Data
$patientId       = (int)($_POST['patient_id'] ?? 0);
$doctorId        = (int)($_POST['provider'] ?? $_POST['doctor_id'] ?? 0);
$pInsurancePlan  = (int)($_POST['p_insurance_plan'] ?? 0);
$treatmentTypes  = $_POST['treatment_type'] ?? []; // Dynamic standard treatment array block
$toothNumbers    = $_POST['tooth_numbers'] ?? [];   // Dynamic target mapping array block
$treatmentPrices = $_POST['treatment_price'] ?? []; // Tracked procedure execution cost matrix rows

// CRITICAL: Item row arrays representing original pre-existing database row row key IDs
$itemRowIds      = $_POST['item_row_ids'] ?? [];

// Capture custom request date parameters input mapping values if supplied
$requestDateInput = trim($_POST['request_date'] ?? '');

if ($patientId <= 0 || $doctorId <= 0 || $pInsurancePlan <= 0 || empty($treatmentTypes) || empty($toothNumbers)) {
    Api::error('Patient entry choice, active designated provider, insurance scheme network, and at least one item line row are required.');
    exit;
}

if (count($treatmentTypes) !== count($toothNumbers) || count($treatmentTypes) !== count($treatmentPrices)) {
    Api::error('Structural processing error: Array data dimensions match alignment failure.');
    exit;
}

// 5. Time Resolution Merging Engine: Synthesize dynamic form inputs with systemic clock details
if (!empty($requestDateInput) && strtotime($requestDateInput) !== false) {
    $currentTimeStamp = date('Y-m-d', strtotime($requestDateInput)) . ' ' . date('H:i:s');
} else {
    $currentTimeStamp = date('Y-m-d H:i:s');
}

try {
    // Start ACID Compliant database transactional boundaries
    $db->beginTransaction();

    // 6. Synchronize Parent Metadata Row Parameters inside `pre_auth_cases`
    $caseUpdateData = [
        'patient_id' => $patientId,
        'doctor_id'  => $doctorId,
        'office_id'  => $targetOfficeId // Admins are permitted to dynamically clear or re-assign case clinic scopes
    ];
    $db->update('pre_auth_cases', $caseUpdateData, ['id' => $caseId]);

    // 7. Extract the pre-existing row states bound to this parent container to calculate diff/deletions matrix
    $existingRows = $db->query(
        "SELECT id, status FROM `pre-auth` WHERE case_id = ?",
        [$caseId]
    ) ?: [];

    // Form index tracking map array referencing key structural primary database IDs
    $indexedExisting = [];
    foreach ($existingRows as $row) {
        $indexedExisting[(int)$row['id']] = $row;
    }

    $processedRowIds = [];

    // 8. Loop through dynamic array payload items to handle positional Updates or row Appends
    foreach ($treatmentTypes as $index => $procedureId) {
        $procId   = (int)$procedureId;
        $toothNo  = (int)$toothNumbers[$index];
        $rowPrice = $treatmentPrices[$index]; // Access matched pricing row metric data

        if ($procId <= 0) {
            continue; // Safely bypass un-configured input fields mapping instances
        }

        // Evaluate whether this distinct payload layout index position references a legacy line row structure
        $matchedRowId = isset($itemRowIds[$index]) ? (int)$itemRowIds[$index] : 0;

        if ($matchedRowId > 0 && isset($indexedExisting[$matchedRowId])) {
            // SCENARIO A: Row identifier found explicitly via client array -> Update parameters, keep status untouched!
            $rowUpdate = [
                'procedure_id'     => $procId,
                'teeth_number'     => $toothNo,
                'price'            => $rowPrice, // Sync tracking cost directly on modified items
                'p_insurance_plan' => $pInsurancePlan,
                'edited_by'        => $currentUserId,
                'edit_time'        => $currentTimeStamp
            ];
            
            $db->update('pre-auth', $rowUpdate, ['id' => $matchedRowId]);
            $processedRowIds[] = $matchedRowId;
        } else {
            // SCENARIO B: Brand new line append item context -> Insert fresh child entry line defaulting tracking state to 'Requested'
            $newRowData = [
                'case_id'              => $caseId,
                'procedure_id'         => $procId,
                'teeth_number'         => $toothNo,
                'price'                => $rowPrice, // Injected tracking cost directly on appended items
                'p_insurance_plan'     => $pInsurancePlan,
                'appointment_date'     => null,
                'created_at'           => $currentTimeStamp,
                'created_by'           => (int)$currentUserId,
                'approved_by'          => 0,
                'approval_expire_date' => null,
                'status'               => 'Requested', // Default starting position for fresh appends only
                'edited_by'            => 0,
                'edit_time'            => $currentTimeStamp,
                'notes'                => ''
            ];
            
            $newId = $db->insert('pre-auth', $newRowData);
            if ($newId) {
                $processedRowIds[] = (int)$newId;
            }
        }
    }

    // 9. Processing Garbage Collection: Purge row entries dropped/deleted by the user interface form view layer
    foreach ($indexedExisting as $existingId => $exRow) {
        if (!in_array($existingId, $processedRowIds)) {
            $db->query("DELETE FROM `pre-auth` WHERE id = ? LIMIT 1", [$existingId]);
        }
    }

    // Safely write structural ledger updates down to storage engine layers permanently
    $db->commit();
    
    Api::success(['case_id' => $caseId], 'Administrative Pre-Authorization profile portfolio modifications saved successfully.');

} catch (Exception $e) {
    // Roll back operational state properties immediately on tracking exceptions to safeguard structural integrity
    $db->rollBack();
    Api::error('Global admin database management tier pipeline runtime failure: ' . $e->getMessage(), 500);
}