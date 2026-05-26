<?php
/**
 * GET api/m-pre-auth/list.php
 * List all pre-auths with status 'Sent' for management review with office, creator, and itemized procedure rows.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Check - Ensure only m-staff or admin can access this
$currentUser = $auth->user();
if ($currentUser['role'] !== 'm-staff' && $currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access.', 403);
    exit;
}

// 2. Standard Pagination
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

try {
    /**
     * 3. Relational SQL Query
     * - Pulls patient primary name from `patient` directory table.
     * - Uses a subquery/grouping structure to aggregate itemized teeth/procedures 
     * from `pre_auth_procedures` into a structured string or array layer.
     */
    $sql = "SELECT 
                pa.*, 
                o.office_name AS clinic_name,
                u.name AS creator_name,
                ins.name AS insurance_name,
                pat.name AS patient_name,
                pat.dob AS patient_dob,
                (
                    SELECT GROUP_CONCAT(CONCAT('Tooth ', pap.tooth_number, ': ', p.name) SEPARATOR ' || ')
                    FROM `pre_auth_procedures` pap
                    INNER JOIN `procedures` p ON pap.procedure_id = p.id
                    WHERE pap.pre_auth_id = pa.id
                ) AS itemized_procedures_summary
            FROM `pre-auth` pa
            INNER JOIN `patient` pat ON pa.patient_id = pat.id
            LEFT JOIN offices o ON pa.office_id = o.id
            LEFT JOIN users u ON pa.created_by = u.user_id
            LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
            WHERE pa.status = 'Sent' OR pa.status = 'Appealed'
            ORDER BY pa.id DESC
            LIMIT ? OFFSET ?";

    $records = $db->query($sql, [$limit, $offset]) ?: [];

    // 4. Get Total Count for Pagination metadata (Filtering strictly by 'Sent')
    $countSql   = "SELECT COUNT(*) as total FROM `pre-auth` WHERE status = 'Sent'";
    $totalCount = (int)($db->queryOne($countSql)['total'] ?? 0);
    $totalPages = ceil($totalCount / $limit);

    // 5. Hydrate Data Logs & Extract Itemized Lists for UI loop processing
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        
        // Convert the concatenated procedures summary into a manageable clean array for JS loops
        $r['procedures_list'] = [];
        if (!empty($r['itemized_procedures_summary'])) {
            $items = explode(' || ', $r['itemized_procedures_summary']);
            foreach ($items as $item) {
                $r['procedures_list'][] = $item;
            }
        }
        // Unset raw string to keep response payload slim
        unset($r['itemized_procedures_summary']);
    }

    // 6. Response with Complete Metadata Packet
    Api::success([
        'records'      => $records,
        'total_pages'  => $totalPages,
        'current_page' => $page,
        'total_count'  => $totalCount
    ], 'Success');

} catch (Exception $e) {
    Api::error('Management registry retrieval failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert datetime to relative string
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