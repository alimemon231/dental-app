<?php
/**
 * POST api/items/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';
$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403); // 403 is the standard "Forbidden" code
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

// 1. Setup Image Directory
// Using realpath to ensure the folder exists relative to this file
$upload_base_dir = __DIR__ . '/../../uploads/items/';
if (!is_dir($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

$image_path = null;

// 2. Handle File Upload Logic
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['image']['size'] > $max_size) {
        Api::error('File is too large. Maximum size allowed is 5MB.');
        exit;
    }
    $file_tmp  = $_FILES['image']['tmp_name'];
    $file_name = time() . '_' . $_FILES['image']['name']; // Unique name to prevent overwriting
    $target_file = $upload_base_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        // This is the path we store in the database
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
    'image_path'  => $image_path, // Now holds the path string
];

// Validation
if (empty($data['name']) || empty($data['price']) || empty($data['description']) || empty($data['image_path'])) {
    Api::error('All fields (including the image) are required.');
    exit;
}

// 4. Insert into Database
try {
    $id = $db->insert('items', $data);
    Api::success(['id' => $id], 'Item created successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}