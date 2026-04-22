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
    'name'    => trim($_POST['name']    ?? ''),
    'mobile'     => trim($_POST['phone']     ?? ''),
    'email'        => $_POST['email']             ?? null,
    'address' => $_POST['address']      ?: null,
    'password' => $_POST['password']      ?: null,
    'user_type' => "doctor",
    'status' => "active",
    
];

if (empty($data['name']) || empty($data['mobile']) || empty($data['email']) || empty($data['address'])|| empty($data['password'])) {
    Api::error('All fields required');
    exit;
}

$data["password"] = password_hash($data["password"] , PASSWORD_DEFAULT);
$id = $db->insert('users', $data);
Api::success(null, 'Doctor created successfully.');
