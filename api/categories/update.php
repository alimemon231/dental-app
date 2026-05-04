<?php
/**
 * POST api/categories/update.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization & Method Check
if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Validate Inputs
$category_id = (int)($_POST['category_id'] ?? 0);
if (!$category_id) {
    Api::error('Category ID is required for updating.');
    exit;
}

$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
];

if (empty($data['name'])) {
    Api::error('Category name is required.');
    exit;
}

// 3. Verify Category Exists
if (!$db->exists('categories', ['id' => $category_id])) {
    Api::error('Category not found.', 404);
    exit;
}

// 4. DUPLICATE CHECK
// Check if another category (not this one) already uses the new name
$duplicate = $db->queryOne(
    "SELECT id FROM categories WHERE name = ? AND id != ?", 
    [$data['name'], $category_id]
);

if ($duplicate) {
    Api::error('This category name already exists. Please choose a different name.');
    exit;
}

// 5. Update Database
try {
    $db->update('categories', $data, ['id' => $category_id]);
    
    // Return updated object for UI refresh
    $updatedCategory = array_merge(['id' => $category_id], $data);
    Api::success($updatedCategory, 'Category updated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}