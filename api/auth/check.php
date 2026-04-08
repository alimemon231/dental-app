<?php
/**
 * GET api/auth/check.php
 * Verifies session is alive. Called by every page on load.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

if (!$auth->check()) {
    Api::error('Unauthorized', 401);
    exit;
}

Api::success(['user' => $auth->user()], 'Authenticated');
