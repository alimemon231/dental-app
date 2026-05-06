<?php
/**
 * POST api/cart/add-to-cart.php
 * Handles adding/updating items in the persistent cart table.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check (Consistent with your order pattern)
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Data

$id      = (int) ($_POST['id'] ?? 1);

if (!$id) {
    Api::error('Invalid id.');
    exit;
}

try {
   $db->delete("cart" , ['id' => $id]);
   Api::success(null , "Item Removed From Cart");

} catch (Exception $e) {
    Api::error('Server Error: ' . $e->getMessage());
}