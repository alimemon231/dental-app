<?php
/**
 * API: Fetch Global Dashboard Metrics (Admin Scope)
 * Path: /api/admin/dashboard-stats.php
 * Performance-optimized single payload combining payments ledger cash, pre-auth pipelines, and lab metrics.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce explicit administrative or clinical/billing management authorization parameters
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

/**
 * Helper function to calculate the multiplier value based on arch text format
 */
function calculateArchMultiplier($archValue) {
    $archValue = trim($archValue ?? '');
    
    // Rule 1: If no data, value is 0
    if ($archValue === '') {
        return 0;
    }
    
    // Rule 2: If value is 'Full', value is 1
    if (strcasecmp($archValue, 'Full') === 0) {
        return 1;
    }
    
    // Rule 3: If it contains comma-separated tooth numbers, count them
    $teethArray = explode(',', $archValue);
    // Filter out any accidental empty strings from split trailing commas
    $cleanTeethArray = array_filter(array_map('trim', $teethArray), function($val) {
        return $val !== '';
    });
    
    return count($cleanTeethArray);
}

// 1. Establish Current Month Timestamp Boundaries (First to Last Day)
$currentYearMonth = date('Y-m'); // Format: "YYYY-MM"
$startDateStr = $currentYearMonth . '-01 00:00:00';
$endDateStr   = date('Y-m-t') . ' 23:59:59';

/* =================================================================
    SECTION A: PRE-AUTH PIPELINE METRICS & COUNTS
================================================================= */
// Fetches all itemized price calculations along with tracking states for the current month
$preAuthSql = "SELECT `status`, `price` 
               FROM `pre-auth` 
               WHERE `created_at` BETWEEN ? AND ?";
$preAuthRows = $db->query($preAuthSql, [$startDateStr, $endDateStr]);

// Initialize tracking buckets matching the UI layout requirements
$preauth_total_value     = 0.0;
$preauth_approved_value  = 0.0;
$preauth_pending_value   = 0.0;
$preauth_completed_value = 0.0;
$preauth_scheduled_value = 0.0; // Added tracking metric container

$preauth_total_count     = 0;
$preauth_approved_count  = 0;
$preauth_pending_count   = 0;
$preauth_completed_count = 0;
$preauth_scheduled_count = 0; // Added volume counter metrics

foreach ($preAuthRows as $row) {
    $price = (float)($row['price'] ?? 0.0);
    $status = strtolower(trim($row['status'] ?? ''));

    $preauth_total_value += $price;
    $preauth_total_count++;

    if ($status === 'approved') {
        $preauth_approved_value += $price;
        $preauth_approved_count++;
    } elseif ($status === 'requested' || $status === 'sent' || $status === 'pending') {
        $preauth_pending_value += $price;
        $preauth_pending_count++;
    } elseif ($status === 'completed' || $status === 'finalized') {
        $preauth_completed_value += $price;
        $preauth_completed_count++;
    } elseif ($status === 'scheduled') { // Isolated mapping match condition
        $preauth_scheduled_value += $price;
        $preauth_scheduled_count++;
    }
}

/* =================================================================
    SECTION B: TOTAL MONTHLY REVENUE (EXPECTED VS RECEIVED)
    1. Master Expected Treatment Value from `payments` (Total Cost)
    2. Transaction Ledger Aggregation from `payment_transactions` (Money Received)
================================================================= */

// Step 1: Calculate Total Treatment Costs Generated this Month
$paymentsSql = "SELECT SUM(`total_amount`) AS `expected_production` 
                FROM `payments` 
                WHERE `payment_date` BETWEEN ? AND ?";
$paymentResult = $db->queryOne($paymentsSql, [$startDateStr, $endDateStr]);
$totalMonthlyCost = (float)($paymentResult['expected_production'] ?? 0.0);


// Step 2: Calculate Actual Cash Received (Payments minus Refunds)
$transSql = "SELECT `payment_type`, `amount` 
             FROM `payment_transactions` 
             WHERE `transaction_date` BETWEEN ? AND ?";
$transactionRows = $db->query($transSql, [$startDateStr, $endDateStr]);

$totalPaid = 0.0;
$totalRefunded = 0.0;

foreach ($transactionRows as $t) {
    $amt = (float)($t['amount'] ?? 0.0);
    $type = trim($t['payment_type'] ?? '');

    if ($type === 'Payment') {
        $totalPaid += $amt;
    } elseif ($type === 'Refund') {
        $totalRefunded += $amt;
    }
}

// Net actual cash captured in this month interval
$moneyReceived = $totalPaid - $totalRefunded;

/* =================================================================
    SECTION C: LAB INFRASTRUCTURE DATA AGGREGATION
================================================================= */
$labs_total_value = 0.00; 
$labs_total_count = 0;

// Fetch all monthly active labs records for calculations mapping
$labsSql = "SELECT `u_arch`, `l_arch`, `price` 
            FROM `labs` 
            WHERE `date_sent` BETWEEN ? AND ?";
$labsRows = $db->query($labsSql, [$startDateStr, $endDateStr]) ?: [];

foreach ($labsRows as $lab) {
    $uArchMultiplier = calculateArchMultiplier($lab['u_arch']);
    $lArchMultiplier = calculateArchMultiplier($lab['l_arch']);
    $itemBasePrice   = floatval($lab['price'] ?? 0.00);

    // Compute (u_arch + l_arch) * price Formula Structure
    $totalRowValue   = ($uArchMultiplier + $lArchMultiplier) * $itemBasePrice;

    $labs_total_value += $totalRowValue;
    $labs_total_count++;
}

/* =================================================================
    SECTION D: RESPONSE COMPILATION
================================================================= */
$response = [
    // Both options cleanly isolated for the frontend
    'total_cost'                => (float)$totalMonthlyCost, // Total production values booked
    'money_received'            => (float)$moneyReceived,    // Actual cash collection in drawer
    
    'raw_gross_payments'        => (float)$totalPaid,
    'raw_gross_refunds'         => (float)$totalRefunded,

    // Pre-auth tracking volume calculations
    'preauth_total_value'       => (float)$preauth_total_value,
    'preauth_total_count'       => (int)$preauth_total_count,
    
    'preauth_approved_value'    => (float)$preauth_approved_value,
    'preauth_approved_count'    => (int)$preauth_approved_count,
    
    'preauth_pending_value'     => (float)$preauth_pending_value,
    'preauth_pending_count'     => (int)$preauth_pending_count,
    
    'preauth_completed_value'   => (float)$preauth_completed_value,
    'preauth_completed_count'   => (int)$preauth_completed_count,

    // Scheduled Pre-auth extensions (Exposed targets)
    'preauth_scheduled_value'   => (float)$preauth_scheduled_value,
    'preauth_scheduled_count'   => (int)$preauth_scheduled_count,

    // Lab assets integration arrays (Populated dynamically)
    'labs_total_value'          => (float)round($labs_total_value, 2),
    'labs_total_count'          => (int)$labs_total_count
];

Api::success($response);