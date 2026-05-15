<?php
/**
 * POST api/adm-labs/update.php
 * Update an existing Lab Partner's details.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization & Method Check
if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Validate Inputs
// Note: We use 'id' as sent by labs-manage.js
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Lab ID is required for updating.');
    exit;
}

$name    = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$email   = trim($_POST['email'] ?? '');
$status  = trim($_POST['status'] ?? 'active');

// Basic Validation
if (empty($name)) {
    Api::error('Lab name is required.');
    exit;
}

// Ensure status is valid
if (!in_array($status, ['active', 'deactive'])) {
    $status = 'active';
}

// 3. Verify Record Exists in 'labs_patner' table
if (!$db->exists('labs_patner', ['id' => $id])) {
    Api::error('Lab record not found.', 404);
    exit;
}

// 4. Duplicate Check
// Ensure we don't rename this lab to another lab's name
$duplicate = $db->queryOne(
    "SELECT id FROM labs_patner WHERE name = ? AND id != ?", 
    [$name, $id]
);

if ($duplicate) {
    Api::error('A lab with this name already exists.');
    exit;
}

// 5. Update Database
try {
    $updateData = [
        'name'    => $name,
        'address' => $address,
        'phone'   => $phone,
        'email'   => $email,
        'status'  => $status
    ];

    $db->update('labs_patner', $updateData, ['id' => $id]);
    
    // Return updated object for UI refresh in labs-manage.js
    $updatedLab = array_merge(['id' => $id], $updateData);
    Api::success($updatedLab, 'Lab details updated successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}