<?php
/**
 * GET api/admin/pre-auth/list.php
 * Global list for Admin Monitoring with multi-dimensional filtering.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Check
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Capture Filters & Pagination
$limit  = min((int) ($_GET['limit'] ?? 15), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null;
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; // Can be a string or an array

// 3. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

// Patient Name Filter
if (!empty($patientName)) {
    $where[] = "(pa.p_first_name LIKE ? OR pa.p_last_name LIKE ?)";
    $params[] = '%' . $patientName . '%';
    $params[] = '%' . $patientName . '%';
}

if (!empty($clinicId)) {
    $where[] = "pa.office_id = ?";
    $params[] = (int)$clinicId;
}

// Start Date Filter
if (!empty($startDate)) {
    $where[] = "DATE(pa.created_at) >= ?";
    $params[] = $startDate;
}

// End Date Filter
if (!empty($endDate)) {
    $where[] = "DATE(pa.created_at) <= ?";
    $params[] = $endDate;
}

// --- ENHANCED: Handle Multiple Statuses ---
if (!empty($status)) {
    if (is_array($status)) {
        // Create placeholders (?, ?, ?) for the array length
        $placeholders = implode(',', array_fill(0, count($status), '?'));
        $where[] = "pa.status IN ($placeholders)";
        foreach ($status as $s) {
            $params[] = $s;
        }
    } else {
        // Standard single status logic
        $where[] = "pa.status = ?";
        $params[] = $status;
    }
}
// ------------------------------------------

$whereSql = implode(" AND ", $where);

/**
 * 4. Main Query
 */
$sql = "SELECT 
            pa.*, 
            o.office_name, 
            u_creator.name AS creator_name,
            u_approver.name AS approver_name,
            ins.name AS insurance_name,
            proc.name AS procedure_name
        FROM `pre-auth` pa
        LEFT JOIN offices o ON pa.office_id = o.id
        LEFT JOIN users u_creator ON pa.created_by = u_creator.user_id
        LEFT JOIN users u_approver ON pa.approved_by = u_approver.user_id
        LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN procedures proc ON pa.treatment_type = proc.id
        WHERE $whereSql
        ORDER BY pa.id DESC
        LIMIT ? OFFSET ?";

// Merge pagination params with filter params
$queryData = array_merge($params, [$limit, $offset]);
$records = $db->query($sql, $queryData);

// 5. Total Count for Pagination
$countSql = "SELECT COUNT(*) as total FROM `pre-auth` pa WHERE $whereSql";
$totalCount = $db->queryOne($countSql, $params)['total'];
$totalPages = ceil($totalCount / $limit);

// 6. Data Formatting for JS Stages
foreach ($records as &$r) {
    $r['time_ago'] = timeAgo($r['created_at']);
    $r['created_at_date'] = date('m/d/Y', strtotime($r['created_at']));
    
    if (!empty($r['appointment_date'])) {
        $r['appointment_date_fmt'] = date('m/d/Y g:i A', strtotime($r['appointment_date']));
    } else {
        $r['appointment_date_fmt'] = null;
    }
}

// 7. Response
Api::success([
    'records'       => $records,
    'total_records' => (int)$totalCount,
    'total_pages'   => (int)$totalPages,
    'current_page'  => $page
], 'Global records fetched successfully');

/**
 * Helper: Convert datetime to relative string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 84400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}