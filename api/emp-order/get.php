<?php
/**
 * GET api/order/get.php?id=XX
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validation
$id = (int)($_GET['id'] ?? 0);
if (!$id) { Api::error('Order ID is required.'); exit; }

$currentUserId = $_SESSION['user_id'];

// 2. Fetch Order Header Details
// We use queryOne because we only expect one result
$order = $db->queryOne("
    SELECT 
        o.*,
        CASE WHEN o.created_by = ? THEN 'You' ELSE u1.name END as creator_name,
        CASE 
            WHEN o.approved_by IS NULL THEN '-'
            WHEN o.approved_by = ? THEN 'You' 
            ELSE u2.name 
        END as approver_name
    FROM orders o
    LEFT JOIN users u1 ON o.created_by = u1.user_id
    LEFT JOIN users u2 ON o.approved_by = u2.user_id
    WHERE o.id = ? AND o.activation = 'active'
", [$currentUserId, $currentUserId, $id]);

if (!$order) { 
    Api::error('Order not found.', 404); 
    exit; 
}

// 3. Fetch Items List for this specific Order
// We join with the items/products table to get the item names
$items = $db->query("
    SELECT 
        oi.item_id as id,
        oi.price,
        oi.quantity as qty,
        (oi.price * oi.quantity) as subtotal,
        i.name as name
    FROM order_items oi
    LEFT JOIN items i ON oi.item_id = i.id
    WHERE oi.order_id = ?
", [$id]);

// 4. Calculate Total Amount 
// (You can also do this in JS, but doing it here is cleaner)
$totalAmount = 0;
foreach ($items as $item) {
    $totalAmount += $item['subtotal'];
}

// 5. Build the Final Object
$order['items'] = $items;
$order['total_amount'] = $totalAmount;
$order['total_items_count'] = count($items);

// 6. Return the unified object
Api::success($order);