<?php
/**
 * POST api/procedures/create.php
 * Create a new dental procedure/service.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Check Authorization (Only Admin can manage procedures)
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
    'name'        => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'status'      => 'active' // Default status on creation
];

// Basic Validation
if (empty($data['name'])) {
    Api::error('Procedure name is required.');
    exit;
}

// 4. Check for Duplicate Procedure Name
$exists = $db->queryOne("SELECT id FROM procedures WHERE name = ?", [$data['name']]);
if ($exists) {
    Api::error('A procedure with this name already exists.');
    exit;
}

// 5. Insert into Database
try {
    $id = $db->insert('procedures', $data);
    
    // Construct the response object for the JS frontend
    $newProcedure = [
        'id'          => $id,
        'name'        => $data['name'],
        'description' => $data['description'],
        'status'      => $data['status']
    ];
    
    Api::success($newProcedure, 'Procedure created successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}