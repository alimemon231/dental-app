<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Only Access
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { Api::error('Budget ID is required.'); exit; }

// 2. Fetch the Budget row to get context (Office, Year, Month)
$budget = $db->queryOne(
    "SELECT mb.*, o.office_name 
     FROM monthly_budget mb
     INNER JOIN offices o ON mb.office_id = o.id
     WHERE mb.id = ?", 
    [$id]
);

if (!$budget) { Api::error('Budget record not found.', 404); exit; }

$officeId = $budget['office_id'];
$year     = $budget['budget_year'];
$month    = $budget['budget_month'];

// 3. Fetch all orders for this specific Office, Year, and Month
// We include 'rejected' orders as requested, but they are clearly labeled
$orders = $db->query(
    "SELECT 
        o.id,
        o.order_date,
        o.status,
        u1.name as creator_name,
        COALESCE(u2.name, '-') as approver_name,
        (SELECT SUM(price * quantity) FROM order_items WHERE order_id = o.id) as total_amount
     FROM orders o
     LEFT JOIN users u1 ON o.created_by = u1.user_id
     LEFT JOIN users u2 ON o.approved_by = u2.user_id
     WHERE o.office_id = ? 
       AND YEAR(o.order_date) = ? 
       AND MONTH(o.order_date) = ?
       AND o.activation = 'active'
     ORDER BY o.order_date DESC",
    [$officeId, $year, $month]
);

// 4. Return combined data
Api::success([
    'budget' => $budget,
    'orders' => $orders
]);