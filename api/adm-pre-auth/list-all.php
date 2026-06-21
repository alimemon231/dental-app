<?php
/**
 * GET api/admin/pre-auth/list-all.php
 * Aggregates global pre-auth counts and financial metrics cleanly on the database layer.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Check
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Capture Filter Parameters
$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null; 
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; 

// 3. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}
if (!empty($clinicId)) {
    $where[] = "pac.office_id = ?";
    $params[] = (int)$clinicId;
}
if (!empty($startDate)) {
    $where[] = "DATE(pa.created_at) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "DATE(pa.created_at) <= ?";
    $params[] = $endDate;
}
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

$whereSql = implode(" AND ", $where);

try {
    // 4. SQL Aggregation Matrix Engine
    $sql = "SELECT 
                -- Absolute Total Metrics
                COUNT(pa.id) AS total_sent,
                
                -- Stat Metrics Counts
                SUM(CASE WHEN pa.status IN ('Approved', 'Rejected', 'Denied', 'Scheduled', 'Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_evaluated,
                SUM(CASE WHEN pa.appointment_date IS NOT NULL OR pa.status IN ('Scheduled', 'Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_scheduled,
                SUM(CASE WHEN pa.status IN ('Completed', 'complete', 'done') THEN 1 ELSE 0 END) AS count_completed,
                
                -- Money Card Totals
                SUM(CASE WHEN pa.status IN ('Approved', 'Scheduled') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_approved,
                SUM(CASE WHEN pa.status IN ('Requested', 'Sent', 'Pending') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_pending,
                SUM(CASE WHEN pa.status IN ('Completed', 'complete', 'done') THEN COALESCE(pa.price, 0) ELSE 0 END) AS total_value_completed
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
            LEFT JOIN patient pat ON pac.patient_id = pat.id
            WHERE $whereSql";

    // Query returns a single consolidated tracking structure row
    $metrics = $db->queryOne($sql, $params) ?: [
        'total_sent' => 0, 'count_evaluated' => 0, 'count_scheduled' => 0, 'count_completed' => 0,
        'total_value_approved' => 0, 'total_value_pending' => 0, 'total_value_completed' => 0
    ];

    // 5. Output response package matrix directly
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
    ], 'Global stats computed successfully');

} catch (Exception $e) {
    Api::error('Metrics aggregation failure: ' . $e->getMessage(), 500);
}