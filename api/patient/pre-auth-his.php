<?php
/**
 * GET api/patient/pre-auth-his.php?patient_id=XX
 * Fetches the entire multi-row split Pre-Auth history timeline for a specific patient matching the new schema architecture.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Authorization Gate Checking (Bypass role assignments validation block safely)
$userRole = $auth->userRole();
if ($userRole !== 'admin' && $userRole !== 'staff' && $userRole !== 'doctor') {
    Api::error('Unauthorized access. Operational privilege credentials clearance failure.', 403);
    exit;
}

// 2. Validate Target Input Parameters
$patientId = (int)($_GET['patient_id'] ?? 0);
if (!$patientId) {
    Api::error('A valid Patient ID configuration argument is required to fetch history profiles.');
    exit;
}

// Capture current clinic context for workforce workspace security checking
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    /**
     * 3. Complete Relational Master History Compilation Query
     * Selects individual itemized line procedures from `pre-auth` joined directly onto parent master cases mapping `patient_id`.
     */
    $sql = "
        SELECT 
            pa.id AS pre_auth_id,
            pa.id AS id,
            pa.case_id,
            pa.procedure_id,
            pa.teeth_number AS tooth_number,
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
            pac.patient_id,
            pac.doctor_id,
            pac.office_id,
            o.office_name AS clinic_name,
            o.office_name AS office_name,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            doc.name AS doctor_name,
            u_creator.name AS creator_name,
            u_creator.name AS staff_name,
            u_editor.name AS editor_name,
            u_approver.name AS approver_name
        FROM `pre-auth` pa
        INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
        INNER JOIN `patient` pat ON pac.patient_id = pat.id
        LEFT JOIN `procedures` proc ON pa.procedure_id = proc.id
        LEFT JOIN `offices` o ON pac.office_id = o.id
        LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN `users` doc ON pac.doctor_id = doc.user_id
        LEFT JOIN `users` u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN `users` u_editor ON pa.edited_by = u_editor.user_id
        LEFT JOIN `users` u_approver ON pa.approved_by = u_approver.user_id
        WHERE pac.patient_id = ?
        ORDER BY pa.id DESC
    ";

    $records = $db->query($sql, [$patientId]) ?: [];
    $filteredRecords = [];

    /**
     * 4. Multi-Row Parameter Normalization & Workspace Security Gate Processing
     */
    foreach ($records as $record) {
        // Workspace Security Separation: staff users can only access records tracking their active clinic workspace location
        if (($userRole === 'staff' || $userRole === 'employee') && $sessionOfficeId > 0 && (int)$record['office_id'] !== $sessionOfficeId) {
            // Drop out of the matrix assignment loop to isolate target record entries securely
            continue;
        }

        // Clean values formatting fallback operations
        $record['pre_auth_id']    = (int)$record['pre_auth_id'];
        $record['case_id']        = (int)$record['case_id'];
        $record['patient_id']     = (int)$record['patient_id'];
        $record['doctor_id']      = (int)$record['doctor_id'];
        $record['office_id']      = (int)$record['office_id'];
        $record['procedure_id']   = (int)$record['procedure_id'];
        $record['p_insurance_plan'] = (int)$record['p_insurance_plan'];

        $record['notes']          = !empty($record['notes']) ? $record['notes'] : '';
        $record['doctor_name']    = $record['doctor_name'] ?: 'Unassigned Doctor';
        $record['clinic_name']    = $record['clinic_name'] ?: 'Unknown Clinic Location';
        $record['insurance_name'] = $record['insurance_name'] ?: 'No Insurance';
        $record['creator_name']   = $record['creator_name'] ?: 'System';
        $record['editor_name']    = $record['editor_name'] ?: '—';
        $record['approver_name']  = $record['approver_name'] ?: '—';
        $record['p_insurance_id'] = $record['insurance_name'];

        // Format dynamic patient demographics attributes
        if (!empty($record['patient_dob'])) {
            $record['p_dob'] = date('M d, Y', strtotime($record['patient_dob']));
        } else {
            $record['p_dob'] = '—';
        }

        // Apply fallback timeline UI date template strings matching schema specification properties
        $record['created_at_fmt']      = date('m/d/Y h:i A', strtotime($record['created_at']));
        $record['formatted_date']      = date('M d, Y h:i A', strtotime($record['created_at']));
        $record['formatted_created_at'] = date('M d, Y h:i A', strtotime($record['created_at']));
        $record['time_ago']            = timeAgo($record['created_at']);
        
        if (!empty($record['edit_time']) && $record['edit_time'] !== '0000-00-00 00:00:00') {
            $record['formatted_edit_time'] = date('M d, Y h:i A', strtotime($record['edit_time']));
        } else {
            $record['formatted_edit_time'] = '—';
        }

        if (!empty($record['approval_expire_date'])) {
            $record['formatted_expiry'] = date('M d, Y', strtotime($record['approval_expire_date']));
        } else {
            $record['formatted_expiry'] = '—';
        }

        if (!empty($record['appointment_date']) && $record['appointment_date'] !== '0000-00-00 00:00:00') {
            $record['appointment_date_fmt'] = date('M d, Y h:i A', strtotime($record['appointment_date']));
        } else {
            $record['appointment_date_fmt'] = '—';
        }

        // Structural fallback wrapper array to guarantee backward compatibility with multi-procedure loop expectations
        $record['procedures_list'] = [[
            'procedure_id'   => $record['procedure_id'],
            'tooth_number'   => $record['tooth_number'],
            'procedure_name' => $record['procedure_name']
        ]];

        $filteredRecords[] = $record;
    }

    // 5. Return compiled records data payload context array matrix structure 
    Api::success($filteredRecords, 'Patient authorization history timeline tracking profiles compiled successfully.');

} catch (Exception $e) {
    Api::error('Global database query engine lookup processing failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Human-readable relative calculation timestamps metric system
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