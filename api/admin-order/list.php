<?php
/**
 * GET /admin-order/list.php
 * Administrative script to list, paginate, and search all global orders.
 * Supports cross-office aggregation, keyword searches, and date range filters.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication and ensure user is an administrator
$auth->requireAuth();
// If you have a role checking mechanism, uncomment the line below:
// $auth->requireRole('admin'); 

// 1. Standard Pagination Parameters
$limit  = min((int) ($_GET['limit'] ?? 20), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$currentUserId = $_SESSION['user_id'];

// 2. Extract Admin Search Filter Variables from GET Request
$searchVal = isset($_GET['search']) ? trim($_GET['search']) : '';
$officeId  = isset($_GET['office_id']) && $_GET['office_id'] !== 'null' ? trim($_GET['office_id']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// 3. Construct Dynamic WHERE clauses based on provided query structures
$whereClauses = ["o.activation = 'active'"];
$queryParams  = [];

// First bind parameters for the inner SELECT CASE blocks
array_push($queryParams, $currentUserId, $currentUserId);

// Dynamic search keyword check (Checks Order ID code, Creator Name, or Approver Name)
if ($searchVal !== '') {
    $whereClauses[] = "(o.id LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)";
    $searchWildcard = '%' . $searchVal . '%';
    array_push($queryParams, $searchWildcard, $searchWildcard, $searchWildcard);
}

// Cross-Office Filter (Admins can select a specific office, or view all if empty)
if ($officeId !== '') {
    $whereClauses[] = "o.office_id = ?";
    $queryParams[]  = $officeId;
}

// Start Date Filter
if ($startDate !== '') {
    $whereClauses[] = "o.order_date >= ?";
    $queryParams[]  = $startDate;
}

// End Date Filter
if ($endDate !== '') {
    $whereClauses[] = "o.order_date <= ?";
    $queryParams[]  = $endDate;
}

// Compile down standard array to a valid WHERE string chain
$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// 4. Assemble the Primary Dynamic Data Fetch SQL query string
$sql = "SELECT 
    o.*,
    -- Office metadata inclusion
    ofc.office_name as office_name,
    -- Creator Logic Mapping
    CASE WHEN o.created_by = ? THEN 'You' ELSE u1.name END as creator_name,
    -- Approver Logic Mapping
    CASE 
        WHEN o.approved_by IS NULL THEN '-'
        WHEN o.approved_by = ? THEN 'You' 
        ELSE u2.name 
    END as approver_name,
    -- Analytical aggregates per order row
    COUNT(oi.id) as total_items_count,
    COALESCE(SUM(oi.price * oi.quantity), 0) as total_amount
FROM orders o
LEFT JOIN offices ofc ON o.office_id = ofc.id
LEFT JOIN users u1 ON o.created_by = u1.user_id
LEFT JOIN users u2 ON o.approved_by = u2.user_id
LEFT JOIN order_items oi ON o.id = oi.order_id
{$whereSql}
GROUP BY o.id, ofc.office_name, u1.name, u2.name
ORDER BY o.id DESC
LIMIT ? OFFSET ?";


// Append pagination bounds parameters to the list array
$dataParams = array_merge($queryParams, [$limit, $offset]);
$orders = $db->query($sql, $dataParams);

// 5. Query matching record totals for your pagination metadata structure
$countSql = "SELECT COUNT(DISTINCT o.id) as total 
             FROM orders o
             LEFT JOIN users u1 ON o.created_by = u1.user_id
             LEFT JOIN users u2 ON o.approved_by = u2.user_id
             {$whereSql}";

// Strip out the first two currentUserId variables since the count query doesn't use the creator/approver CASE clauses
$countParams = array_slice($queryParams, 2); 
$countRow = $db->queryOne($countSql, $countParams);
$totalCount = (int)($countRow['total'] ?? 0);

// 6. Build response object array using metadata values expected by renderPagination()
$totalPages = ceil($totalCount / $limit);

$response = [
    "data" => $orders,
    "meta" => [
        "total"   => $totalCount,
        "pages"   => $totalPages,
        "current" => $page,
        "limit"   => $limit
    ]
];

// Return JSON via your system helper class
Api::success($response['data'], "Orders loaded successfully.", $response);