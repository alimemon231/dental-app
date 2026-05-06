<?php
/**
 * GET api/insurance/list.php
 * Query params: search
 * Fetches the list of insurance companies.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Insurance providers are listed for Staff (to link patients) 
// and Admin (to manage), so we only require a valid login.

$search = trim($_GET['search'] ?? '');

if ($search) {
    $like = '%' . $search . '%';
    // Search by company name, email, or notes
    $insurance = $db->query(
        "SELECT id, name, email, description, status
         FROM insurance 
         WHERE name LIKE ? OR email LIKE ? OR notes LIKE ?
         ORDER BY name ASC",
        [$like, $like, $like]
    );
} else {
    // Return all providers ordered by newest first for the management table
    $insurance = $db->query(
        "SELECT id, name, email, description, status
         FROM insurance 
         ORDER BY id DESC"
    );
}

// Return the data to insurance.js
Api::success($insurance);