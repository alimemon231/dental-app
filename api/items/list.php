<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$limit = 20; 
$page  = max((int) ($_GET['page'] ?? 1), 1);
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

$records = [];
$totalCount = 0;

if ($search) {
    $like = '%' . $search . '%';
    // 1. Get total for search
    $totalCount = $db->queryOne("SELECT COUNT(*) as total FROM patients WHERE deleted_at IS NULL AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)", [$like, $like, $like, $like])['total'];

    // 2. Get records
    $records = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name, phone, email, gender, date_of_birth, created_at
         FROM patients
         WHERE deleted_at IS NULL
           AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
        [$like, $like, $like, $like, $limit, $offset]
    );
} else {
    // 1. Get total for all items
    $totalCount = $db->queryOne("SELECT COUNT(*) as total FROM items")['total'];

    // 2. Get records (Fixed the missing comma after COUNT and removed COUNT from this query)
    $records = $db->query(
        "SELECT 
            i.id, i.name, i.price, i.description, i.image_path,
            GROUP_CONCAT(c.id) AS category_ids,
            GROUP_CONCAT(c.name SEPARATOR ', ') AS category_names
        FROM items i
        LEFT JOIN item_categories ic ON i.id = ic.item_id
        LEFT JOIN categories c ON ic.category_id = c.id AND c.id IS NOT NULL
        GROUP BY i.id
        ORDER BY i.id DESC
        LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
}

// Prepare Meta Logic
$rangeStart = $offset + 1;
$rangeEnd = min($offset + $limit, $totalCount);
$totalPages = ceil($totalCount / $limit);

// Build response structure
$responseData = [
    "records" => $records,
    "meta" => [
        "offset"  => (int)$offset ,
        "display" => "$rangeStart-$rangeEnd of $totalCount",
        "total"   => (int)$totalCount,
        "limit"   => $limit,
        "total_pages"    => (int)$totalPages
    ]
];

Api::success($responseData);