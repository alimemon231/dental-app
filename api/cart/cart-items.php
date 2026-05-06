<?php
/**
 * GET api/cart/cart-items.php
 * Fetch all items in the current user's cart with full product details.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check (Ensuring only valid roles access the cart)
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

$currentUserId = $_SESSION['user_id'];

/**
 * 2. The Main Query
 * Joins the cart table with items and categories to provide a detailed view.
 */
$sql = "SELECT 
            c.id,              -- The unique ID of the cart record
            c.item_id,         -- The actual item ID
            c.quantity, 
            i.name, 
            i.price, 
            i.image_path,
            GROUP_CONCAT(cat.name SEPARATOR ', ') AS category_names
        FROM cart c
        JOIN items i ON c.item_id = i.id
        LEFT JOIN item_categories ic ON i.id = ic.item_id
        LEFT JOIN categories cat ON ic.category_id = cat.id
        WHERE c.user_id = ?
        GROUP BY c.id
        ORDER BY c.id DESC";

$items = $db->query($sql, [$currentUserId]);

/**
 * 3. Response logic
 * Following your list pattern to return the success data.
 */
if (empty($items)) {
    Api::success([], 'Your cart is currently empty.');
} else {
    Api::success($items, 'Cart items fetched successfully.');
}