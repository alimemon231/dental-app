<?php
/**
 * POST api/patients/update.php
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

$id = (int)($_POST['doctor_id'] ?? 0);
if (!$id) { Api::error('Doctor ID is required.'); exit; }

if (!$db->exists('users', ['user_id' => $id, 'user_type' => "doctor"])) {
    Api::error('Doctor not found.', 404); exit;
}

$data = [
    'name'    => trim($_POST['name']    ?? ''),
    'mobile'         => trim($_POST['phone']         ?? '') ?: null,
    'email'         => trim($_POST['email']         ?? '') ?: null,
    'address'       => trim($_POST['address']       ?? '') ?: null,
];

if (empty($data['name']) || empty($data['mobile']) || empty($data['email']) || empty($data['address'])) {
    Api::error('Please fill all fields.');
    exit;
}

$db->update('users', $data, ['user_id' => $id]);
$patient = $db->selectOne('users', ['user_id' => $id]);
Api::success($patient, 'Docotr updated successfully.');
