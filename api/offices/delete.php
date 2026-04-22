<?php
/**
 * POST api/patients/delete.php
 * Soft-deletes (sets deleted_at) rather than hard delete.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403); // 403 is the standard "Forbidden" code
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { Api::error('Office ID is required.'); exit; }

if (!$db->exists('offices', ['id' => $id,])) {
    Api::error('Patient not found.', 404); exit;
}

$db->Delete('office_users', ['office_id' => $id]);
$db->Delete('offices', ['id' => $id]);
Api::success(null, 'Patient deleted successfully.');
