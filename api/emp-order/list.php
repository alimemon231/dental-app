<?php
/**
 * GET api/order/list.php
 * List only orders belonging to the user's office.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Standard Pagination
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$currentUserId = $_SESSION['user_id'];

// 2. Identify the User's Office ID
$officeRow = $db->queryOne(
    "SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1",
    [$currentUserId]
);

if (!$officeRow) {
    Api::success([], 'No office assigned to user.'); // Return empty list if no office
    exit;
}
$officeId = $officeRow['office_id'];

// 3. The SQL Query (Filtered by o.office_id)
$sql = "SELECT 
    o.id,
    o.order_date,
    o.expected_received_date,
    o.status,
    o.activation,
    -- Creator Logic
    CASE WHEN o.created_by = ? THEN 'You' ELSE u1.name END as creator_name,
    -- Approver Logic
    CASE 
        WHEN o.approved_by IS NULL THEN '-'
        WHEN o.approved_by = ? THEN 'You' 
        ELSE u2.name 
    END as approver_name,
    -- Aggregates
    COUNT(oi.id) as total_items_count,
    SUM(oi.price * oi.quantity) as total_amount
FROM orders o
LEFT JOIN users u1 ON o.created_by = u1.user_id
LEFT JOIN users u2 ON o.approved_by = u2.user_id
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE o.activation = 'active' 
  AND o.office_id = ? 
  AND YEAR(o.order_date) = YEAR(CURRENT_DATE)
  AND MONTH(o.order_date) = MONTH(CURRENT_DATE)
GROUP BY o.id
ORDER BY o.id DESC
LIMIT ? OFFSET ?";

$orders = $db->query($sql, [
    $currentUserId, 
    $currentUserId, 
    $officeId, // Added officeId to parameters
    $limit, 
    $offset
]);

// 4. Fetch total count for pagination metadata (Filtered by office)
$countRow = $db->queryOne(
    "SELECT COUNT(*) as total FROM orders WHERE activation = 'active' AND office_id = ?",
    [$officeId]
);
$totalCount = (int)($countRow['total'] ?? 0);

// 5. Combined Response for your JS pagination
Api::success($orders);