<?php
/**
 * API: Fetch Global Dashboard Metrics (Staff Office Scope)
 * Path: /api/staff/dashboard-stats.php
 * Performance-optimized single payload combining payments, pre-auth pipelines,
 * pending orders, budgets, and patient counts scoped strictly to the user's office.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce explicit administrative, staff, or provider access parameter protections
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

// Extract Session Context Controls
$officeId = $_SESSION['office_id'] ?? null;
if (!$officeId) {
    Api::error('User session context missing office location assignment.', 400);
    exit;
}

// Establish Calendar Month Timestamp Boundaries (First to Last Day)
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');
$currentYearMonth = date('Y-m'); 

$startDateStr = $currentYearMonth . '-01 00:00:00';
$endDateStr   = date('Y-m-t') . ' 23:59:59';

/* =================================================================
   SECTION A: PRE-AUTH PIPELINE METRICS & COUNTS (Scoped via Cases)
================================================================= */
// Connects `pre-auth` entries to `pre_auth_cases` to isolate rows by office_id
$preAuthSql = "SELECT pa.`status`, pa.`price` 
               FROM `pre-auth` pa
               INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
               WHERE pac.office_id = ? 
                 AND pa.`created_at` BETWEEN ? AND ?";
$preAuthRows = $db->query($preAuthSql, [$officeId, $startDateStr, $endDateStr]) ?: [];

// Initialize metrics buckets matching frontend parameters
$preauth_total_value       = 0.0;
$preauth_sent_value        = 0.0;
$preauth_scheduled_value   = 0.0;
$preauth_completed_value   = 0.0;

$preauth_total_count       = 0;
$preauth_sent_count        = 0;
$preauth_scheduled_count   = 0;
$preauth_completed_count   = 0;

foreach ($preAuthRows as $row) {
    $price = (float)($row['price'] ?? 0.0);
    $status = strtolower(trim($row['status'] ?? ''));

    $preauth_total_value += $price;
    $preauth_total_count++;

    if ($status === 'requested' || $status === 'sent') {
        $preauth_sent_value += $price;
        $preauth_sent_count++;
    } elseif ($status === 'scheduled') {
        $preauth_scheduled_value += $price;
        $preauth_scheduled_count++;
    } elseif ($status === 'completed' || $status === 'finalized') {
        $preauth_completed_value += $price;
        $preauth_completed_count++;
    }
}

/* =================================================================
   SECTION B: TOTAL MONTHLY REVENUE (Expected Production vs Cash Collected)
================================================================= */
// Step 1: Calculate Total Treatment Production Value booked at this office location
$paymentsSql = "SELECT SUM(`total_amount`) AS `expected_production` 
                FROM `payments` 
                WHERE `office_id` = ? 
                  AND `payment_date` BETWEEN ? AND ?";
$paymentResult = $db->queryOne($paymentsSql, [$officeId, $startDateStr, $endDateStr]);
$totalMonthlyCost = (float)($paymentResult['expected_production'] ?? 0.0);

// Step 2: Calculate Actual Cash Received by JOINing transactions to parent payments for office filtering
$transSql = "SELECT pt.`payment_type`, pt.`amount` 
             FROM `payment_transactions` pt
             INNER JOIN `payments` p ON pt.`payment_id` = p.`id`
             WHERE p.`office_id` = ? 
               AND pt.`transaction_date` BETWEEN ? AND ?";
$transactionRows = $db->query($transSql, [$officeId, $startDateStr, $endDateStr]) ?: [];

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
   SECTION C: LAB LEDGER AGGREGATION
================================================================= */
$labs_total_value = 0.00; 
$labs_total_count = 0;

/* =================================================================
   SECTION D: EXTRA STRATEGIC OPERATIONAL METRICS (Orders, Budgets, Patients)
================================================================= */
// 1. Total Count of Active, Pending Orders for this Office Location
$orderSql = "SELECT COUNT(*) as total 
             FROM `orders` 
             WHERE `office_id` = ? 
               AND `activation` = 'active' 
               AND `status` = 'pending'";
$orderRow = $db->queryOne($orderSql, [$officeId]);
$pendingOrdersCount = (int)($orderRow['total'] ?? 0);

// 2. Total Registered Patients in the current clinic directory scope
$patientSql = "SELECT COUNT(*) as total FROM `patient` WHERE `office_id` = ?";
$patientRow = $db->queryOne($patientSql, [$officeId]);
$totalPatientsCount = (int)($patientRow['total'] ?? 0);

// 3. Office Spending Target vs Monthly Budget Remaining Calculations
$budgetSql = "SELECT `budget_amount` 
              FROM `monthly_budget` 
              WHERE `office_id` = ? 
                AND `budget_month` = ? 
                AND `budget_year` = ?";
$budgetResult = $db->queryOne($budgetSql, [$officeId, $currentMonth, $currentYear]);
$budgetAmount = (float)($budgetResult['budget_amount'] ?? 0.0);

// Calculate actual total approved spending items for the target budget interval
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

// Calculate operational variations remaining
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
    'total_cost'                    => (float)$totalMonthlyCost, 
    'money_received'                => (float)$moneyReceived,    
    
    'preauth_total_value'           => (float)$preauth_total_value,
    'preauth_total_count'           => (int)$preauth_total_count,
    
    'preauth_sent_value'            => (float)$preauth_sent_value,
    'preauth_sent_count'            => (int)$preauth_sent_count,
    
    'preauth_scheduled_value'       => (float)$preauth_scheduled_value,
    'preauth_scheduled_count'       => (int)$preauth_scheduled_count,
    
    'preauth_completed_value'       => (float)$preauth_completed_value,
    'preauth_completed_count'       => (int)$preauth_completed_count,

    'labs_total_value'              => (float)$labs_total_value,
    'labs_total_count'              => (int)$labs_total_count,

    // Custom operational parameters injected for staff panels
    'pending_orders_count'          => (int)$pendingOrdersCount,
    'total_patients_count'          => (int)$totalPatientsCount,
    
    // Cleanly isolated budget properties
    'total_budget'                  => (float)$budgetAmount,           // Total Target Budget Assigned
    'spent_budget'                  => (float)$totalSpent,             // Approved Amount Consumed
    'monthly_budget_remaining'      => (float)$monthlyBudgetRemaining, // Pending/Remaining Budget Balance
    'monthly_budget_percentage_left'=> (float)$monthlyBudgetPctLeft
];

Api::success($response);