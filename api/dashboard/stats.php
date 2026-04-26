<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$currentUserId = $_SESSION['user_id'];
$currentYear   = date('Y');
$currentMonth  = (int)date('m');

// 1. Identify User's Office ID
$officeRow = $db->queryOne(
    "SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1",
    [$currentUserId]
);

if (!$officeRow) {
    Api::error("User is not assigned to any office.");
    exit;
}
$officeId = $officeRow['office_id'];

// 2. Find Budget for current month
$budgetRow = $db->queryOne(
    "SELECT budget_amount FROM monthly_budget 
     WHERE office_id = ? AND budget_year = ? AND budget_month = ?",
    [$officeId, $currentYear, $currentMonth]
);
$budget = (float)($budgetRow['budget_amount'] ?? 0);

// 3. Find Expenses (Sum of all active orders this month for this office)
// We join orders with order_items to get the actual monetary value
$expenseRow = $db->queryOne(
    "SELECT SUM(oi.price * oi.quantity) as total_spent
     FROM orders o
     JOIN order_items oi ON o.id = oi.order_id
     WHERE o.office_id = ? and o.status = 'approved'
     AND o.activation = 'active'
     AND YEAR(o.order_date) = ?
     AND MONTH(o.order_date) = ?",
    [$officeId, $currentYear, $currentMonth]
);
$expense = (float)($expenseRow['total_spent'] ?? 0);

$pending_orders_row = $db->queryOne(
    "SELECT COUNT(id) as total
     FROM orders
     WHERE office_id = ? 
     AND status = 'pending'
     AND activation = 'active'",
    [$officeId]
);

$pending_count = (int)($pending_orders_row['total'] ?? 0);

// 4. Logic for Frontend
$percentage = $budget > 0 ? round(($expense / $budget) * 100, 1) : 0;

// Determine status class based on utilization
$statusClass = 'status-green';
if ($percentage >= 80 && $percentage < 100) {
    $statusClass = 'status-yellow';
} elseif ($percentage >= 100) {
    $statusClass = 'status-red';
}

Api::success([
    'budget'       => $budget,
    'expense'      => $expense,
    'percentage'   => $percentage,
    'status_class' => $statusClass,
    'month_name'   => date('F'),
    'pending_order' => $pending_count
]);