<?php
/**
 * GET api/m-labs/get.php?id=XX
 * Fetch every detail for a single lab case for Management Review.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('m-staff')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    Api::error('ID is required.');
    exit;
}

$sql = "SELECT 
            l.*, 
            o.office_name AS office_location,
            u1.name AS doctor_name,
            u2.name AS sent_by_name,
            u3.name AS received_by_name,
            ct.name AS case_type_name,
            proc.name AS next_visit_procedure
        FROM labs l
        LEFT JOIN offices o ON l.office_id = o.id
        LEFT JOIN users u1 ON l.provider = u1.user_id
        LEFT JOIN users u2 ON l.sent_by = u2.user_id
        LEFT JOIN users u3 ON l.received_by = u3.user_id
        LEFT JOIN case_type ct ON l.case_type = ct.id
        LEFT JOIN procedures proc ON l.next_visit = proc.id
        WHERE l.id = ? LIMIT 1";

$r = $db->queryOne($sql, [$id]);

if (!$r) {
    Api::error('Lab case not found.');
    exit;
}

// Data Transformation
$r['fmt_sent_date']     = $r['date_sent'] ? date('M d, Y', strtotime($r['date_sent'])) : '—';
$r['fmt_received_date'] = $r['date_received'] ? date('M d, Y', strtotime($r['date_received'])) : 'Pending';
$r['display_arch']      = formatArchInfo($r['u_arch'], $r['l_arch']);

Api::success($r);

/**
 * Re-using your format helper
 */
function formatArchInfo($u, $l) {
    if ($u === 'Full' && $l === 'Full') return "Both Arches";
    if ($u === 'Full') return "Upper Arch";
    if ($l === 'Full') return "Lower Arch";
    $parts = [];
    if (!empty($u)) $parts[] = "Upper: $u";
    if (!empty($l)) $parts[] = "Lower: $l";
    return !empty($parts) ? implode(' | ', $parts) : "N/A";
}