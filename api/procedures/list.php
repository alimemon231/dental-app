<?php
/**
 * GET api/procedures/list.php
 * Query params: search
 * Fetches the list of dental services/procedures.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Procedures are listed for both Staff (to select) and Admin (to manage),
// so we only require a valid login session via requireAuth().

$search = trim($_GET['search'] ?? '');

if ($search) {
    $like = '%' . $search . '%';
    // Search by procedure name or description
    $procedures = $db->query(
        "SELECT *
         FROM procedures 
         WHERE name LIKE ? OR description LIKE ?
         ORDER BY name ASC",
        [$like, $like]
    );
} else {
    // Return all procedures ordered by newest first for the management table
    $procedures = $db->query(
        "SELECT *
         FROM procedures 
         ORDER BY id DESC"
    );
}

// Return the data to procedures.js
Api::success($procedures);