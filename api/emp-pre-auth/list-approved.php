<?php
/**
 * GET api/emp-pre-auth/list-approved.php
 * List only approved pre-auths for the logged-in office staff.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Get Current User Info
$currentUserId = $_SESSION['user_id'];

/**
 * 2. The SQL Query
 * - Filters by pa.created_by (Current User)
 * - Filters by pa.status = 'Approved'
 * - Joins 'users' as 'u_app' to get the Name of the management staff who approved it
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
          AND pa.status = 'Approved'
          AND pa.appointment_date IS NULL
        ORDER BY pa.id DESC";

try {
    $records = $db->query($sql, [$currentUserId]);

    // 3. Formatting and Helper Data
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        // Format date for the "Created At" column in your JS table
        $r['formatted_date'] = date('M d, Y', strtotime($r['created_at']));
    }

    // 4. Clean Response
    Api::success(['records' => $records], 'Success');

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