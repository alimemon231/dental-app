<?php
/**
 * GET api/emp-labs/list-scheduled.php
 * Fetch only "Scheduled" lab cases for the logged-in staff's specific clinic office with dynamic filtering capabilities.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Capture incoming filter criteria and pagination variables matching the JS parameters exactly
$limit       = 500;
$page        = 1;
$offset      = ($page - 1) * $limit;
$currentUserId = $_SESSION['user_id'];
$officeId    = (int)($_SESSION['office_id'] ?? 0);

// If the user isn't assigned to an office, return error layout envelope cleanly to prevent cross-clinic data leaks
if ($officeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}

$patientName  = trim($_GET['patient_name'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$caseIdFilter = trim($_GET['case_id'] ?? '');

// 2. Build Dynamic WHERE Conditions based on base constraints & parameters
$whereClauses = ["l.office_id = ?"];
$whereParams  = [$officeId];

// Base status constraint check: Must belong strictly to 'Scheduled' pipeline board configuration
if (!empty($statusFilter)) {
    // If user explicitly requests a status that isn't 'Scheduled' on this specific panel, return clean empty set
    if ($statusFilter === 'Scheduled') {
        $whereClauses[] = "l.status = 'Scheduled'";
    } else {
        Api::success([
            'records'       => [],
            'total_records' => 0,
            'total_pages'   => 0,
            'current_page'  => $page
        ], 'Success');
        exit;
    }
} else {
    $whereClauses[] = "l.status = 'Scheduled'";
}

// Search Filter mapping the related patient name string match column
if (!empty($patientName)) {
    $whereClauses[] = "p.name LIKE ?";
    $whereParams[]  = '%' . $patientName . '%';
}

// If specific Lab Case ID No. is filtered (Checks explicit matching integers)
if (!empty($caseIdFilter) && is_numeric($caseIdFilter)) {
    $whereClauses[] = "l.id = ?";
    $whereParams[]  = (int)$caseIdFilter;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

try {
    // 3. Count matching structural records cleanly to pass pagination envelopes down
    $countSql = "SELECT COUNT(*) AS total 
                 FROM labs l 
                 LEFT JOIN patient p ON l.p_id = p.id 
                 $whereSql";
                 
    $totalCountResult = $db->queryOne($countSql, $whereParams);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    /**
     * 4. The Main SQL Query
     * Joins Patient (p), Users (u), Case Type (ct), Lab Steps (ls), and Labs Partner (lp)
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
            $whereSql
            ORDER BY l.date_scheduled ASC, l.id DESC
            LIMIT ? OFFSET ?";

    // Append limits securely onto dynamic statement execution parameters matrices
    $queryParams = array_merge($whereParams, [$limit, $offset]);
    $records = $db->query($sql, $queryParams) ?: [];

    // 5. Transform and format data records for the UI
    foreach ($records as &$r) {
        // Format dates cleanly for datatable rows
        $r['fmt_scheduled_date'] = $r['date_scheduled'] ? date('M d, Y', strtotime($r['date_scheduled'])) : 'TBD';
        $r['formatted_date']     = $r['date_sent'] ? date('M d, Y', strtotime($r['date_sent'])) : '—';
        
        // Formatting the Tooth/Arch layout visualization logic
        $arch_info = "";
        if ($r['u_arch'] === 'Full' && $r['l_arch'] === 'Full') {
            $arch_info = "Both Arches";
        } elseif ($r['u_arch'] === 'Full') {
            $arch_info = "Upper Arch";
        } elseif ($r['l_arch'] === 'Full') {
            $arch_info = "Lower Arch";
        } else {
            // Handle specific itemized tooth numbers array strings
            $upper = trim($r['u_arch'] ?? '');
            $lower = trim($r['l_arch'] ?? '');
            $combined = [];
            if (!empty($upper)) $combined[] = "U: $upper";
            if (!empty($lower)) $combined[] = "L: $lower";
            
            $arch_info = !empty($combined) ? implode(' | ', $combined) : "N/A";
        }
        $r['display_arch'] = $arch_info;
    }
    unset($r); // Secure processing loop reference termination safe contextually

    // 6. Return response package wrapped into page index mapping properties matching JS frontend layers
    Api::success([
        'records'       => $records,
        'total_records' => (int)$totalCount,
        'total_pages'   => (int)$totalPages,
        'current_page'  => $page
    ], 'Success');

} catch (Exception $e) {
    Api::error('Scheduled lab list pipeline filtering failure: ' . $e->getMessage(), 500);
}