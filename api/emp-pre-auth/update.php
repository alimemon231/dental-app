<?php
/**
 * POST api/emp-pre-auth/update.php
 * Synchronizes parent case metadata and maps itemized line procedures dynamically.
 * Preserves specific workflow states for unchanged items and supports universal editing.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized administrative access credential clear failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Workspace Context Parameters
$currentUserId   = $_SESSION['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$caseId          = (int)($_POST['preauth_id'] ?? 0); // Payload 'preauth_id' maps to the master case_id context

if ($caseId <= 0) {
    Api::error('A valid Case Profile Identification key is required for modification.');
    exit;
}

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session.', 400);
    exit;
}

// 2. Verify that the Master Case Envelope Profile exists and matches clinic office scope
$currentCase = $db->queryOne(
    "SELECT id FROM `pre_auth_cases` WHERE id = ? AND office_id = ? LIMIT 1", 
    [$caseId, $sessionOfficeId]
);

if (!$currentCase) {
    Api::error('Master Case envelope not found or location validation mismatch.', 404);
    exit;
}

// 3. Capture Relational Payload Matrices & Array Blocks
$patientId       = (int)($_POST['patient_id'] ?? 0);
$doctorId        = (int)($_POST['provider'] ?? 0);
$pInsurancePlan  = (int)($_POST['p_insurance_plan'] ?? 0);
$treatmentTypes  = $_POST['treatment_type'] ?? []; // Dynamic UI array stack
$toothNumbers    = $_POST['tooth_numbers'] ?? [];   // Dynamic UI array stack

// CRITICAL: Array mapping explicitly containing original item row database keys from the UI
$itemRowIds      = $_POST['item_row_ids'] ?? [];

if ($patientId <= 0 || $doctorId <= 0 || $pInsurancePlan <= 0 || empty($treatmentTypes) || empty($toothNumbers)) {
    Api::error('Patient selection, assigned provider, insurance network mapping, and at least one itemized procedure line are required.');
    exit;
}

if (count($treatmentTypes) !== count($toothNumbers)) {
    Api::error('Structural processing error: Array data dimensions match alignment failure.');
    exit;
}


    // Start Transaction block to guarantee atomicity
    $db->beginTransaction();

    // 4. Update parent structural parameters inside `pre_auth_cases`
    $caseUpdateData = [
        'patient_id' => $patientId,
        'doctor_id'  => $doctorId,
    ];
    $db->update('pre_auth_cases', $caseUpdateData, ['id' => $caseId]);

    // 5. Gather existing tracking records currently saved for this case to handle diffs/deletions
    $existingRows = $db->query(
        "SELECT id, status FROM `pre-auth` WHERE case_id = ?",
        [$caseId]
    ) ?: [];

    // Map existing records index by their explicit record table row IDs for quick lookup references
    $indexedExisting = [];
    foreach ($existingRows as $row) {
        $indexedExisting[(int)$row['id']] = $row;
    }

    $processedRowIds = [];

    // 6. Loop through the incoming form matrix to perform target updates or fresh appends
    foreach ($treatmentTypes as $index => $procedureId) {
        $procId  = (int)$procedureId;
        $toothNo = trim($toothNumbers[$index]);

        if ($procId <= 0 || $toothNo === '') {
            continue; // Ignore blank structural entry lines safely
        }

        // Determine if this exact index step correlates to an explicit incoming database row key
        $matchedRowId = isset($itemRowIds[$index]) ? (int)$itemRowIds[$index] : 0;

        if ($matchedRowId > 0 && isset($indexedExisting[$matchedRowId])) {
            // SCENARIO A: Row item found explicitly via passed element ID -> Keep status untouched!
            $rowUpdate = [
                'procedure_id'     => $procId,
                'teeth_number'     => $toothNo,
                'p_insurance_plan' => $pInsurancePlan,
                'edited_by'        => $currentUserId,
                'edit_time'        => date('Y-m-d H:i:s')
            ];
            $db->update('pre-auth', $rowUpdate, ['id' => $matchedRowId]);
            $processedRowIds[] = $matchedRowId;
        } else {
            // SCENARIO B: This is a newly appended row -> Save fresh itemized row tracking details with status 'Requested'
            $newRowData = [
                'case_id'          => $caseId,
                'procedure_id'     => $procId,
                'teeth_number'     => $toothNo,
                'p_insurance_plan' => $pInsurancePlan,
                'status'           => 'Requested', // Default operational state assigned only to newly added lines
                'created_by'       => $currentUserId,
                'created_at'       => date('Y-m-d H:i:s')
            ];
            
            $newId = $db->insert('pre-auth', $newRowData);
            if ($newId) {
                $processedRowIds[] = (int)$newId;
            }
        }
    }

    // 7. Cleanup Stage: Purge tracking lines that were explicitly deleted by users from the UI interface container
    foreach ($indexedExisting as $existingId => $exRow) {
        if (!in_array($existingId, $processedRowIds)) {
            $db->query("DELETE FROM `pre-auth` WHERE id = ? LIMIT 1", [$existingId]);
        }
    }

    // Commit transaction permanently
    $db->commit();
    Api::success(null, 'Pre-Authorization profile portfolio modifications saved successfully.');

