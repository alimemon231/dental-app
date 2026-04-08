<?php
/**
 * POST api/auth/logout.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->logout();

Api::success(null, 'Logged out successfully.');
