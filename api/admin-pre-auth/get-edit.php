<?php
/**
 * GET api/admin-pre-auth/get-edit.php?id=XX
 * Fetches sibling records and case metadata for full portfolio editing.
 * Global admin scope: bypasses office session isolation boundaries.
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

// 2. Parameter Validation
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Pre-Auth record item reference row ID is required.'); 
    exit; 
}

try {
    // 3. Locate the parent case identification context for this row item
    $lookupRow = $db->queryOne("SELECT case_id FROM `pre-auth` WHERE id = ? LIMIT 1", [$id]);
    if (!$lookupRow) {
        Api::error('The specified pre-authorization procedure record row does not exist.', 404);
        exit;
    }
    $caseId = (int)$lookupRow['case_id'];

    /**
     * 4. Fetch Master Parent Case Details
     * REMOVED: pac.office_id constraint checking to grant cross-office admin permissions
     */
    $caseSql = "
        SELECT 
            pac.id AS case_id,
            pac.patient_id,
            pac.doctor_id,
            pac.office_id,
            o.office_name AS clinic_name,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            doc.name AS doctor_name
        FROM `pre_auth_cases` pac
        INNER JOIN `patient` pat ON pac.patient_id = pat.id
        LEFT JOIN `offices` o ON pac.office_id = o.id
        LEFT JOIN `users` doc ON pac.doctor_id = doc.user_id
        WHERE pac.id = ?
        LIMIT 1
    ";
    // Now passing only the caseId, no longer dependent on session office_id
    $caseMetadata = $db->queryOne($caseSql, [$caseId]);

    if (!$caseMetadata) { 
        Api::error('Parent case profile not found or access denied.', 404); 
        exit; 
    }

    // 5. Gather ALL procedures currently nested inside this case file block envelope
    $proceduresSql = "
        SELECT 
            pa.id AS pre_auth_id,
            pa.procedure_id,
            pa.teeth_number AS tooth_number,
            pa.p_insurance_plan,
            pa.status,
            pa.notes,
            proc.name AS procedure_name
        FROM `pre-auth` pa
        INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
        WHERE pa.case_id = ?
        ORDER BY pa.id ASC
    ";
    $proceduresList = $db->query($proceduresSql, [$caseId]) ?: [];

    // 6. Structure payload
    $primaryItem = !empty($proceduresList) ? $proceduresList[0] : null;

    $response = [
        'id'               => $caseId, 
        'case_id'          => $caseId,
        'patient_id'       => (int)$caseMetadata['patient_id'],
        'patient_name'     => $caseMetadata['patient_name'],
        'patient_dob'      => $caseMetadata['patient_dob'],
        'doctor_id'        => (int)$caseMetadata['doctor_id'],
        'doctor_name'      => $caseMetadata['doctor_name'] ?: 'Unassigned Doctor',
        'office_id'        => (int)$caseMetadata['office_id'],
        'clinic_name'      => $caseMetadata['clinic_name'] ?: 'Unknown Clinic',
        
        'p_insurance_plan' => $primaryItem ? (int)$primaryItem['p_insurance_plan'] : 0,
        'status'           => $primaryItem ? $primaryItem['status'] : 'Requested',
        
        'procedures_list'  => $proceduresList
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database lookup failed: ' . $e->getMessage(), 500);
}