<?php
/**
 * POST api/patients/update.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
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
    'mobile'  => trim($_POST['phone']   ?? '') ?: null,
    'username'   => trim($_POST['username']   ?? '') ?: null,
    'address' => trim($_POST['address'] ?? '') ?: null,
];

if (empty($data['name']) || empty($data['mobile']) || empty($data['username']) || empty($data['address'])) {
    Api::error('Please fill all fields.');
    exit;
}

/* ---- Password Logic Addition ---- */
$password   = $_POST['password'] ?? '';
$rePassword = $_POST['re_password'] ?? '';

// Only run this logic if the password field is not blank
if (!empty($password)) {
    if ($password !== $rePassword) {
        Api::error('Passwords do not match.');
        exit;
    }

    if (strlen($password) < 6) {
        Api::error('Password must be at least 6 characters long.');
        exit;
    }

    // Encrypt and add to the data array for update
    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
}
/* --------------------------------- */

$db->update('users', $data, ['user_id' => $id]);

// Fetch updated record (excluding password for security in the response)
$patient = $db->queryOne("SELECT user_id, name, mobile, email, address, user_type FROM users WHERE user_id = ?", [$id]);

Api::success($patient, 'Doctor updated successfully.');