<?php
/**
 * GET api/lab-cases/list.php
 * List lab cases for the current user's office with status 'Sent'.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Standard Pagination & Session
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;
$currentUserId = $_SESSION['user_id'];

/**
 * 2. Identify the current user's office
 */
$office = $db->queryOne("SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1", [$currentUserId]);
$officeId = $office ? $office['office_id'] : 0;

/**
 * 3. The SQL Query
 * Joins:
 * - patients (p): Dynamic Patient Record Data Extraction
 * - users (u): Provider/Doctor name
 * - case_types (ct): Case category
 * - lab_steps (ls): Next visit procedure name from lab_steps
 * - labs_patner (lp): Lab partner name from labs_patner
 * Filter: office_id AND status = 'Sent'
 */
$sql = "SELECT 
    l.id,
    l.p_id,
    p.name AS patient_name,            -- Dynamic data pulling from patient mapping table
    l.u_arch,
    l.l_arch,
    l.impression_type,
    l.status,
    l.date_sent,
    u.name AS doctor_name,
    ct.name AS case_type_name,
    ls.name AS next_visit_step,    -- Now from lab_steps
    lp.name AS lab_partner_name    -- Now from labs_patner
FROM labs l
LEFT JOIN patient p ON l.p_id = p.id
LEFT JOIN users u ON l.provider = u.user_id
LEFT JOIN case_type ct ON l.case_type = ct.id
LEFT JOIN lab_steps ls ON l.next_visit = ls.id        -- Linked to lab_steps
LEFT JOIN labs_patner lp ON l.lab_provider = lp.id    -- Linked to labs_patner
WHERE l.office_id = ? 
  AND l.status = 'Sent'
ORDER BY l.id DESC
LIMIT ? OFFSET ?";

$records = $db->query($sql, [$officeId, $limit, $offset]);

// 4. Transform data for the UI
foreach ($records as &$r) {
    // Format date specifically (since we are not using time ago)
    $r['formatted_date'] = $r['date_sent'] ? date('M d, Y', strtotime($r['date_sent'])) : '—';
    
    // Formatting the Tooth/Arch display logic
    $arch_info = "";
    if ($r['u_arch'] === 'Full' && $r['l_arch'] === 'Full') {
        $arch_info = "Both Arches";
    } elseif ($r['u_arch'] === 'Full') {
        $arch_info = "Upper Arch";
    } elseif ($r['l_arch'] === 'Full') {
        $arch_info = "Lower Arch";
    } else {
        // Handle tooth numbers array/string
        $upper = trim($r['u_arch']);
        $lower = trim($r['l_arch']);
        $combined = [];
        if(!empty($upper)) $combined[] = "U: $upper";
        if(!empty($lower)) $combined[] = "L: $lower";
        
        $arch_info = !empty($combined) ? implode(' | ', $combined) : "N/A";
    }
    $r['display_arch'] = $arch_info;
}

// 5. Response
Api::success($records);