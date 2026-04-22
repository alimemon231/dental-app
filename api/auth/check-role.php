<?php
/**
 * POST api/auth/check-role.php
 * Checks if the current logged-in user matches the requested role.
 */
require_once __DIR__ . '/../../includes/Auth.php';


$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$requestedRole = trim($_POST['role'] ?? '');

if (empty($requestedRole)) {
    Api::error('Role parameter is required');
    exit;
}


if ($auth->hasRole($requestedRole)) {
    Api::success(null, 'Valid user');
} else {
    
    Api::error("You are not authrorized to use this page");
}