<?php
/**
 * GET api/emp-pre-auth/get.php?id=XX
 * Fetch detailed information for management review.
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

// Restrict access to m-staff and admin roles
if ($currentUser['role'] !== 'm-staff' && $currentUser['role'] !== 'admin') {
    Api::error('Unauthorized. Management access required.', 403);
    exit;
}

/**
 * 2. Fetch Detailed Pre-Auth Data
 * Joins:
 * - offices: To see which clinic sent the request
 * - users: To see which staff member created the record
 * - insurance/procedures: To get the actual names for display
 */
$sql = "
    SELECT 
        pa.*,
        o.office_name as clinic_name,
        u.name as creator_name,
        ins.name AS insurance_name,
        proc.name AS procedure_name
    FROM `pre-auth` pa
    LEFT JOIN offices o ON pa.office_id = o.id
    LEFT JOIN users u ON pa.created_by = u.user_id
    LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
    LEFT JOIN procedures proc ON pa.treatment_type = proc.id
    WHERE pa.id = ?
";

$record = $db->queryOne($sql, [$id]);

if (!$record) { 
    Api::error('Record not found.', 404); 
    exit; 
}

// 3. Add Human-Readable Time & Formatting
$record['time_ago'] = timeAgo($record['created_at']);
$record['formatted_date'] = date('M d, Y h:i A', strtotime($record['created_at']));

// 4. Return the detailed object
Api::success($record);

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