<?php
/**
 * GET api/emp-pre-auth/get-edit.php?id=XX
 * Resolves a single line-item row to its parent case, then pulls all sibling records 
 * and case metadata (including provider/doctor assignment info) for full portfolio editing.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Parameter Validation
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Pre-Auth record item reference row ID is required.'); 
    exit; 
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    // 2. Locate the parent case identification context for this row item
    $lookupRow = $db->queryOne("SELECT case_id FROM `pre-auth` WHERE id = ? LIMIT 1", [$id]);
    if (!$lookupRow) {
        Api::error('The specified pre-authorization procedure record row does not exist.', 404);
        exit;
    }
    $caseId = (int)$lookupRow['case_id'];

    // 3. Fetch Master Parent Case Details alongside Doctor Assignment Information
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
        WHERE pac.id = ? AND pac.office_id = ?
        LIMIT 1
    ";
    $caseMetadata = $db->queryOne($caseSql, [$caseId, $sessionOfficeId]);

    if (!$caseMetadata) { 
        Api::error('Parent case profile access denied or mismatch for this branch.', 404); 
        exit; 
    }

    // 4. Gather ALL procedures currently nested inside this case file block envelope
    $proceduresSql = "
        SELECT 
            pa.id AS pre_auth_id,
            pa.procedure_id,
            pa.teeth_number AS tooth_number,
            pa.price,
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

    // 5. Structure payload to look like your legacy format, but containing all case metrics
    $formattedProcedures = [];
    foreach ($proceduresList as $item) {
        $formattedProcedures[] = [
            'pre_auth_id'     => (int)$item['pre_auth_id'],
            'procedure_id'    => (int)$item['procedure_id'],
            'procedure_name'  => $item['procedure_name'],
            'procedure_price' => $item['price'], // Aligned to look up from pre-auth column
            'price'           => $item['price'], // Redundant map fallback for security matching strings
            'tooth_number'    => $item['tooth_number'],
            'p_insurance_plan'=> (int)$item['p_insurance_plan'],
            'status'          => $item['status'],
            'notes'           => $item['notes']
        ];
    }

    $primaryItem = !empty($formattedProcedures) ? $formattedProcedures[0] : null;

    $response = [
        'id'               => $caseId, // Assigning case_id as root identifier to preserve your framework configurations
        'case_id'          => $caseId,
        'patient_id'       => (int)$caseMetadata['patient_id'],
        'patient_name'     => $caseMetadata['patient_name'],
        'patient_dob'      => $caseMetadata['patient_dob'],
        'doctor_id'        => (int)$caseMetadata['doctor_id'],
        'doctor_name'      => $caseMetadata['doctor_name'] ?: 'Unassigned Doctor',
        'office_id'        => (int)$caseMetadata['office_id'],
        'clinic_name'      => $caseMetadata['clinic_name'],
        
        // Expose configuration defaults based on case elements row
        'p_insurance_plan' => $primaryItem ? (int)$primaryItem['p_insurance_plan'] : 0,
        'status'           => $primaryItem ? $primaryItem['status'] : 'Requested',
        
        // Supply full itemized list for JavaScript loop to generate dynamic rows
        'procedures_list'  => $formattedProcedures
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database lookup failed during modification gathering: ' . $e->getMessage(), 500);
}