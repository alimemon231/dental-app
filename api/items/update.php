<?php
/**
 * POST api/items/update.php
 */
require_once __DIR__ . '/../../includes/Auth.php';


$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

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

// 2. Handle File Upload Logic (Only if a new file is provided)
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    
    // Size check
    $max_size = 5 * 1024 * 1024; 
    if ($_FILES['image']['size'] > $max_size) {
        Api::error('File is too large. Maximum size allowed is 5MB.');
        exit;
    }

    $file_tmp  = $_FILES['image']['tmp_name'];
    $file_name = time() . '_' . $_FILES['image']['name'];
    $target_file = $upload_base_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        
        // --- DELETE OLD FILE ---
        // We calculate the absolute path to the old file to delete it
        if (!empty($existing_item['image_path'])) {
            $old_file_full_path = __DIR__ . '/../../' . $existing_item['image_path'];
            if (file_exists($old_file_full_path)) {
                unlink($old_file_full_path);
            }
        }

        // Set the new path for the database
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
    'description' => trim($_POST['description'] ?? ''),
    'image_path'  => $image_path, 
];

// Validation (Notice image_path is checked, but it will be valid because we defaulted to the old one)
if (empty($data['name']) || empty($data['price']) || empty($data['description']) || empty($data['image_path'])) {
    Api::error('All fields are required.');
    exit;
}

// 4. Update Database
try {
    // Assuming your Database class has an update method: update($table, $data, $where)
    $db->update('items', $data, ['id' => $item_id]);
    Api::success(null, 'Item updated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}