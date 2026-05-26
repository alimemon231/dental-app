<?php
/**
 * GET api/m-pre-auth/get.php?id=XX
 * Fetch fully relational detailed pre-auth info, including multi-procedure child loops, for management.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validation & Role Check
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Pre-Auth ID is required.'); 
    exit; 
}

$currentUser = $auth->user();

// Restrict access explicitly to management layers (m-staff and admin)
if ($currentUser['role'] !== 'm-staff' && $currentUser['role'] !== 'admin') {
    Api::error('Unauthorized. Management access required.', 403);
    exit;
}

try {
    /**
     * 2. Fetch Master Parent Data
     * Relational Joins map the base patient table registry, workspace office location context, 
     * user submitter identifiers, and matching audit profiles safely.
     */
    $sql = "
        SELECT 
            pa.*,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            o.office_name AS clinic_name,
            u_creator.name AS creator_name,
            u_editor.name AS editor_name,
            u_approver.name AS approver_name,
            ins.name AS insurance_name
        FROM `pre-auth` pa
        INNER JOIN `patient` pat ON pa.patient_id = pat.id
        LEFT JOIN offices o ON pa.office_id = o.id
        LEFT JOIN users u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN users u_editor ON pa.edited_by = u_editor.user_id
        LEFT JOIN users u_approver ON pa.approved_by = u_approver.user_id
        LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
        WHERE pa.id = ?
        LIMIT 1
    ";

    $record = $db->queryOne($sql, [$id]);

    if (!$record) { 
        Api::error('Pre-authorization record not found.', 404); 
        exit; 
    }

    /**
     * 3. Fetch Child Array Structure Stack
     * Collects all individual procedures and teeth mapped dynamically to this pre-auth.
     */
    $proceduresSql = "
        SELECT 
            pap.tooth_number,
            proc.name AS procedure_name
        FROM `pre_auth_procedures` pap
        INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
        WHERE pap.pre_auth_id = ?
        ORDER BY pap.id ASC
    ";

    $record['procedures_list'] = $db->query($proceduresSql, [$id]) ?: [];

    // 4. Operational Time Formats & Serialization Adjustments
    $record['time_ago'] = timeAgo($record['created_at']);
    $record['formatted_date'] = date('M d, Y h:i A', strtotime($record['created_at']));
    
    // Format last modification stamp for audit tracker UI
    $record['formatted_edit_time'] = !empty($record['edit_time']) 
        ? date('M d, Y h:i A', strtotime($record['edit_time'])) 
        : '';

    // 5. Direct Success Response 
    Api::success($record);

} catch (Exception $e) {
    Api::error('Database retrieval operational failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Relative time string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}