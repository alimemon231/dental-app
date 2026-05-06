<?php
/**
 * POST api/procedures/update.php
 * Update an existing dental procedure.
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
$procedure_id = (int)($_POST['procedure_id'] ?? 0);
if (!$procedure_id) {
    Api::error('Procedure ID is required for updating.');
    exit;
}

// Added 'status' to the data collection
$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'status'      => trim($_POST['status'] ?? 'active') 
];

// Basic Validation
if (empty($data['name'])) {
    Api::error('Procedure name is required.');
    exit;
}

// Ensure status is a valid value
if (!in_array($data['status'], ['active', 'deactive'])) {
    $data['status'] = 'active';
}

// 3. Verify Procedure Exists
if (!$db->exists('procedures', ['id' => $procedure_id])) {
    Api::error('Procedure not found.', 404);
    exit;
}

// 4. DUPLICATE CHECK
$duplicate = $db->queryOne(
    "SELECT id FROM procedures WHERE name = ? AND id != ?", 
    [$data['name'], $procedure_id]
);

if ($duplicate) {
    Api::error('This procedure name already exists.');
    exit;
}

// 5. Update Database
try {
    $db->update('procedures', $data, ['id' => $procedure_id]);
    
    // Return updated object for UI refresh
    $updatedProcedure = array_merge(['id' => $procedure_id], $data);
    Api::success($updatedProcedure, 'Procedure updated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}