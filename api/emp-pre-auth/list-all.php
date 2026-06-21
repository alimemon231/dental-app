<?php
/**
 * GET api/emp-pre-auth/list-all.php
 * Un-paginated global statistics endpoint for staff dashboards.
 * Aggregates itemized metrics and total pricing values directly via high-speed database querying.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Capture authenticated user context & clinic scope
$currentUser = $auth->user();
$currentUserId = $_SESSION['user_id'] ?? $currentUser['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? ($currentUser['office_id'] ?? 0));

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}

// 2. Capture Filter Parameters (Pagination omitted intentionally)
$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null;
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; 

// 3. Build Dynamic WHERE Clause matching Staff Scope constraints
$where = [];
$params = [];

// Enforce clinic location scope restrictions strictly
if (!empty($clinicId)) {
    $where[] = "pac.office_id = ?";
    $params[] = (int)$clinicId;
} else {
    $where[] = "pac.office_id = ?";
    $params[] = $sessionOfficeId;
}

// Patient Name Filter
if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

// Start Date Filter
if (!empty($startDate)) {
    $where[] = "DATE(pa.created_at) >= ?";
    $params[] = $startDate;
}

// End Date Filter
if (!empty($endDate)) {
    $where[] = "DATE(pa.created_at) <= ?";
    $params[] = $endDate;
}

// Handle Multiple Statuses array conversion matrix
if (!empty($status)) {
    if (is_array($status)) {
        $placeholders = implode(',', array_fill(0, count($status), '?'));
        $where[] = "pa.status IN ($placeholders)";
        foreach ($status as $s) {
            $params[] = $s;
        }
    } else {
        $where[] = "pa.status = ?";
        $params[] = $status;
    }
}

// Bind active filter strings
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    // 4. Run High-Speed Aggregation Analytics
    $sql = "SELECT 
                -- Absolute Total Cases Sent Baseline
                COUNT(pa.id) AS total_sent,
                
                -- Progress Tracking Stat Counts
                SUM(CASE WHEN pa.status IN ('Approved', 'Rejected', 'Denied', 'Scheduled', 'Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_evaluated,
                SUM(CASE WHEN pa.appointment_date IS NOT NULL OR pa.status IN ('Scheduled', 'Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_scheduled,
                SUM(CASE WHEN pa.status IN ('Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_completed,
                
                -- Financial Value Cards Aggregation
                SUM(CASE WHEN pa.status IN ('Approved', 'Scheduled') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_approved,
                SUM(CASE WHEN pa.status IN ('Requested', 'Sent', 'Pending') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_pending,
                SUM(CASE WHEN pa.status IN ('Completed', 'complete', 'done') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_completed
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
            LEFT JOIN patient pat ON pac.patient_id = pat.id
            $whereSql";

    // Grab single structural aggregate row
    $metrics = $db->queryOne($sql, $params) ?: [
        'total_sent' => 0, 'count_evaluated' => 0, 'count_scheduled' => 0, 'count_completed' => 0,
        'total_value_approved' => 0, 'total_value_pending' => 0, 'total_value_completed' => 0
    ];

    // 5. Package output object payload mapping types cleanly
    Api::success([
        'metrics' => [
            'total_sent'             => (int)$metrics['total_sent'],
            'count_evaluated'        => (int)$metrics['count_evaluated'],
            'count_scheduled'        => (int)$metrics['count_scheduled'],
            'count_completed'        => (int)$metrics['count_completed'],
            'total_value_approved'   => (float)$metrics['total_value_approved'],
            'total_value_pending'    => (float)$metrics['total_value_pending'],
            'total_value_completed'  => (float)$metrics['total_value_completed']
        ]
    ], 'Staff dashboard statistics aggregated successfully');

} catch (Exception $e) {
    Api::error('Staff metrics calculation failure: ' . $e->getMessage(), 500);
}