<?php
/**
 * POST api/lab-cases/create.php
 * Create a new Lab Case Type (e.g. Crown, Bridge) with target (Teeth/Arch) and pricing details.
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
$name   = trim($_POST['name'] ?? '');
$target = trim($_POST['target'] ?? ''); // 'teeth' or 'arch'
$price  = isset($_POST['price']) ? (float)$_POST['price'] : 0.00; // Captures and casts front-end price field

// Basic Validation
if (empty($name)) {
    Api::error('Case name is required.');
    exit;
}

if (empty($target) || !in_array($target, ['teeth', 'arch'])) {
    Api::error('A valid target area (Teeth or Arch) must be selected.');
    exit;
}

if ($price < 0) {
    Api::error('Price cannot be a negative value.');
    exit;
}

// 4. Check for Duplicate Case Name
// Prevents duplicate entries like "PFM Crown"
$exists = $db->queryOne("SELECT id FROM case_type WHERE name = ?", [$name]);
if ($exists) {
    Api::error('A lab case with this name already exists.');
    exit;
}

// 5. Insert into Database
try {
    $data = [
        'name'   => $name,
        'target' => $target,
        'price'  => $price,  // Saved to matching column lock key
        'status' => 'active' // Default status on creation
    ];

    // Insert into 'case_type' table
    $id = $db->insert('case_type', $data);
    
    if (!$id) {
        throw new Exception("Failed to save record to database.");
    }

    // Construct response for lab-cases.js
    $newCase = [
        'id'     => $id,
        'name'   => $name,
        'target' => $target,
        'price'  => (float)$price, // Return precision safe type back to layout UI components
        'status' => 'active'
    ];
    
    Api::success($newCase, 'Lab case type created successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}