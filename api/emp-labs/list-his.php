<?php
/**
 * GET api/emp-labs/list-his.php
 * Staff Monitoring for Lab Cases with office-restricted fallback scoping.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Employee Security Check
$currentUser = $auth->user();
if ($currentUser['role'] !== 'doctor' && $currentUser['role'] !== 'staff') {
    Api::error('Unauthorized access. Staff privileges required.', 403);
    exit;
}

// 2. Capture Filters & Pagination
$limit  = min((int) ($_GET['limit'] ?? 15), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$patientName = trim($_GET['patient'] ?? '');
$clinicId    = $_GET['office_id'] ?? null; // Passed explicit selection from filter
$providerId  = $_GET['provider_id'] ?? null;
$caseTypeId  = $_GET['case_type'] ?? null;
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$statuses    = $_GET['statuses'] ?? null; 

// 3. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

// Search Filter mapping the related patient name column
if (!empty($patientName)) {
    $where[] = "p.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

// Clinic Scoping Fallback Scenarios
if (!empty($clinicId)) {
    // If someone passes a specific clinic in the filter search, use that
    $where[] = "l.office_id = ?";
    $params[] = (int)$clinicId;
} else {
    // By default, capture the office id directly from the logged-in session profile matrix
    $where[] = "l.office_id = ?";
    $params[] = (int)($_SESSION['office_id'] ?? 0);
}

if (!empty($providerId)) {
    $where[] = "l.provider = ?";
    $params[] = (int)$providerId;
}

if (!empty($caseTypeId)) {
    $where[] = "l.case_type = ?";
    $params[] = (int)$caseTypeId;
}

// Date Range (Based on date_sent)
if (!empty($startDate)) {
    $where[] = "DATE(l.date_sent) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "DATE(l.date_sent) <= ?";
    $params[] = $endDate;
}

// Multiple Statuses Filter 
if (!empty($statuses)) {
    $statusArray = explode(',', $statuses);
    $placeholders = implode(',', array_fill(0, count($statusArray), '?'));
    $where[] = "l.status IN ($placeholders)";
    foreach ($statusArray as $s) {
        $params[] = $s;
    }
}

$whereSql = implode(" AND ", $where);

// 4. Total Count for Pagination (Executed early before appending pagination parameters)
$countSql = "SELECT COUNT(*) as total 
             FROM labs l 
             LEFT JOIN patient p ON l.p_id = p.id 
             WHERE $whereSql";
$totalCount = $db->queryOne($countSql, $params)['total'];
$totalPages = ceil($totalCount / $limit);

/**
 * 5. Main Query (Joins Patient, Offices, Users, and Case Types)
 */
$sql = "SELECT 
            l.*, 
            p.name AS patient_name,
            o.office_name, 
            u.name AS doctor_name,
            ct.name AS type_name
        FROM labs l
        LEFT JOIN patient p ON l.p_id = p.id
        LEFT JOIN offices o ON l.office_id = o.id
        LEFT JOIN users u ON l.provider = u.user_id
        LEFT JOIN case_type ct ON l.case_type = ct.id
        WHERE $whereSql
        ORDER BY l.id DESC
        LIMIT ? OFFSET ?";

// Pagination Params merged safely into the array parameters
$queryData = array_merge($params, [$limit, $offset]);
$records = $db->query($sql, $queryData);

/**
 * 6. Format Dates for JS Table
 */
foreach ($records as &$r) {
    // Stage 1: Sent Date
    $r['date_sent_fmt'] = date('m/d/Y', strtotime($r['date_sent']));
    
    // Stage 2: Received Date
    $r['date_received_fmt'] = !empty($r['date_received']) 
        ? date('m/d/Y', strtotime($r['date_received'])) 
        : null;

    // Stage 3: Scheduled/Appointment Date
    $r['date_scheduled_fmt'] = !empty($r['date_scheduled']) 
        ? date('m/d/Y g:i A', strtotime($r['date_scheduled'])) 
        : null;

    // Stage 4: Completed Date
    $r['date_completed_fmt'] = !empty($r['date_complete']) 
        ? date('m/d/Y', strtotime($r['date_complete'])) 
        : null;
}
unset($r); // Clear reference pointer

// 7. Standard JSON Response
Api::success([
    'records'       => $records,
    'total_records' => (int)$totalCount,
    'total_pages'   => (int)$totalPages,
    'current_page'  => $page
], 'Lab cases pipeline data fetched successfully');