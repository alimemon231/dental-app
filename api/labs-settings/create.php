<?php
/**
 * POST api/adm-labs/create.php
 * Register a new Dental Lab Vendor.
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

// 3. Collect Data from Form
$name    = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$email   = trim($_POST['email'] ?? ''); // Included as it was in your JS/HTML

// 4. Validation
if (empty($name)) {
    Api::error('Lab name is required.');
    exit;
}

// 5. Duplicate Check
// Ensures you don't register the same lab twice
$exists = $db->queryOne("SELECT id FROM labs_patner WHERE name = ?", [$name]);
if ($exists) {
    Api::error('A lab with this name is already registered.');
    exit;
}

// 6. Insert into Database
try {
    $data = [
        'name'    => $name,
        'address' => $address,
        'phone'   => $phone,
        'email'   => $email,
        'status'  => 'active' // Default status for new records
    ];

    // Insert into 'labs_patner' table
    $id = $db->insert('labs_patner', $data);
    
    if (!$id) {
        throw new Exception("Could not save the lab record.");
    }

    // Construct response for your AJAX onSuccess
    $newLab = [
        'id'      => $id,
        'name'    => $name,
        'address' => $address,
        'phone'   => $phone,
        'email'   => $email,
        'status'  => 'active'
    ];
    
    Api::success($newLab, 'Lab registered successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}