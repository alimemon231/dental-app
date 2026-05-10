<?php
/**
 * GET api/lab-cases/list.php
 * Query params: search
 * Fetches the list of lab case types (Teeth/Arch).
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Lab cases are listed for Staff (to create lab orders) 
// and Admin (to manage), so we only require a valid login.

$search = trim($_GET['search'] ?? '');

if ($search) {
    $like = '%' . $search . '%';
    
    // Search by case name or target type
    $cases = $db->query(
        "SELECT id, name, target, status
         FROM case_type 
         WHERE name LIKE ? OR target LIKE ?
         ORDER BY name ASC",
        [$like, $like]
    );
} else {
    // Return all case types ordered by newest first for the management table
    $cases = $db->query(
        "SELECT id, name, target, status
         FROM case_type 
         ORDER BY id DESC"
    );
}

// Return the data to lab-cases.js
Api::success($cases);