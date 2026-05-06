<?php
/**
 * POST api/procedures/deactivate.php
 * Soft-deactivation logic.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    Api::error('Procedure ID is required.');
    exit;
}

// Verify existence
if (!$db->exists('procedures', ['id' => $id])) {
    Api::error('Procedure not found.', 404);
    exit;
}

try {
    // Perform "Soft Delete" by updating status to deactive
    $db->update('procedures', ['status' => 'deactive'], ['id' => $id]);
    
    Api::success(null, 'Procedure deactivated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}