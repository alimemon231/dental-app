<?php
/**
 * GET api/emp-labs/list-scheduled.php
 * Fetch only "Scheduled" lab cases for the logged-in staff's specific clinic office.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Standard Pagination & Session Extraction
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;
$currentUserId = $_SESSION['user_id'];

// 2. Identify the current user's office assignment via mapping table
$office = $db->queryOne("SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1", [$currentUserId]);
$officeId = $office ? $office['office_id'] : 0;

// If the user isn't assigned to an office, return empty to prevent data leaks across clinics
if (!$officeId) {
    Api::success([], 'No office assigned to this user.');
    exit;
}

/**
 * 3. Comprehensive SQL Query
 * Fetches required dataset components by cleanly linking contextual records:
 * - patient (p): Relational patient data extraction
 * - users (u): Provider/Doctor name details
 * - case_type (ct): Treatment case label categories
 * - lab_steps (ls): Planned clinical procedures
 * - labs_patner (lp): Active external vendor supplier label
 */
$sql = "SELECT 
            l.id,
            l.p_id,
            p.name AS patient_name,
            l.u_arch,
            l.l_arch,
            l.impression_type,
            l.status,
            l.date_sent,
            l.date_scheduled,
            u.name AS doctor_name,
            ct.name AS case_type_name,
            ls.name AS next_visit_step,
            lp.name AS lab_partner_name
        FROM labs l
        LEFT JOIN patient p ON l.p_id = p.id
        LEFT JOIN users u ON l.provider = u.user_id
        LEFT JOIN case_type ct ON l.case_type = ct.id
        LEFT JOIN lab_steps ls ON l.next_visit = ls.id
        LEFT JOIN labs_patner lp ON l.lab_provider = lp.id
        WHERE l.office_id = ? 
          AND l.status = 'Scheduled'
        ORDER BY l.date_scheduled ASC
        LIMIT ? OFFSET ?";

$records = $db->query($sql, [$officeId, $limit, $offset]);

// 4. Transform data structurally matching UI parameters
foreach ($records as &$r) {
    // Format dates cleanly for datatable rows
    $r['fmt_scheduled_date'] = $r['date_scheduled'] ? date('M d, Y', strtotime($r['date_scheduled'])) : 'TBD';
    $r['formatted_date']     = $r['date_sent'] ? date('M d, Y', strtotime($r['date_sent'])) : '—';
    
    // Formatting the Tooth/Arch display block logic
    $arch_info = "";
    if ($r['u_arch'] === 'Full' && $r['l_arch'] === 'Full') {
        $arch_info = "Both Arches";
    } elseif ($r['u_arch'] === 'Full') {
        $arch_info = "Upper Arch";
    } elseif ($r['l_arch'] === 'Full') {
        $arch_info = "Lower Arch";
    } else {
        $upper = trim($r['u_arch'] ?? '');
        $lower = trim($r['l_arch'] ?? '');
        $combined = [];
        if (!empty($upper)) $combined[] = "U: $upper";
        if (!empty($lower)) $combined[] = "L: $lower";
        
        $arch_info = !empty($combined) ? implode(' | ', $combined) : "N/A";
    }
    $r['display_arch'] = $arch_info;
}
unset($r); // Secure processing loop reference termination

// 5. Response Pipeline Outflow
Api::success($records);