<?php
/**
 * API: Fetch Global Dashboard Metrics (Admin Scope)
 * Path: /api/admin/dashboard-stats.php
 * Performance-optimized single payload combining payments ledger cash and pre-auth pipelines.
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

    $preauth_total_count     = 0;
    $preauth_approved_count  = 0;
    $preauth_pending_count   = 0;
    $preauth_completed_count = 0;

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
       SECTION C: LAB INFRASTRUCTURE PLACEHOLDERS
    ================================================================= */
    $labs_total_value = 0.00; 
    $labs_total_count = 0;

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

        // Lab assets integration arrays
        'labs_total_value'          => (float)$labs_total_value,
        'labs_total_count'          => (int)$labs_total_count
    ];

    Api::success($response);