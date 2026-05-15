<?php
/**
 * POST api/lab-cases/deactivate.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized.', 403);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('ID is required.');
    exit;
}

try {
    $db->update('labs_patner', ['status' => 'deactive'], ['id' => $id]);
    Api::success(null, 'Lab deactivated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}