<?php
/**
 * POST api/patients/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

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
    'created_by'    => $auth->userId(),
];

if (empty($data['first_name']) || empty($data['last_name'])) {
    Api::error('First name and last name are required.');
    exit;
}

$id = $db->insert('patients', $data);

// Generate patient code: PAT-00001
$db->update('patients', ['patient_code' => 'PAT-' . str_pad($id, 5, '0', STR_PAD_LEFT)], ['id' => $id]);

$patient = $db->selectOne('patients', ['id' => $id]);
Api::success($patient, 'Patient created successfully.');
