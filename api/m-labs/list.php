<?php
/**
 * GET api/m-labs/list-all.php
 * Fetch all details for every lab case across all clinics for Management Review.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Management Security Check
if (!$auth->hasRole('m-staff')) {
    Api::error('Unauthorized access. Management only.', 403);
    exit;
}

// 2. Pagination Setup
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

/**
 * 3. Comprehensive SQL Query
 * Fetches every piece of information by joining relevant tables according to the new schema:
 * - patient (p): Patient name details
 * - offices (o): Office/Location data
 * - users (u1): The Provider/Doctor
 * - users (u2): The staff who sent the record
 * - users (u3): The staff who marked it as received
 * - case_type (ct): Category of the lab work
 * - lab_steps (ls): Planned step/procedure for next visit
 * - labs_patner (lp): Lab partner company name
 * - users (ue): The staff who edited the record
 */
$sql = "SELECT 
            l.*, 
            p.name AS patient_name,
            o.office_name AS office_location,
            u1.name AS doctor_name,
            u2.name AS created_by_name,
            u3.name AS received_by_name,
            ue.name AS edited_by_name,
            ct.name AS case_type_name,
            ls.name AS next_visit_procedure,
            lp.name AS lab_partner_name
        FROM labs l
        LEFT JOIN patient p ON l.p_id = p.id
        LEFT JOIN offices o ON l.office_id = o.id
        LEFT JOIN users u1 ON l.provider = u1.user_id
        LEFT JOIN users u2 ON l.sent_by = u2.user_id
        LEFT JOIN users u3 ON l.received_by = u3.user_id
        LEFT JOIN users ue ON l.edited_by = ue.user_id
        LEFT JOIN case_type ct ON l.case_type = ct.id
        LEFT JOIN lab_steps ls ON l.next_visit = ls.id
        LEFT JOIN labs_patner lp ON l.lab_provider = lp.id
        WHERE l.status = 'Received'
        ORDER BY l.id DESC
        LIMIT ? OFFSET ?";

$records = $db->query($sql, [$limit, $offset]);

// 4. Data Transformation for UI
foreach ($records as &$r) {
    // Format Dates
    $r['fmt_sent_date']      = $r['date_sent'] ? date('M d, Y', strtotime($r['date_sent'])) : '—';
    $r['fmt_received_date']  = $r['date_received'] ? date('M d, Y', strtotime($r['date_received'])) : 'Pending';
    $r['fmt_scheduled_date'] = $r['date_scheduled'] ? date('M d, Y', strtotime($r['date_scheduled'])) : '—';
    $r['fmt_edited_at']      = $r['edited_at'] && $r['edited_at'] !== '0000-00-00 00:00:00' ? date('M d, Y g:i A', strtotime($r['edited_at'])) : '—';
    
    // Arch/Teeth Logic for display
    $r['display_arch'] = formatArchInfo($r['u_arch'], $r['l_arch']);
}
unset($r); // Break references

// 5. Response
Api::success($records);

/**
 * Helper to format Arch Information consistently
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