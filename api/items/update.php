<?php
/**
 * POST api/items/update.php
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

$item_id = $_POST['item_id'] ?? null;
if (!$item_id) {
    Api::error('Item ID is required for updating.');
    exit;
}

// 1. Fetch existing item to get the current image path
$existing_item = $db->query("SELECT * FROM items WHERE id = ?", [$item_id])[0] ?? null;

if (!$existing_item) {
    Api::error('Item not found.');
    exit;
}

$upload_base_dir = __DIR__ . '/../../uploads/items/';
$image_path = $existing_item['image_path']; // Default to existing path

// 2. Handle File Upload Logic (Unchanged as requested)
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $max_size = 5 * 1024 * 1024; 
    if ($_FILES['image']['size'] > $max_size) {
        Api::error('File is too large. Maximum size allowed is 5MB.');
        exit;
    }

    $file_tmp  = $_FILES['image']['tmp_name'];
    $file_name = time() . '_' . $_FILES['image']['name'];
    $target_file = $upload_base_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        if (!empty($existing_item['image_path'])) {
            $old_file_full_path = __DIR__ . '/../../' . $existing_item['image_path'];
            if (file_exists($old_file_full_path)) {
                unlink($old_file_full_path);
            }
        }
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

// Capture current categories from payload
$category_ids = $_POST['category_ids'] ?? [];

if (empty($data['name']) || empty($data['price']) || empty($data['description']) || empty($data['image_path'])) {
    Api::error('All fields are required.');
    exit;
}

// 4. Update Database
try {
    $db->beginTransaction();

    // Update the main item info
    $db->update('items', $data, ['id' => $item_id]);

    // --- CATEGORY SYNC LOGIC ---
    
    // Step A: Remove all existing category relationships for this item
    $db->delete('item_categories', ['item_id' => $item_id]);

    // Step B: Re-insert only the currently selected categories
    if (!empty($category_ids) && is_array($category_ids)) {
        foreach ($category_ids as $cat_id) {
            $db->insert('item_categories', [
                'item_id'     => $item_id,
                'category_id' => (int)$cat_id
            ]);
        }
    }

    $db->commit();
    Api::success(null, 'Item and categories updated successfully.');

} catch (Exception $e) {
    if($db->inTransaction()) $db->rollBack();
    Api::error('Database error: ' . $e->getMessage());
}