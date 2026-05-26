<?php
/**
 * GET api/emp-pre-auth/list-scheduled.php
 * List only scheduled pre-auths for the logged-in clinic office context with pagination and full relation mapping matrices.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$currentUserId   = $_SESSION['user_id'];

if ($sessionOfficeId <= 0) {
    Api::error('Workspace session expired or clinic scope context lost.', 400);
    exit;
}

// Pagination Setup
$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Core Query: Complete relational outer join schema matching database structure
    $sql = "SELECT 
                pa.*, 
                p.name AS patient_name,
                p.dob AS patient_dob,
                o.office_name,
                ins.name AS insurance_name, 
                u_app.name AS approver_name,
                u_edt.name AS editor_name,
                u_cre.name AS creator_name
            FROM `pre-auth` pa
            LEFT JOIN `patient` p ON pa.patient_id = p.id
            LEFT JOIN `offices` o ON pa.office_id = o.id
            LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
            LEFT JOIN `users` u_app ON pa.approved_by = u_app.user_id
            LEFT JOIN `users` u_edt ON pa.edited_by = u_edt.user_id
            LEFT JOIN `users` u_cre ON pa.created_by = u_cre.user_id
            WHERE pa.office_id = ? 
              AND pa.status = 'Scheduled'
            ORDER BY pa.appointment_date ASC
            LIMIT $limit OFFSET $offset";

    $records = $db->query($sql, [$sessionOfficeId]) ?: [];

    // 2. Count Query: Scope constrained directly to the active clinic workspace
    $countSql    = "SELECT COUNT(*) as total FROM `pre-auth` WHERE office_id = ? AND status = 'Scheduled'";
    $totalResult = $db->queryOne($countSql, [$sessionOfficeId]);
    
    $total_records = (int)($totalResult['total'] ?? 0);
    $total_pages   = ceil($total_records / $limit);

    // 3. Formatting and Itemized Sub-Procedures Matrix Mapping Loops
    foreach ($records as &$r) {
        $r['time_ago']       = timeAgo($r['created_at']);
        $r['formatted_date'] = !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '—';
        
        // Handle appointment dates formatting safely
        if (!empty($r['appointment_date'])) {
            $r['appointment_date_fmt'] = date('M d, Y h:i A', strtotime($r['appointment_date']));
        } else {
            $r['appointment_date_fmt'] = '—';
        }

        // Fetch itemized treatment rows assigned to this pre-auth reference mapping ID
        $proceduresList = $db->query(
            "SELECT pap.tooth_number, proc.name AS procedure_name 
             FROM `pre_auth_procedures` pap
             INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
             WHERE pap.pre_auth_id = ?",
            [(int)$r['id']]
        );
        $r['procedures_list'] = $proceduresList ?: [];
    }
    unset($r); // Break reference safely

    // 4. Return clean, envelope-wrapped success records array matching JS expectation
    Api::success($records, 'Success');

} catch (Exception $e) {
    Api::error('Database relational execution or sub-query loop failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert datetime to relative string
 */
function timeAgo($datetime) {
    if (empty($datetime)) return '—';
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}