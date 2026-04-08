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
if (!$id) { Api::error('Patient ID is required.'); exit; }

if (!$db->exists('patients', ['id' => $id, 'deleted_at' => null])) {
    Api::error('Patient not found.', 404); exit;
}

$data = [
    'first_name'    => trim($_POST['first_name']    ?? ''),
    'last_name'     => trim($_POST['last_name']     ?? ''),
    'gender'        => $_POST['gender']             ?? null,
    'date_of_birth' => $_POST['date_of_birth']      ?: null,
    'blood_group'   => $_POST['blood_group']        ?: null,
    'referred_by'   => trim($_POST['referred_by']   ?? '') ?: null,
    'phone'         => trim($_POST['phone']         ?? '') ?: null,
    'email'         => trim($_POST['email']         ?? '') ?: null,
    'city'          => trim($_POST['city']          ?? '') ?: null,
    'address'       => trim($_POST['address']       ?? '') ?: null,
    'allergies'     => trim($_POST['allergies']     ?? '') ?: null,
    'notes'         => trim($_POST['notes']         ?? '') ?: null,
    'updated_at'    => date('Y-m-d H:i:s'),
];

if (empty($data['first_name']) || empty($data['last_name'])) {
    Api::error('First name and last name are required.');
    exit;
}

$db->update('patients', $data, ['id' => $id]);
$patient = $db->selectOne('patients', ['id' => $id]);
Api::success($patient, 'Patient updated successfully.');
