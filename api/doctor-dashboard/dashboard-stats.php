<?php
/**
 * API: Fetch Doctor-Specific Dashboard Metrics
 * Path: /api/doctor/dashboard-stats.php
 * Performance-optimized payload isolating production and pre-auth metrics to the individual doctor,
 * while maintaining shared office scope for budgets and pending operational inventory orders.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce provider access parameter protections
if (!$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access permissions. Doctor role required.', 403);
    exit;
}

// Extract Session Context Controls for both cross-sections
$officeId = $_SESSION['office_id'] ?? null;
$doctorId = $_SESSION['user_id'] ?? null; // Adjusted to match your structural session key for user identification

if (!$officeId || !$doctorId) {
    Api::error('User session context missing office location assignment or doctor identification.', 400);
    exit;
}

// Establish Calendar Month Timestamp Boundaries (First to Last Day)
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');
$currentYearMonth = date('Y-m'); 

$startDateStr = $currentYearMonth . '-01 00:00:00';
$endDateStr   = date('Y-m-t') . ' 23:59:59';

/* =================================================================
   SECTION A: DOCTOR PRE-AUTH PIPELINE METRICS (Office & Doctor Scoped)
================================================================= */
// Filters cases strictly belonging to this doctor at this specific office location
$preAuthSql = "SELECT pa.`status`, pa.`price` 
               FROM `pre-auth` pa
               INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
               WHERE pac.office_id = ? 
                 AND pac.doctor_id = ?
                 AND pa.`created_at` BETWEEN ? AND ?";
$preAuthRows = $db->query($preAuthSql, [$officeId, $doctorId, $startDateStr, $endDateStr]) ?: [];

$preauth_total_value       = 0.0;
$preauth_sent_value        = 0.0;
$preauth_approved_value    = 0.0;
$preauth_scheduled_value   = 0.0;
$preauth_completed_value   = 0.0;

$preauth_total_count       = 0;
$preauth_sent_count        = 0;
$preauth_approved_count    = 0;
$preauth_scheduled_count   = 0;
$preauth_completed_count   = 0;

foreach ($preAuthRows as $row) {
    $price = (float)($row['price'] ?? 0.0);
    $status = strtolower(trim($row['status'] ?? ''));

    // CRITICAL: Total Pre-Auth metrics now represent the total initial stage generated/sent out this month
    $preauth_total_value += $price;
    $preauth_total_count++;

    // Track specific breakdown milestones
    if ($status === 'requested' || $status === 'sent') {
        $preauth_sent_value += $price;
        $preauth_sent_count++;
    } elseif ($status === 'approved') {
        $preauth_approved_value += $price;
        $preauth_approved_count++;
    } elseif ($status === 'scheduled') {
        $preauth_scheduled_value += $price;
        $preauth_scheduled_count++;
    } elseif ($status === 'completed' || $status === 'finalized') {
        $preauth_completed_value += $price;
        $preauth_completed_count++;
    }
}

// Ensure the frontend display mapping variables match up seamlessly
$preauth_sent_value = $preauth_total_value;
$preauth_sent_count = $preauth_total_count;

/* =================================================================
   SECTION B: TOTAL DOCTOR REVENUE (Expected Production vs Cash Collected)
================================================================= */
// Step 1: Calculate Total Treatment Production Value booked by this doctor at this office location
$paymentsSql = "SELECT SUM(`total_amount`) AS `expected_production` 
                FROM `payments` 
                WHERE `office_id` = ? 
                  AND `provider_id` = ?
                  AND `payment_date` BETWEEN ? AND ?";
$paymentResult = $db->queryOne($paymentsSql, [$officeId, $doctorId, $startDateStr, $endDateStr]);
$totalMonthlyCost = (float)($paymentResult['expected_production'] ?? 0.0);

// Step 2: Calculate Actual Cash Received for this specific doctor's ledger
$transSql = "SELECT pt.`payment_type`, pt.`amount` 
             FROM `payment_transactions` pt
             INNER JOIN `payments` p ON pt.`payment_id` = p.`id`
             WHERE p.`office_id` = ? 
               AND p.`provider_id` = ?
               AND pt.`transaction_date` BETWEEN ? AND ?";
$transactionRows = $db->query($transSql, [$officeId, $doctorId, $startDateStr, $endDateStr]) ?: [];

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
$moneyReceived = $totalPaid - $totalRefunded;

/* =================================================================
   SECTION C: LAB LEDGER AGGREGATION (Placeholder matching unified object structure)
================================================================= */
$labs_total_value = 0.00; 
$labs_total_count = 0;

/* =================================================================
   SECTION D: CLINIC OVERVIEW METRICS (Shared Office-Level Parameters)
================================================================= */
// 1. Total Count of Active, Pending Orders for the entire Office Location
$orderSql = "SELECT COUNT(*) as total 
             FROM `orders` 
             WHERE `office_id` = ? 
               AND `activation` = 'active' 
               AND `status` = 'pending'";
$orderRow = $db->queryOne($orderSql, [$officeId]);
$pendingOrdersCount = (int)($orderRow['total'] ?? 0);

// 2. Office Spending Target vs Monthly Budget Remaining Calculations (Office Scoped)
$budgetSql = "SELECT `budget_amount` 
              FROM `monthly_budget` 
              WHERE `office_id` = ? 
                AND `budget_month` = ? 
                AND `budget_year` = ?";
$budgetResult = $db->queryOne($budgetSql, [$officeId, $currentMonth, $currentYear]);
$budgetAmount = (float)($budgetResult['budget_amount'] ?? 0.0);

// Calculate office approved spending totals for inventory targets
$spentSql = "SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total_spent
             FROM `orders` ord
             JOIN `order_items` oi ON ord.id = oi.order_id
             WHERE ord.office_id = ? 
               AND ord.status = 'approved'
               AND ord.activation = 'active'
               AND MONTH(ord.order_date) = ?
               AND YEAR(ord.order_date) = ?";
$spentResult = $db->queryOne($spentSql, [$officeId, $currentMonth, $currentYear]);
$totalSpent = (float)($spentResult['total_spent'] ?? 0.0);

// Calculate remaining variations
$monthlyBudgetRemaining = $budgetAmount - $totalSpent;
$monthlyBudgetPctLeft = 0.0;
if ($budgetAmount > 0) {
    $monthlyBudgetPctLeft = (($budgetAmount - $totalSpent) / $budgetAmount) * 100;
    if ($monthlyBudgetPctLeft < 0) $monthlyBudgetPctLeft = 0.0;
}

/* =================================================================
   SECTION E: SYSTEM RESPONSE DELIVERY
================================================================= */
$response = [
    'total_revenue_made'             => (float)$moneyReceived,    // Maps directly to front-end layout engine expectations
    'total_cost'                     => (float)$totalMonthlyCost, 
    
    'preauth_total_value'            => (float)$preauth_total_value,
    'preauth_total_count'            => (int)$preauth_total_count,
    
    'preauth_sent_value'             => (float)$preauth_sent_value,
    'preauth_sent_count'             => (int)$preauth_sent_count,

    'preauth_approved_value'         => (float)$preauth_approved_value, 
    'preauth_approved_count'         => (int)$preauth_approved_count,   
    
    'preauth_scheduled_value'        => (float)$preauth_scheduled_value,
    'preauth_scheduled_count'        => (int)$preauth_scheduled_count,
    
    'preauth_completed_value'        => (float)$preauth_completed_value,
    'preauth_completed_count'        => (int)$preauth_completed_count,

    'labs_total_value'               => (float)$labs_total_value,
    'labs_total_count'               => (int)$labs_total_count,

    'pending_orders_count'           => (int)$pendingOrdersCount,
    
    'total_budget'                   => (float)$budgetAmount, 
    'spent_budget'                   => (float)$totalSpent, 
    'monthly_budget_remaining'       => (float)$monthlyBudgetRemaining, 
    'monthly_budget_percentage_left' => (float)$monthlyBudgetPctLeft
];

Api::success($response);