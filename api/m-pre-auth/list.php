<?php
/**
 * GET api/m-pre-auth/list.php
 * List all pre-auths for management review with office and creator details.
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

/**
 * 3. The SQL Query
 * - Joins 'offices' to get clinic name
 * - Joins 'users' to get the name of the staff who created it
 * - Joins 'insurance' & 'procedures' for the human-readable names
 */
$sql = "SELECT 
            pa.*, 
            o.office_name AS clinic_name,
            u.name AS creator_name,
            ins.name AS insurance_name,
            proc.name AS procedure_name
        FROM `pre-auth` pa
        LEFT JOIN offices o ON pa.office_id = o.id
        LEFT JOIN users u ON pa.created_by = u.user_id
        LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN procedures proc ON pa.treatment_type = proc.id
        WHERE pa.status = 'Sent'
        ORDER BY pa.id DESC
        LIMIT ? OFFSET ?";

$records = $db->query($sql, [$limit, $offset]);

// 4. Get Total Count for Pagination metadata
$totalCount = $db->queryOne("SELECT COUNT(*) as total FROM `pre-auth`")['total'];
$totalPages = ceil($totalCount / $limit);

// 5. Add "Time Ago" logic
foreach ($records as &$r) {
    $r['time_ago'] = timeAgo($r['created_at']);
}

// 6. Response with Metadata
Api::success([
    'records'      => $records,
    'total_pages'  => $totalPages,
    'current_page' => $page,
    'total_count'  => $totalCount
], 'Success');

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