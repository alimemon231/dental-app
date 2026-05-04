<?php
/**
 * POST api/staff/update.php
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

$id = (int)($_POST['staff_id'] ?? 0);
if (!$id) { Api::error('Staff ID is required.'); exit; }

// Verify user exists and is actually staff
if (!$db->exists('users', ['user_id' => $id, 'user_type' => "staff"])) {
    Api::error('Staff member not found.', 404); 
    exit;
}

$data = [
    'name'     => trim($_POST['name']     ?? ''),
    'mobile'   => trim($_POST['phone']    ?? '') ?: null,
    'username' => trim($_POST['username'] ?? '') ?: null,
    'address'  => trim($_POST['address']  ?? '') ?: null,
];

// 1. Basic Validation
if (empty($data['name']) || empty($data['mobile']) || empty($data['username']) || empty($data['address'])) {
    Api::error('Please fill all required fields.');
    exit;
}

$existingUser = $db->queryOne(
    "SELECT user_id FROM users WHERE username = ? AND user_id != ?", 
    [$data['username'], $id]
);

if ($existingUser) {
    Api::error('This username is already taken by another user.');
    exit;
}

// 3. Password Logic (Same as Doctor API)
$password   = $_POST['password'] ?? '';
$rePassword = $_POST['re_password'] ?? '';

if (!empty($password)) {
    if ($password !== $rePassword) {
        Api::error('Passwords do not match.');
        exit;
    }

    if (strlen($password) < 6) {
        Api::error('Password must be at least 6 characters long.');
        exit;
    }

    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
}

// 4. Update and Respond
try {
    $db->update('users', $data, ['user_id' => $id]);
    
    // Fetch fresh data (excluding password) to return to frontend
    $staff = $db->queryOne(
        "SELECT user_id, username, name, mobile, address, user_type 
         FROM users WHERE user_id = ?", 
        [$id]
    );
    
    Api::success($staff, 'Staff member updated successfully.');
} catch (Exception $e) {
    Api::error('Update failed: ' . $e->getMessage());
}