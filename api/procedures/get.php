<?php
/**
 * GET api/procedures/get.php?id=X
 * Fetches a single procedure's details including status for editing.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validate ID from URL parameters
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Procedure ID is required.'); 
    exit; 
}

// 2. Fetch Procedure Data
// Added 'status' to the SELECT statement
$procedure = $db->queryOne(
    "SELECT *
     FROM procedures
     WHERE id = ?",
    [$id]
);

// 3. Handle Not Found
if (!$procedure) { 
    Api::error('Procedure not found.', 404); 
    exit; 
}

// 4. Return Data to procedures.js
// The JS frontend will now receive p.status to populate the dropdown
Api::success($procedure);