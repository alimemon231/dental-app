<?php
/**
 * POST api/lab-steps/create.php
 * Create a new Lab Workflow Step.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check (Admin Only)
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Validate Method
if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 3. Collect and Validate Data
$name = trim($_POST['name'] ?? '');

// Basic Validation
if (empty($name)) {
    Api::error('Step name is required.');
    exit;
}

// 4. Check for Duplicate Step Name
$exists = $db->queryOne("SELECT id FROM lab_steps WHERE name = ?", [$name]);
if ($exists) {
    Api::error('A lab step with this name already exists.');
    exit;
}

// 5. Insert into Database
try {
    $data = [
        'name'   => $name,
        'status' => 'active' // Default status on creation
    ];

    // Insert into 'lab_steps' table
    $id = $db->insert('lab_steps', $data);
    
    if (!$id) {
        throw new Exception("Failed to save record to database.");
    }

    // Construct response for lab-steps.js
    $newStep = [
        'id'     => $id,
        'name'   => $name,
        'status' => 'active'
    ];
    
    Api::success($newStep, 'Lab step created successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}