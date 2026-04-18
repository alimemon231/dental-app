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
    'office_name'    => trim($_POST['name']    ?? ''),
    'phone'     => trim($_POST['phone']     ?? ''),
    'email'        => $_POST['email']             ?? null,
    'address' => $_POST['address']      ?: null,
    
];

if (empty($data['office_name']) || empty($data['phone']) || empty($data['email']) || empty($data['address'])) {
    Api::error('All fields required');
    exit;
}

$id = $db->insert('offices', $data);
Api::success(null, 'Office created successfully.');
