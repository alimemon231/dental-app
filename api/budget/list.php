<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = !empty($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// This query fetches the budget AND calculates the sum of approved orders for that specific office/month
$sql = "SELECT 
            mb.id,
            mb.office_id,
            mb.budget_amount,
            mb.budget_month,
            mb.budget_year,
            o.office_name,
            (
                SELECT COALESCE(SUM(oi.price * oi.quantity), 0)
                FROM orders ord
                JOIN order_items oi ON ord.id = oi.order_id
                WHERE ord.office_id = mb.office_id 
                  AND ord.status = 'approved'
                  AND ord.activation = 'active'
                  AND MONTH(ord.order_date) = mb.budget_month
                  AND YEAR(ord.order_date) = mb.budget_year
            ) as total_spent
        FROM monthly_budget mb
        INNER JOIN offices o ON mb.office_id = o.id
        WHERE mb.budget_month = ? AND mb.budget_year = ?
        ORDER BY o.office_name ASC
        LIMIT ? OFFSET ?";

$budgets = $db->query($sql, [$month, $year, $limit, $offset]);

// Process logic for frontend (percentage and colors)
foreach ($budgets as &$b) {
    $b['budget_amount'] = (float)$b['budget_amount'];
    $b['total_spent']   = (float)$b['total_spent'];
    
    $perc = $b['budget_amount'] > 0 ? ($b['total_spent'] / $b['budget_amount']) * 100 : 0;
    $b['percentage'] = round($perc, 1);
    
    if ($b['percentage'] >= 100) {
        $b['status_class'] = 'status-red';
    } elseif ($b['percentage'] >= 80) {
        $b['status_class'] = 'status-yellow';
    } else {
        $b['status_class'] = 'status-green';
    }
}

Api::success($budgets);