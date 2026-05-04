<?php
/**
 * GET api/categories/get.php?id=5
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validate ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Category ID is required.'); 
    exit; 
}

// 2. Fetch Category Data
$category = $db->queryOne(
    "SELECT id, name, description
     FROM categories
     WHERE id = ?",
    [$id]
);

// 3. Handle Not Found
if (!$category) { 
    Api::error('Category not found.', 404); 
    exit; 
}

// 4. Return Data
Api::success($category);