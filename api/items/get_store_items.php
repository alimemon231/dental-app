<?php
/**
 * GET api/items/get_store_items.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Capture Filters & Pagination
$limit  = min((int) ($_GET['limit'] ?? 12), 100); 
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$search      = trim($_GET['search'] ?? '');
$categoryIds = $_GET['category_id'] ?? 'all'; // Can be 'all', a string, or an array

// 2. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

// Filter by Search Name
if (!empty($search)) {
    $where[] = "i.name LIKE ?";
    $params[] = '%' . $search . '%';
}

// Filter by Multiple Categories
if (!empty($categoryIds) && $categoryIds !== 'all') {
    if (is_array($categoryIds)) {
        // Create placeholders like (?, ?, ?) for the array
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $where[] = "i.id IN (SELECT item_id FROM item_categories WHERE category_id IN ($placeholders))";
        foreach ($categoryIds as $id) {
            $params[] = (int)$id;
        }
    } else {
        $where[] = "i.id IN (SELECT item_id FROM item_categories WHERE category_id = ?)";
        $params[] = (int)$categoryIds;
    }
}

$whereSql = implode(" AND ", $where);

// 3. Main Query
$sql = "SELECT 
            i.id, 
            i.name, 
            i.price, 
            i.description, 
            i.image_path,
            GROUP_CONCAT(c.name SEPARATOR ', ') AS category_names
        FROM items i
        LEFT JOIN item_categories ic ON i.id = ic.item_id
        LEFT JOIN categories c ON ic.category_id = c.id
        WHERE $whereSql
        GROUP BY i.id
        ORDER BY i.id DESC";

// Pagination parameters always come last

$items = $db->query($sql, $params);

// 4. Total Count for Pagination[cite: 4]
$countSql = "SELECT COUNT(DISTINCT i.id) as total FROM items i 
             LEFT JOIN item_categories ic ON i.id = ic.item_id 
             WHERE $whereSql";
             
$totalCount = $db->queryOne($countSql, $params)['total'];
$totalPages = ceil($totalCount / $limit);

Api::success([
    'items'         => $items,
    'total_records' => (int)$totalCount,
    'total_pages'   => (int)$totalPages
], 'Store items fetched successfully');