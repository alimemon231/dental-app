<?php
/**
 * GET api/patient/pre-auth-his.php?patient_id=XX
 * Fetches the entire multi-row Pre-Auth history for a specific patient.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Authorization Gate
$userRole = $auth->userRole();
if ($userRole !== 'admin' && $userRole !== 'staff' && $userRole !== 'doctor') {
    Api::error('Unauthorized access. Operational privileges required.', 403);
    exit;
}

// 2. Validate input parameter
$patientId = (int)($_GET['patient_id'] ?? 0);
if (!$patientId) {
    Api::error('A valid Patient ID is required to fetch pre-auth history.');
    exit;
}

// Capture current clinic context for workspace security scoping rules
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    /**
     * 3. Complete Relational Master History Query
     * Filters strictly by patient_id instead of a single document record key.
     */
    $sql = "
        SELECT 
            pa.*,
            o.office_name,
            o.office_name AS clinic_name,
            ins.name AS insurance_name,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            u_creator.name AS creator_name,
            u_creator.name AS staff_name,
            u_editor.name AS editor_name,
            u_approver.name AS approver_name
        FROM `pre-auth` pa
        INNER JOIN `patient` pat ON pa.patient_id = pat.id
        LEFT JOIN offices o ON pa.office_id = o.id
        LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN users u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN users u_editor ON pa.edited_by = u_editor.user_id
        LEFT JOIN users u_approver ON pa.approved_by = u_approver.user_id
        WHERE pa.patient_id = ?
        ORDER BY pa.id DESC
    ";

    $records = $db->query($sql, [$patientId]) ?: [];

    /**
     * 4. Multi-Row Parsing Loop & Security Workspace Gate checking
     */
    foreach ($records as &$record) {
        // Workspace Security: staff users can only view records matching their clinic session
        if (($userRole === 'staff' || $userRole === 'employee') && $sessionOfficeId > 0 && (int)$record['office_id'] !== $sessionOfficeId) {
            // Mask or omit cross-clinic data if strict data separation rule applies
            continue;
        }

        $record['notes'] = !empty($record['notes']) ? $record['notes'] : 'No additional notes provided by operational staff.';
        
        // Safely format patient dates of birth
        if (!empty($record['patient_dob'])) {
            $record['p_dob'] = date('M d, Y', strtotime($record['patient_dob']));
        } else {
            $record['p_dob'] = '—';
        }

        // Apply fallback timeline UI date strings
        $record['created_at_fmt'] = date('m/d/Y h:i A', strtotime($record['created_at']));
        $record['formatted_date'] = date('M d, Y h:i A', strtotime($record['created_at']));
        $record['time_ago']        = timeAgo($record['created_at']);
        
        if (!empty($record['edit_time'])) {
            $record['formatted_edit_time'] = date('M d, Y h:i A', strtotime($record['edit_time']));
        } else {
            $record['formatted_edit_time'] = null;
        }

        if (!empty($record['appointment_date'])) {
            $record['appointment_date_fmt'] = date('M d, Y h:i A', strtotime($record['appointment_date']));
        } else {
            $record['appointment_date_fmt'] = '—';
        }

        $record['p_insurance_id'] = $record['insurance_name'] ?? '—'; 

        /**
         * 5. Collect Multi-Row Linked Treatment Matrices for this individual entry
         */
        $procSql = "
            SELECT 
                pap.procedure_id,
                pap.tooth_number,
                proc.name AS procedure_name
            FROM `pre_auth_procedures` pap
            INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
            WHERE pap.pre_auth_id = ?
        ";
        $record['procedures_list'] = $db->query($procSql, [$record['id']]) ?: [];

        // Compile relational arrays into readable strings for direct front-end consumption
        if (!empty($record['procedures_list'])) {
            $names = [];
            $teeth = [];
            foreach ($record['procedures_list'] as $item) {
                $names[] = $item['procedure_name'];
                $teeth[] = $item['tooth_number'];
            }
            $record['procedure_name'] = implode(', ', $names);
            $record['tooth_numbers']  = implode(', ', $teeth);
        } else {
            $record['procedure_name'] = $record['procedure_name'] ?? 'No procedures assigned';
            $record['tooth_numbers']  = $record['tooth_numbers'] ?? '—';
        }
    }
    unset($record); // Clear reference pointer

    // 6. Return response array structure via the standard success envelope
    Api::success($records, 'Patient authorization history timeline fetched successfully.');

} catch (Exception $e) {
    Api::error('Database lookup failed: ' . $e->getMessage(), 500);
}

/**
 * Helper: Human-readable relative time
 */
function timeAgo($datetime) {
    if (empty($datetime)) return '—';
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