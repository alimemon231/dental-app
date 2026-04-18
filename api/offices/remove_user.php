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

$doctor_id = (int)($_POST['user_id'] ?? 0);
$office_id = (int)($_POST['office_id'] ?? 0);
if (!$office_id) { Api::error('Office ID is required.'); exit; }
if (!$doctor_id) { Api::error('User ID is required.'); exit; }

if (!$db->exists('office_users', ['user_id' => $doctor_id, 'office_id' => $office_id ,])) {
    Api::error('Doctor or Staff Not Assignedto Orgnization.', 404); exit;
}

$db->delete("office_users" , ['user_id' => $doctor_id, 'office_id' => $office_id ,]);
Api::success(null, 'User Removed from Office.');
