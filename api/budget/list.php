<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

// 1. Capture Filters
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$month  = !empty($_GET['month']) && $_GET['month'] !== 'null' ? (int)$_GET['month'] : null;
$year   = !empty($_GET['year'])  && $_GET['year']  !== 'null' ? (int)$_GET['year']  : null;
$office = !empty($_GET['office']) && $_GET['office'] !== 'null' ? (int)$_GET['office'] : null;

// 2. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

if ($office) {
    $where[] = "mb.office_id = ?";
    $params[] = $office;
}

if ($year) {
    $where[] = "mb.budget_year = ?";
    $params[] = $year;
}

if ($month) {
    $where[] = "mb.budget_month = ?";
    $params[] = $month;
}

// 3. DEFAULT LOGIC: If no office, no year, and no month are provided, show Current Month/Year
if (!$office && !$year && !$month) {
    $where = ["mb.budget_month = ?", "mb.budget_year = ?"];
    $params = [(int)date('m'), (int)date('Y')];
}

$whereSql = implode(" AND ", $where);

// 4. Main Query
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
        WHERE $whereSql
        ORDER BY mb.budget_year DESC, mb.budget_month DESC, o.office_name ASC
        LIMIT ? OFFSET ?";

// Add pagination params to the end of the array
$queryParams = array_merge($params, [$limit, $offset]);
$budgets = $db->query($sql, $queryParams);

// 5. Process logic for frontend
foreach ($budgets as &$b) {
    $b['budget_amount'] = (float)$b['budget_amount'];
    $b['total_spent']   = (float)$b['total_spent'];
    
    $perc = $b['budget_amount'] > 0 ? ($b['total_spent'] / $b['budget_amount']) * 100 : 0;
    $b['percentage'] = round($perc, 1);
    
    // Status color logic
    if ($b['percentage'] >= 100) {
        $b['status_class'] = 'status-red';
    } elseif ($b['percentage'] >= 80) {
        $b['status_class'] = 'status-yellow';
    } else {
        $b['status_class'] = 'status-green';
    }

    // Format month name for display
    $b['month_name'] = date("F", mktime(0, 0, 0, $b['budget_month'], 10));
}

Api::success($budgets);