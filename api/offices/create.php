<?php
/**
 * POST api/patients/create.php
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
