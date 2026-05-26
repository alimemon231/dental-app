<?php
/**
 * POST api/auth/check-role.php
 * Validates if the currently authenticated session matches any of the requested security workspace roles.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force basic auth session tracking initialization
$auth->requireAuth();

if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

// Intercept both array structured lists ('roles') or singular parameters ('role') seamlessly
$requestedRoles = $_POST['roles'] ?? ($_POST['role'] ? [$_POST['role']] : null);

if (empty($requestedRoles) || !is_array($requestedRoles)) {
    Api::error('Target verification configuration roles are required.');
    exit;
}

// Loop through each provided role option. Grant access immediately if the user possesses ANY of them.
$accessGranted = false;
foreach ($requestedRoles as $role) {
    if ($auth->hasRole(trim($role))) {
        $accessGranted = true;
        break; // Stop evaluating further rules once authorization condition is satisfied
    }
}

if ($accessGranted) {
    Api::success(null, 'Security role parameters authorized successfully.');
} else {
    Api::error("You are not authorized to use this page", 403);
}