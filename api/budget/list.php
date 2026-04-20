<?php
/**
 * GET api/patients/list.php
 * Query params: limit, page, year, month
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();


$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$month = !empty(trim($_GET['month'] ?? '')) ? trim($_GET['month']) : date('m');
$year  = !empty(trim($_GET['year'] ?? ''))  ? trim($_GET['year'])  : date('Y');


$budgets = $db->query(
    "SELECT 
        mb.*, 
        o.office_name AS office_name 
     FROM monthly_budget mb
     INNER JOIN offices o ON mb.office_id = o.id
     WHERE mb.budget_month = ? AND mb.budget_year = ?
     LIMIT ? OFFSET ?",
    [$month, $year, $limit, $offset]
);

Api::success($budgets);