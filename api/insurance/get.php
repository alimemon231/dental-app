<?php
/**
 * GET api/insurance/get.php?id=X
 * Fetches a single insurance company's details for editing.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validate ID from URL parameters
$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Insurance ID is required.'); 
    exit; 
}

// 2. Fetch Insurance Data
// Using 'description' as requested for the DB column
$insurance = $db->queryOne(
    "SELECT id, name, email, phone , description, status
     FROM insurance
     WHERE id = ?",
    [$id]
);

// 3. Handle Not Found
if (!$insurance) { 
    Api::error('Insurance company not found.', 404); 
    exit; 
}

// 4. Return Data to insurance.js
Api::success($insurance);