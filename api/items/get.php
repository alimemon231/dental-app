<?php
/**
 * GET api/patients/get.php?id=5
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    Api::error('Item ID is required.');
    exit;
}

$patient = $db->queryOne(
    "SELECT 
    i.*, 
    GROUP_CONCAT(ic.category_id) AS category_ids,
    GROUP_CONCAT(COALESCE(c.name, 'Deleted Category') SEPARATOR ', ') AS category_names
FROM items i
LEFT JOIN item_categories ic ON i.id = ic.item_id
LEFT JOIN categories c ON ic.category_id = c.id
WHERE i.id = ?
GROUP BY i.id",
    [$id]
);

if (!$patient) {
    Api::error('Item not found.', 404);
    exit;
}
Api::success($patient);
