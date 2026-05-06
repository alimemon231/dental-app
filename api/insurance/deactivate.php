<?php
/**
 * POST api/insurance/deactivate.php
 * Soft-deactivation logic for insurance providers.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

// 2. Method Validation
if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

// 3. Collect ID
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    Api::error('Insurance ID is required.');
    exit;
}

// 4. Verify Existence in 'insurance' table
if (!$db->exists('insurance', ['id' => $id])) {
    Api::error('Insurance provider not found.', 404);
    exit;
}

try {
    // 5. Perform "Soft Delete" by updating status to deactive
    // This preserves the record for historical claims/pre-auths
    $db->update('insurance', ['status' => 'deactive'], ['id' => $id]);
    
    Api::success(null, 'Insurance provider deactivated successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}