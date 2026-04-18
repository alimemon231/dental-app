<?php
/**
 * POST api/patients/update.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$id = (int)($_POST['patient_id'] ?? 0);
if (!$id) { Api::error('Office ID is required.'); exit; }

if (!$db->exists('offices', ['id' => $id,])) {
    Api::error('Patient not found.', 404); exit;
}

$data = [
    'office_name'    => trim($_POST['name']    ?? ''),
    'phone'         => trim($_POST['phone']         ?? '') ?: null,
    'email'         => trim($_POST['email']         ?? '') ?: null,
    'address'       => trim($_POST['address']       ?? '') ?: null,
];

if (empty($data['office_name']) || empty($data['phone']) || empty($data['email']) || empty($data['address'])) {
    Api::error('First name and last name are required.');
    exit;
}

$db->update('offices', $data, ['id' => $id]);
$patient = $db->selectOne('offices', ['id' => $id]);
Api::success($patient, 'Office updated successfully.');
