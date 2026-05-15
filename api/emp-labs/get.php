<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$id = $_GET['id'] ?? null;

if (!$id) {
    Api::error('Lab ID is required.');
    exit;
}

// Fetch with all necessary joins for the detailed view
$sql = "SELECT 
    l.*,
    u.name AS doctor_name,
    ct.name AS case_type_name,
    ls.name AS next_visit_step_name,
    lp.name AS lab_partner_name
FROM labs l
LEFT JOIN users u ON l.provider = u.user_id
LEFT JOIN case_type ct ON l.case_type = ct.id
LEFT JOIN lab_steps ls ON l.next_visit = ls.id
LEFT JOIN labs_patner lp ON l.lab_provider = lp.id
WHERE l.id = ?";

$record = $db->queryOne($sql, [$id]);

if (!$record) {
    Api::error('Lab record not found.');
    exit;
}

// Formatting the date for the UI
$record['date_sent'] = $record['date_sent'] ? date('M d, Y', strtotime($record['date_sent'])) : '—';
$record['date_received'] = $record['date_received'] ? date('M d, Y', strtotime($record['date_received'])) : '—';
$record['date_scheduled'] = $record['date_scheduled'] ? date('M d, Y', strtotime($record['date_scheduled'])) : '—';

Api::success($record);