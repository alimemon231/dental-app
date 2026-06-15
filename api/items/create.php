<?php
/**
 * POST api/items/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';
$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

// 1. Setup Image Directory
$upload_base_dir = __DIR__ . '/../../uploads/items/';
if (!is_dir($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

$image_path = null;

// 2. Handle File Upload Logic (Kept exactly as provided)
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['image']['size'] > $max_size) {
        Api::error('File is too large. Maximum size allowed is 5MB.');
        exit;
    }
    $file_tmp  = $_FILES['image']['tmp_name'];
    $file_name = time() . '_' . $_FILES['image']['name']; 
    $target_file = $upload_base_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        $image_path = 'uploads/items/' . $file_name;
    } else {
        Api::error('Failed to move uploaded file.');
        exit;
    }
}

// 3. Collect and Validate Data
$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'price'       => trim($_POST['price'] ?? ''),
    'item_code'       => trim($_POST['item_code'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'image_path'  => $image_path,
];

// Capture category_ids from payload
$category_ids = $_POST['category_ids'] ?? [];

// Validation
if (empty($data['name']) || empty($data['price']) || empty($data['description']) || empty($data['image_path'])) {
    Api::error('All fields (including the image) are required.');
    exit;
}

// 4. Insert into Database
try {
    // Start Transaction (Optional but recommended for multi-table inserts)
    $db->beginTransaction();

    // Insert Item
    $item_id = $db->insert('items', $data);

    // 5. Save Relationships (New Logic)
    if (!empty($category_ids) && is_array($category_ids)) {
        foreach ($category_ids as $cat_id) {
            $db->insert('item_categories', [
                'item_id'     => $item_id,
                'category_id' => (int)$cat_id
            ]);
        }
    }

    $db->commit();
    Api::success(['id' => $item_id], 'Item created and categories assigned successfully.');

} catch (Exception $e) {
    // Rollback if something goes wrong
    if($db->inTransaction()) $db->rollBack();
    Api::error('Database error: ' . $e->getMessage());
}