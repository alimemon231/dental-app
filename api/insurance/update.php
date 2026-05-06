<?php
/**
 * POST api/insurance/update.php
 * Update an existing insurance company.
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
// Matching the 'insurance_id' key sent by insurance.js
$insurance_id = (int)($_POST['insurance_id'] ?? 0);
if (!$insurance_id) {
    Api::error('Insurance ID is required for updating.');
    exit;
}

$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'email'       => trim($_POST['email'] ?? ''),
    'description' => trim($_POST['notes'] ?? $_POST['description'] ?? ''), // Maps 'notes' from front-end to DB 'description'
    'status'      => trim($_POST['status'] ?? 'active') 
];

// Basic Validation
if (empty($data['name'])) {
    Api::error('Insurance name is required.');
    exit;
}

// Ensure status is valid
if (!in_array($data['status'], ['active', 'deactive'])) {
    $data['status'] = 'active';
}

// 3. Verify Record Exists
if (!$db->exists('insurance', ['id' => $insurance_id])) {
    Api::error('Insurance company not found.', 404);
    exit;
}

// 4. Duplicate Check
// Ensure we don't rename this company to another company that already exists
$duplicate = $db->queryOne(
    "SELECT id FROM insurance WHERE name = ? AND id != ?", 
    [$data['name'], $insurance_id]
);

if ($duplicate) {
    Api::error('This insurance company name already exists.');
    exit;
}

// 5. Update Database
try {
    $db->update('insurance', $data, ['id' => $insurance_id]);
    
    // Return updated object for UI refresh
    $updatedInsurance = array_merge(['id' => $insurance_id], $data);
    Api::success($updatedInsurance, 'Insurance company updated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}