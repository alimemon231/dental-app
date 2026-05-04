<?php
/**
 * GET api/categories/list.php
 * Query params: search
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Categories usually don't need strict admin role for listing, 
// but requireAuth() ensures the user is logged in.

$search = trim($_GET['search'] ?? '');

if ($search) {
    $like = '%' . $search . '%';
    // Search by name or description
    $categories = $db->query(
        "SELECT id, name, description 
         FROM categories 
         WHERE name LIKE ? OR description LIKE ?
         ORDER BY name ASC",
        [$like, $like]
    );
} else {
    // Return all categories ordered alphabetically
    $categories = $db->query(
        "SELECT id, name, description 
         FROM categories 
         ORDER BY id DESC"
    );
}

// Return the data in the same format as items
Api::success($categories);