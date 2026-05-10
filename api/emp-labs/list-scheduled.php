<?php
/**
 * GET api/emp-labs/list-scheduled.php
 * Fetch only "Scheduled" lab cases for the logged-in staff's specific clinic.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Identify the current user's office via the mapping table
$currentUserId = $_SESSION['user_id'];
$office = $db->queryOne("SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1", [$currentUserId]);
$officeId = $office ? $office['office_id'] : 0;

// If the user isn't assigned to an office, return empty to prevent data leaks
if (!$officeId) {
    Api::success([], 'No office assigned to this user.');
    exit;
}

/**
 * 2. SQL Query
 * Filter by the retrieved $officeId and the 'Scheduled' status.
 */
$sql = "SELECT 
            l.id,
            l.p_name,
            l.date_scheduled,
            l.status,
            u1.name AS doctor_name,
            ct.name AS case_type_name
        FROM labs l
        LEFT JOIN users u1 ON l.provider = u1.user_id
        LEFT JOIN case_type ct ON l.case_type = ct.id
        WHERE l.office_id = ? 
        AND l.status = 'Scheduled'
        ORDER BY l.date_scheduled ASC";

$records = $db->query($sql, [$officeId]);

// 3. Data Transformation
foreach ($records as &$r) {
    $r['fmt_scheduled_date'] = $r['date_scheduled'] 
        ? date('M d, Y', strtotime($r['date_scheduled'])) 
        : 'TBD';
}

// 4. Response
Api::success($records);