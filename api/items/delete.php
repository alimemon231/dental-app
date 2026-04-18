<?php
/**
 * POST api/items/delete.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { Api::error('Item ID is required.'); exit; }

// 1. Fetch the item details first to get the image path
$item = $db->query("SELECT image_path FROM items WHERE id = ?", [$id])[0] ?? null;

if (!$item) {
    Api::error('Item not found.', 404); 
    exit;
}

// 2. Remove the physical file from the directory
if (!empty($item['image_path'])) {
    // Construct the absolute path to the file
    $file_to_delete = __DIR__ . '/../../' . $item['image_path'];
    
    if (file_exists($file_to_delete)) {
        unlink($file_to_delete);
    }
}

// 3. Delete the record from the database
$db->Delete('items', ['id' => $id]);

Api::success(null, 'Item and its image were deleted successfully.');