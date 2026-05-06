<?php
/**
 * GET api/insurance/load-active.php
 * Fetches only active insurance companies for dropdown selection.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Fetch only active insurance providers
$insurance = $db->query(
    "SELECT id, name 
     FROM insurance 
     WHERE status = 'active' 
     ORDER BY name ASC"
);

Api::success($insurance);