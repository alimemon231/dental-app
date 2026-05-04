<?php
/**
 * POST api/categories/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Check Authorization
if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

// 2. Validate Method
if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 3. Collect and Validate Data
$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? '')
];

// Basic Validation
if (empty($data['name'])) {
    Api::error('Category name is required.');
    exit;
}

// 4. Check for Duplicate Category Name
$exists = $db->queryOne("SELECT id FROM categories WHERE name = ?", [$data['name']]);
if ($exists) {
    Api::error('A category with this name already exists.');
    exit;
}

// 5. Insert into Database
try {
    $id = $db->insert('categories', $data);
    
    // Return the new category object so the JS can prepend it to the table if needed
    $newCategory = [
        'id'          => $id,
        'name'        => $data['name'],
        'description' => $data['description']
    ];
    
    Api::success($newCategory, 'Category created successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}