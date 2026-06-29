<?php
/**
 * API: Fetch Global Dashboard Metrics (Staff Office Scope)
 * Path: /api/staff/dashboard-stats.php
 * Performance-optimized single payload isolating budget and pending order properties exclusively.
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

// Establish Calendar Month Timestamp Boundaries
$currentYear  = (int)date('Y');
$currentMonth = (int)date('m');

try {
    /* =================================================================
        SECTION 1: CLINIC OVERVIEW METRICS (Active Pending Orders)
    ================================================================= */
    // Total Count of Active, Pending Orders for this Office Location
    $orderSql = "SELECT COUNT(*) as total 
                 FROM `orders` 
                 WHERE `office_id` = ? 
                   AND `activation` = 'active' 
                   AND `status` = 'pending'";
    $orderRow = $db->queryOne($orderSql, [$officeId]);
    $pendingOrdersCount = (int)($orderRow['total'] ?? 0);

    /* =================================================================
        SECTION 2: OFFICE SPENDING TARGETS & BUDGET PARAMETERS
    ================================================================= */
    // Office Spending Target Base
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

    // Calculate operational variations remaining safely
    $monthlyBudgetRemaining = $budgetAmount - $totalSpent;
    $monthlyBudgetPctLeft = 0.0;
    
    if ($budgetAmount > 0) {
        $monthlyBudgetPctLeft = (($budgetAmount - $totalSpent) / $budgetAmount) * 100;
        if ($monthlyBudgetPctLeft < 0) {
            $monthlyBudgetPctLeft = 0.0;
        }
    }

    /* =================================================================
        SECTION 3: SYSTEM RESPONSE DELIVERY
    ================================================================= */
    $response = [
        'pending_orders_count'            => (int)$pendingOrdersCount,
        'total_budget'                    => (float)$budgetAmount, 
        'spent_budget'                    => (float)$totalSpent, 
        'monthly_budget_remaining'        => (float)$monthlyBudgetRemaining, 
        'monthly_budget_percentage_left' => (float)$monthlyBudgetPctLeft
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Staff metrics pipeline execution context failure: ' . $e->getMessage(), 500);
}