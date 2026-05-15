<?php
/**
 * POST api/lab-steps/update.php
 * Update an existing Lab Workflow Step.
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
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Step ID is required for updating.');
    exit;
}

$name   = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'active');

// Basic Validation
if (empty($name)) {
    Api::error('Step name is required.');
    exit;
}

// Ensure status is valid
if (!in_array($status, ['active', 'deactive'])) {
    $status = 'active';
}

// 3. Verify Record Exists
if (!$db->exists('lab_steps', ['id' => $id])) {
    Api::error('Lab step not found.', 404);
    exit;
}

// 4. Duplicate Check
$duplicate = $db->queryOne(
    "SELECT id FROM lab_steps WHERE name = ? AND id != ?", 
    [$name, $id]
);

if ($duplicate) {
    Api::error('A lab step with this name already exists.');
    exit;
}

// 5. Update Database
try {
    $data = [
        'name'   => $name,
        'status' => $status
    ];

    $db->update('lab_steps', $data, ['id' => $id]);
    
    // Return updated object for UI refresh
    $updatedStep = array_merge(['id' => $id], $data);
    Api::success($updatedStep, 'Lab step updated successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}