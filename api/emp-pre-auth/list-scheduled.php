<?php
/**
 * GET api/emp-pre-auth/list-scheduled.php
 * List only scheduled pre-auths for the logged-in office staff with pagination.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Get Current User Info
$currentUserId = $_SESSION['user_id'];

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

/**
 * 2. The SQL Queries
 * - Main Query: Fetches records for current user where status is 'Scheduled'
 * - Count Query: Fetches total number of 'Scheduled' records for pagination
 */
$sql = "SELECT 
            pa.*, 
            ins.name AS insurance_name, 
            proc.name AS procedure_name,
            u_app.name AS approver_name
        FROM `pre-auth` pa
        LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
        LEFT JOIN `procedures` proc ON pa.treatment_type = proc.id
        LEFT JOIN `users` u_app ON pa.approved_by = u_app.user_id
        WHERE pa.created_by = ? 
          AND pa.status = 'Scheduled'
        ORDER BY pa.appointment_date ASC
        LIMIT $limit OFFSET $offset";

$countSql = "SELECT COUNT(*) as total FROM `pre-auth` WHERE created_by = ? AND status = 'Scheduled'";

try {
    $records = $db->query($sql, [$currentUserId]);
    $totalResult = $db->queryOne($countSql, [$currentUserId]);
    
    $total_records = $totalResult['total'];
    $total_pages = ceil($total_records / $limit);

    // 3. Formatting and Helper Data
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        // Primary date shown in the management table
        $r['formatted_date'] = date('M d, Y', strtotime($r['created_at']));
        // Format the actual appointment time for display
        $r['appointment_date_fmt'] = date('M d, Y h:i A', strtotime($r['appointment_date']));
    }

    // 4. Clean Response matching your expected JS structure
    Api::success([
        'records' => $records,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page
    ], 'Success');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
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