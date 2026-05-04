<?php
/**
 * GET api/emp-pre-auth/get.php?id=XX
 * Fetch detailed information for a specific pre-auth.
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

$currentUserId = $_SESSION['user_id'];

// 2. Fetch Pre-Auth Details
// We use backticks for `pre-auth` and join with offices to get the clinic name
$sql = "
    SELECT 
        pa.*,
        o.office_name as clinic_name
    FROM `pre-auth` pa
    LEFT JOIN offices o ON pa.office_id = o.id
    WHERE pa.id = ? AND pa.created_by = ?
";

$record = $db->queryOne($sql, [$id, $currentUserId]);

if (!$record) { 
    Api::error('Record not found or access denied.', 404); 
    exit; 
}

// 3. Add Human-Readable Time
$record['time_ago'] = timeAgo($record['created_at']);
// Format the date for a cleaner look in the UI
$record['formatted_date'] = date('M d, Y h:i A', strtotime($record['created_at']));

// 4. Return the object
Api::success($record);

/**
 * Helper: Same logic as list.php
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}