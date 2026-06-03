<?php
/**
 * GET api/admin-pre-auth/get.php?id=XX
 * Global multi-role fetch for Admin & Staff pre-auth lifecycle monitoring.
 * Fetches ONLY the single itemized record specified by the requested ID using the new schema.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Authorization Gate
$userRole = $auth->userRole();
if ($userRole !== 'admin' && $userRole !== 'staff') {
    Api::error('Unauthorized access. Administrative or operational staff privileges required.', 403);
    exit;
}

// 2. Validate input parameters
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Api::error('A valid Pre-Auth row item record reference ID is required.');
    exit;
}

// Capture workspace context filters if applicable
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    /**
     * 3. Target Query Execution
     * Pulls only the specific row item details (`pa.id = ?`) while safely joining the 
     * new case setup metadata profiles (`pre_auth_cases`).
     */
    $sql = "
        SELECT 
            pa.id AS pre_auth_id,
            pa.id,
            pa.case_id,
            pa.procedure_id,
            pa.teeth_number AS tooth_number,
            pa.p_insurance_plan,
            pa.status,
            pa.notes,
            pa.created_at,
            pa.appointment_date,
            pa.approval_expire_date,
            pa.edit_time,
            proc.name AS procedure_name,
            ins.name AS insurance_name,
            u_creator.name AS creator_name,
            u_creator.name AS staff_name,
            u_editor.name AS editor_name,
            u_approver.name AS approver_name,
            pac.patient_id,
            pac.doctor_id,
            pac.office_id,
            o.office_name AS clinic_name,
            o.office_name,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            doc.name AS doctor_name
        FROM `pre-auth` pa
        INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
        INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
        LEFT JOIN `patient` pat ON pac.patient_id = pat.id
        LEFT JOIN `offices` o ON pac.office_id = o.id
        LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN `users` doc ON pac.doctor_id = doc.user_id
        LEFT JOIN `users` u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN `users` u_editor ON pa.edited_by = u_editor.user_id
        LEFT JOIN `users` u_approver ON pa.approved_by = u_approver.user_id
        WHERE pa.id = ?
        LIMIT 1
    ";

    $record = $db->queryOne($sql, [$id]);

    if (!$record) {
        Api::error('The specified pre-authorization record row does not exist.', 404);
        exit;
    }

    // Role-based workspace checking safeguard constraint for non-admin operational staff
    if ($userRole === 'staff' && $sessionOfficeId > 0 && (int)$record['office_id'] !== $sessionOfficeId) {
        Api::error('Access denied. This record belongs to a different clinic workspace.', 403);
        exit;
    }

    /**
     * 4. Data Transformations, Formatters & Safe Fallback Bindings
     */
    $notes = !empty($record['notes']) ? $record['notes'] : 'No additional notes provided by operational staff.';
    $createdAt = $record['created_at'] ?: date('Y-m-d H:i:s');
    $editTime = $record['edit_time'];
    $appointmentDate = $record['appointment_date'];

    $pDob = '—';
    if (!empty($record['patient_dob'])) {
        $pDob = date('M d, Y', strtotime($record['patient_dob']));
    }

    // Formatted Time Parameters for UI Components
    $createdAtFmt = date('m/d/Y h:i A', strtotime($createdAt));
    $formattedDate = date('M d, Y h:i A', strtotime($createdAt));
    $timeAgo = timeAgo($createdAt);
    $formattedEditTime = $editTime ? date('M d, Y h:i A', strtotime($editTime)) : null;
    $appointmentDateFmt = $appointmentDate ? date('M d, Y h:i A', strtotime($appointmentDate)) : '—';

    // Normalize direct list-view fallback values matching the current item
    $flatProcedureName = $record['procedure_name'] ?: 'No procedure assigned';
    $flatToothNumbers = $record['tooth_number'] ?: '—';

    /**
     * 5. Emulate Single-Row Array Reference Envelope
     * Wraps the current procedure row inside the `procedures_list` array to ensure 
     * JavaScript template iterations safely loop over the target record without crashing.
     */
    $proceduresList = [
        [
            'pre_auth_id'    => (int)$record['pre_auth_id'],
            'procedure_id'   => (int)$record['procedure_id'],
            'procedure_name' => $flatProcedureName,
            'tooth_number'   => $record['tooth_number'],
            'status'         => $record['status']
        ]
    ];

    // 6. Assemble Normalized Structure Payload matching Javascript Context Maps
    $response = [
        'id'                 => (int)$record['id'], 
        'case_id'            => (int)$record['case_id'],
        'patient_id'         => (int)$record['patient_id'],
        'patient_name'       => $record['patient_name'],
        'patient_dob'        => $record['patient_dob'],
        'p_dob'              => $pDob,
        'doctor_id'          => (int)$record['doctor_id'],
        'doctor_name'        => $record['doctor_name'] ?: 'Unassigned Doctor',
        'office_id'          => (int)$record['office_id'],
        'clinic_name'        => $record['clinic_name'],
        'office_name'        => $record['office_name'],
        
        // Primary record attributes fallbacks
        'p_insurance_plan'   => (int)$record['p_insurance_plan'],
        'insurance_name'     => $record['insurance_name'] ?? '—',
        'p_insurance_id'     => $record['insurance_name'] ?? '—',
        'status'             => $record['status'] ?: 'Requested',
        'notes'              => $notes,
        
        // Audit Metadata metrics
        'creator_name'       => $record['creator_name'] ?? 'System User',
        'staff_name'         => $record['staff_name'] ?? 'System User',
        'editor_name'        => $record['editor_name'] ?? null,
        'approver_name'      => $record['approver_name'],
        'approval_expire_date'      => $record['approval_expire_date'] ,
        'created_at'         => $createdAt,
        'created_at_fmt'     => $createdAtFmt,
        'formatted_date'     => $formattedDate,
        'time_ago'           => $timeAgo,
        'edit_time'          => $editTime,
        'formatted_edit_time'=> $formattedEditTime,
        'appointment_date'   => $appointmentDate,
        'appointment_date_fmt'=> $appointmentDateFmt,

        // Legacy Flat String Map Properties
        'procedure_name'     => $flatProcedureName,
        'tooth_numbers'      => $flatToothNumbers,
        
        // Nested procedure array isolating only this specific target selection row
        'procedures_list'    => $proceduresList
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database retrieval or relation mapping failed: ' . $e->getMessage(), 500);
}

/**
 * Helper: Human-readable relative time string calculation
 */
function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '—';
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