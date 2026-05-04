<?php
/**
 * POST api/categories/delete.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check
if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

// 2. Method Validation
if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 3. ID Validation
$id = (int)($_POST['id'] ?? 0);
if (!$id) { 
    Api::error('Category ID is required.'); 
    exit; 
}

// 4. Verify Existence
if (!$db->exists('categories', ['id' => $id])) {
    Api::error('Category not found.', 404);
    exit;
}

// 5. Delete from Database
try {
    $db->delete('categories', ['id' => $id]);
    $db->delete('item_categories', ['category_id' => $id]);
    Api::success(null, 'Category deleted successfully.');
} catch (Exception $e) {
    // If you have foreign key constraints, this will catch errors 
    // if items are still assigned to this category.
    Api::error('Database error: ' . $e->getMessage());
}