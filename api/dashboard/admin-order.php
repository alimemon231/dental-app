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







// 3. The SQL Query (Filtered by o.office_id)
/**
 * Admin Logic: Fetch ALL approved orders across ALL offices
 */
$sql = "SELECT 
            o.id,
            o.order_date,
            o.expected_received_date,
            o.status,
            off.office_name as office_name,
            u1.name as creator_name,
            u2.name as approver_name,
            COUNT(oi.id) as total_items_count,
            SUM(oi.price * oi.quantity) as total_amount
        FROM orders o
        INNER JOIN offices off ON o.office_id = off.id 
        LEFT JOIN users u1 ON o.created_by = u1.user_id
        LEFT JOIN users u2 ON o.approved_by = u2.user_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.activation = 'active' 
          AND o.status = 'approved'
        GROUP BY o.id
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT ? OFFSET ?";

// No more officeId needed in the parameters
$orders = $db->query($sql, [
    $limit, 
    $offset
]);



// 5. Combined Response for your JS pagination
Api::success($orders);