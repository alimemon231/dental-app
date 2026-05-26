<?php
/**
 * GET api/admin-pre-auth/get.php?id=XX
 * Global multi-role fetch for Admin & Staff pre-auth lifecycle monitoring.
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
    Api::error('A valid Pre-Auth ID is required.');
    exit;
}

// Capture current location scope context for cross-checking if required
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    /**
     * 3. Complete Master Query
     * Selects all parent record elements and joins across the entire relational matrix.
     * Note: Admin roles are allowed to view cross-office records globally, 
     * while staff roles can be tightly restricted here or via downstream filters.
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
        WHERE pa.id = ?
    ";

    $record = $db->queryOne($sql, [$id]);

    if (!$record) {
        Api::error('Record not found.', 404);
        exit;
    }

    // Role-based scope checking safeguard: staff users can only view records matching their current workspace session
    if ($userRole === 'staff' && $sessionOfficeId > 0 && (int)$record['office_id'] !== $sessionOfficeId) {
        Api::error('Access denied. This record belongs to a different clinic workspace.', 403);
        exit;
    }

    /**
     * 4. Audit Processing & Data Mapping Fallbacks for UI Fields
     */
    $record['notes'] = !empty($record['notes']) ? $record['notes'] : 'No additional notes provided by operational staff.';
    
    // Safely format patient dates of birth
    if (!empty($record['patient_dob'])) {
        $record['p_dob'] = date('M d, Y', strtotime($record['patient_dob']));
    } elseif (!empty($record['p_dob'])) {
        $record['p_dob'] = date('M d, Y', strtotime($record['p_dob']));
    } else {
        $record['p_dob'] = '—';
    }

    // Standard structural dates formatting transformations
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

    // Alias fallback strings mapping payload definitions to handle older Javascript assignments
    $record['p_insurance_id'] = $record['insurance_name'] ?? '—'; 

    /**
     * 5. Collect Multi-Row Linked Treatment Matrices
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
    $record['procedures_list'] = $db->query($procSql, [$id]) ?: [];

    // Map compiled arrays to string values as reliable fallback structures for standard list view columns
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
        // Fallback to legacy single-field structure values if sub-query array turns up empty
        $record['procedure_name'] = $record['procedure_name'] ?? 'No procedures assigned';
        $record['tooth_numbers']  = $record['tooth_numbers'] ?? '—';
    }

    // 6. Return standard structured success envelope payload
    Api::success($record);

} catch (Exception $e) {
    Api::error('Database retrieval or relation mapping failed: ' . $e->getMessage(), 500);
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