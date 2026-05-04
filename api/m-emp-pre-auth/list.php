<?php
/**
 * GET api/emp-pre-auth/list.php
 * List only pre-auths created by the logged-in staff member.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Standard Pagination
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$currentUserId = $_SESSION['user_id'];

// 2. The SQL Query (Filtered strictly by created_by)
$sql = "SELECT 
    *
FROM `pre-auth`
WHERE created_by = ? AND status = 'Sent'
ORDER BY id DESC
LIMIT ? OFFSET ?";

$records = $db->query($sql, [$currentUserId, $limit, $offset]);

// 3. Add "Time Ago" logic to each record
foreach ($records as &$r) {
    $r['time_ago'] = timeAgo($r['created_at']);
}



// 5. Response with Metadata
Api::success($records, 'Success');

/**
 * Helper: Convert datetime to relative string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}