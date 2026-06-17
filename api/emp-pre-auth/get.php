<?php
/**
 * GET api/emp-pre-auth/get.php?id=XX
 * Fetches exactly ONE specific itemized pre-auth record row alongside its parent case demographics.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validation and Parameter Capture
$preAuthId = (int)($_GET['id'] ?? 0);
if (!$preAuthId) { 
    Api::error('Pre-Auth individual item ID parameter is required.'); 
    exit; 
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    // 2. Fetch the exactly specified single pre-auth row item details
    $procSql = "
        SELECT 
            pa.id AS pre_auth_id,
            pa.case_id,
            pa.procedure_id,
            pa.teeth_number AS tooth_number,
            pa.price AS procedure_price, -- Changed: Pulls pricing context from pre-auth table instead of procedures
            pa.p_insurance_plan,
            pa.appointment_date,
            pa.created_at,
            pa.created_by,
            pa.approved_by,
            pa.approval_expire_date,
            pa.status,
            pa.edited_by,
            pa.edit_time,
            pa.notes,
            proc.name AS procedure_name,
            ins.name AS insurance_name,
            u_creator.name AS creator_name,
            u_editor.name AS editor_name,
            u_approver.name AS approver_name
        FROM `pre-auth` pa
        INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
        LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN `users` u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN `users` u_editor ON pa.edited_by = u_editor.user_id
        LEFT JOIN `users` u_approver ON pa.approved_by = u_approver.user_id
        WHERE pa.id = ?
        LIMIT 1
    ";

    $procedureItem = $db->queryOne($procSql, [$preAuthId]);

    if (!$procedureItem) {
        Api::error('Specified itemized pre-authorization record not found.', 404);
        exit;
    }

    $caseId = (int)$procedureItem['case_id'];

    // 3. Fetch ONLY the single parent Case Master Metadata Record
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
        Api::error('Pre-Auth parent case profile access denied or mismatch for this location window.', 404); 
        exit; 
    }

    // 4. Format timelines for this isolated single record entry row
    $procedureItem['pre_auth_id'] = (int)$procedureItem['pre_auth_id'];
    $procedureItem['case_id']     = (int)$procedureItem['case_id'];
    $procedureItem['time_ago']    = timeAgo($procedureItem['created_at']);
    $procedureItem['formatted_created_at'] = date('M d, Y h:i A', strtotime($procedureItem['created_at']));
    
    if (!empty($procedureItem['edit_time']) && $procedureItem['edit_time'] !== '0000-00-00 00:00:00') {
        $procedureItem['formatted_edit_time'] = date('M d, Y h:i A', strtotime($procedureItem['edit_time']));
    } else {
        $procedureItem['formatted_edit_time'] = '—';
    }

    if (!empty($procedureItem['approval_expire_date'])) {
        $procedureItem['formatted_expiry'] = date('M d, Y', strtotime($procedureItem['approval_expire_date']));
    }

    // 5. Structure clean response output with parent case details + single child procedure parameters
    $response = [
        'id'               => $preAuthId, // Targets the individual pre-auth row explicitly
        'pre_auth_id'      => $preAuthId,
        'case_id'          => $caseId,
        'patient_id'       => (int)$caseMetadata['patient_id'],
        'patient_name'     => $caseMetadata['patient_name'],
        'patient_dob'      => $caseMetadata['patient_dob'],
        'doctor_id'        => (int)$caseMetadata['doctor_id'],
        'doctor_name'      => $caseMetadata['doctor_name'] ?: 'Unassigned Doctor',
        'office_id'        => (int)$caseMetadata['office_id'],
        'clinic_name'      => $caseMetadata['clinic_name'],
        
        // Directly maps the selected single row values straight down to the frontend variables layout
        'procedure_id'     => (int)$procedureItem['procedure_id'],
        'procedure_name'   => $procedureItem['procedure_name'],
        'procedure_price'  => $procedureItem['procedure_price'], // Included at root tier response level
        'tooth_number'     => $procedureItem['tooth_number'],
        'p_insurance_plan' => (int)$procedureItem['p_insurance_plan'],
        'insurance_name'   => $procedureItem['insurance_name'] ?: 'No Insurance',
        'status'           => $procedureItem['status'],
        'created_at'       => $procedureItem['created_at'],
        'time_ago'         => $procedureItem['time_ago'],
        'formatted_created_at' => $procedureItem['formatted_created_at'],
        'formatted_edit_time'  => $procedureItem['formatted_edit_time'],
        'creator_name'     => $procedureItem['creator_name'] ?: 'System',
        'editor_name'      => $procedureItem['editor_name'] ?: '—',
        'approver_name'    => $procedureItem['approver_name'] ?: '—',
        'notes'            => $procedureItem['notes'] ?: '',
        
        // Single-element fallback inside an array if old timeline modals expect a list loop syntax
        'procedures_list'  => [$procedureItem]
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database retrieval failed: ' . $e->getMessage(), 500);
}

/**
 * Helper: Human-readable relative tracking timestamps
 */
function timeAgo($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '—';
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    $mins = floor($diff / 60);
    if ($mins < 60) return $mins . ' mins ago';
    $hours = floor($diff / 3600);
    if ($hours < 24) return $hours . ' hours ago';
    if ($hours < 48) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}