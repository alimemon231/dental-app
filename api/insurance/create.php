<?php
/**
 * POST api/insurance/create.php
 * Create a new insurance company/provider.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Check Authorization (Only Admin can manage insurance providers)
if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

// 2. Validate Method
if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 3. Collect and Validate Data
$data = [
    'name'   => trim($_POST['name'] ?? ''),
    'email'  => trim($_POST['email'] ?? ''),
    'description'  => trim($_POST['description'] ?? ''),
    'status' => 'active' // Default status on creation
];

// Basic Validation
if (empty($data['name'])) {
    Api::error('Insurance company name is required.');
    exit;
}

// 4. Check for Duplicate Insurance Name
// Prevents creating "MetLife" twice
$exists = $db->queryOne("SELECT id FROM insurance WHERE name = ?", [$data['name']]);
if ($exists) {
    Api::error('An insurance company with this name already exists.');
    exit;
}

// 5. Insert into Database
try {
    // Inserts into the 'insurance' table
    $id = $db->insert('insurance', $data);
    
    // Construct the response object for the insurance.js frontend
    $newInsurance = [
        'id'     => $id,
        'name'   => $data['name'],
        'email'  => $data['email'],
        'notes'  => $data['description'],
        'status' => $data['status']
    ];
    
    Api::success($newInsurance, 'Insurance provider created successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}