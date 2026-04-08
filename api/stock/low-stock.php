<?php
/**
 * GET api/stock/low-stock.php
 * Returns products where quantity <= reorder_level
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$items = $db->query(
    "SELECT p.id, p.name, p.quantity, p.reorder_level,
            c.name AS category
     FROM products p
     LEFT JOIN product_categories c ON c.id = p.category_id
     WHERE p.quantity <= p.reorder_level
       AND p.deleted_at IS NULL
     ORDER BY p.quantity ASC
     LIMIT 10"
);

Api::success($items);
