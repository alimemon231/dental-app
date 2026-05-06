<?php
/**
 * GET api/procedures/load-active.php
 * Fetches only active procedures for dropdown selection.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Fetch only active procedures
$procedures = $db->query(
    "SELECT id, name 
     FROM procedures 
     WHERE status = 'active' 
     ORDER BY name ASC"
);

Api::success($procedures);