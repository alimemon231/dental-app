<?php
/**
 * POST api/patients/delete.php
 * Soft-deletes (sets deleted_at) rather than hard delete.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$id = (int)($_POST['patient_id'] ?? 0);
if (!$id) { Api::error('Patient ID is required.'); exit; }

if (!$db->exists('patients', ['id' => $id, 'deleted_at' => null])) {
    Api::error('Patient not found.', 404); exit;
}

$db->softDelete('patients', ['id' => $id]);
Api::success(null, 'Patient deleted successfully.');
