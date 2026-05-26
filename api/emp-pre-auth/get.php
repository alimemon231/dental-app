<?php
/**
 * GET api/emp-pre-auth/get.php?id=XX
 * Fetch detailed information for a specific pre-auth with linked names and procedures list.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validation
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Pre-Auth ID is required.'); 
    exit; 
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    // 2. Fetch Master Pre-Auth Details linked with Patient, Insurance, and Users
    $sql = "
        SELECT 
            pa.*,
            o.office_name AS clinic_name,
            ins.name AS insurance_name,
            pat.name AS patient_name,
            pat.dob AS patient_dob,
            u_creator.name AS creator_name,
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
        Api::error('Record not found or access denied to this workspace location.', 404); 
        exit; 
    }

    // 3. Append Human-Readable Relative Timestamps
    $record['time_ago'] = timeAgo($record['created_at']);
    $record['formatted_date'] = date('M d, Y h:i A', strtotime($record['created_at']));
    
    if (!empty($record['edit_time'])) {
        $record['formatted_edit_time'] = date('M d, Y h:i A', strtotime($record['edit_time']));
    }

    // 4. Collect Linked Multi-row Procedures
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

    // 5. Return JSON Payload
    Api::success($record);

} catch (Exception $e) {
    Api::error('Database retrieval failed: ' . $e->getMessage(), 500);
}

/**
 * Helper: Human-readable time
 */
function timeAgo($datetime) {
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