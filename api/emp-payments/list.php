<?php
/**
 * API: Fetch Payment Liability List with Calculated Totals & Custom Treatment Notes
 * Path: /emp-payments/list.php
 * Aggregates: Total Paid (Payment) and Total Refunded (Refund) per Master Record.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Ensure user has permission to view billing
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

$sessionOfficeId = (int) ($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}

// ── GET LIVE FILTER PARAMETERS FROM CLIENT SEARCH GRID MATRIX ──
$patientName = isset($_GET['patient_name']) ? trim($_GET['patient_name']) : '';
$paymentId   = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
$startDate   = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate     = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Base query array bindings
$params = [$sessionOfficeId];

// Build dynamic WHERE clause statements cleanly
$whereClause = "WHERE p.office_id = ? ";

if ($patientName !== '') {
    $whereClause .= " AND pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

if ($paymentId !== '') {
    $whereClause .= " AND p.id = ?";
    $params[] = (int) $paymentId;
}

if ($startDate !== '') {
    $whereClause .= " AND p.payment_date >= ?";
    $params[] = $startDate . ' 00:00:00';
}

if ($endDate !== '') {
    $whereClause .= " AND p.payment_date <= ?";
    $params[] = $endDate . ' 23:59:59';
}

// We use LEFT JOIN + GROUP BY to calculate running totals for each payment case.
// Modified to select raw custom literal text from 'p.treatments' directly.
$sql = "SELECT 
            p.id AS payment_id,
            p.patient_id,
            p.provider_id,
            p.total_amount,
            p.treatments,
            p.payment_date,
            p.status,
            pat.name AS patient_name,
            doc.name AS provider_name,
            /* COALESCE ensures 0 instead of NULL if no transactions exist */
            COALESCE(SUM(CASE WHEN pt.payment_type = 'Payment' THEN pt.amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(CASE WHEN pt.payment_type = 'Refund' THEN pt.amount ELSE 0 END), 0) AS total_refunded
        FROM `payments` p
        INNER JOIN `patient` pat ON p.patient_id = pat.id
        LEFT JOIN `users` doc ON p.provider_id = doc.user_id
        LEFT JOIN `payment_transactions` pt ON p.id = pt.payment_id
        $whereClause
        GROUP BY p.id
        ORDER BY p.id DESC";

$records = $db->query($sql, $params);

if (empty($records)) {
    Api::success([], 'Success');
    exit;
}

// Format the data for the frontend grid view mapping layouts
$finalList = [];
foreach ($records as $row) {
    $totalPaid = (float) $row['total_paid'];
    $totalRefunded = (float) $row['total_refunded'];

    // Calculate the net paid amount first
    $netPaid = $totalPaid - $totalRefunded;

    // Calculate balance liability parameters
    $balance = (float) $row['total_amount'] - $netPaid;

    // Handle the visual categorization metadata mapping triggers
    if ($balance < 0) {
        $balanceType = 'Credit'; // The patient has extra money (overpaid)
    } elseif ($balance == 0) {
        $balanceType = 'Settled'; // Paid in full
    } else {
        $balanceType = 'Due';     // Money still owed
    }

    $finalList[] = [
        'id'              => (int) $row['payment_id'],
        'patient_name'    => $row['patient_name'],
        'provider_name'   => $row['provider_name'] ?? 'Unassigned',
        'treatments'      => $row['treatments'] ?? '', // Fallback raw custom text descriptions
        'treatment_names' => $row['treatments'] ?? 'N/A', // Kept for total frontend backwards rendering compatibility
        'total_amount'    => (float) $row['total_amount'],
        'total_paid'      => $netPaid,
        'balance_due'     => $balance,
        'status'          => $row['status'],
        'payment_date'    => $row['payment_date'],
        'balance_type'    => $balanceType
    ];
}

Api::success($finalList, 'Success');