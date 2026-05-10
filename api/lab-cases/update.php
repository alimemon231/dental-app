<?php
/**
 * POST api/lab-cases/update.php
 * Update an existing Lab Case Type.
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
// Using 'case_id' as sent by lab-cases.js
$case_id = (int)($_POST['case_id'] ?? 0);
if (!$case_id) {
    Api::error('Case ID is required for updating.');
    exit;
}

$name   = trim($_POST['name'] ?? '');
$target = trim($_POST['target'] ?? '');
$status = trim($_POST['status'] ?? 'active');

// Basic Validation
if (empty($name)) {
    Api::error('Case name is required.');
    exit;
}

if (empty($target) || !in_array($target, ['teeth', 'arch'])) {
    Api::error('A valid target area (Teeth or Arch) must be selected.');
    exit;
}

// Ensure status is valid
if (!in_array($status, ['active', 'deactive'])) {
    $status = 'active';
}

// 3. Verify Record Exists in 'case_type' table
if (!$db->exists('case_type', ['id' => $case_id])) {
    Api::error('Lab case type not found.', 404);
    exit;
}

// 4. Duplicate Check
// Ensure we don't rename this case to another one that already exists
$duplicate = $db->queryOne(
    "SELECT id FROM case_type WHERE name = ? AND id != ?", 
    [$name, $case_id]
);

if ($duplicate) {
    Api::error('A lab case with this name already exists.');
    exit;
}

// 5. Update Database
try {
    $data = [
        'name'   => $name,
        'target' => $target,
        'status' => $status
    ];

    $db->update('case_type', $data, ['id' => $case_id]);
    
    // Return updated object for UI refresh
    $updatedCase = array_merge(['id' => $case_id], $data);
    Api::success($updatedCase, 'Lab case type updated successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}