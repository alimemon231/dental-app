<?php
/**
 * GET api/lab-cases/get.php?id=X
 * Fetches a single Lab Case Type's details for editing.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validate ID from URL parameters
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Case ID is required.'); 
    exit; 
}

// 2. Fetch Case Data
// Selecting name, target, and status from case_type table
$case = $db->queryOne(
    "SELECT *
     FROM labs_patner
     WHERE id = ?",
    [$id]
);

// 3. Handle Not Found
if (!$case) { 
    Api::error('Lab case type not found.', 404); 
    exit; 
}

// 4. Return Data to lab-cases.js
Api::success($case);