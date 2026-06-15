<?php
/**
 * POST api/cart/update.php
 * Updates the quantity of a specific persistent item row directly inside the cart table.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check (Consistent with your cart & order patterns)
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Payload Data
$currentUserId = $_SESSION['user_id'];
$cartId        = (int) ($_POST['id'] ?? 0);
$quantity      = (int) ($_POST['quantity'] ?? 0);

if ($cartId <= 0) {
    Api::error('A valid Cart Record ID is required.');
    exit;
}

if ($quantity <= 0) {
    Api::error('Quantity must be a valid positive non-zero numerical integer.');
    exit;
}

try {
    // 3. Verify ownership: Ensure the cart line entry exists and belongs to the active logged-in user
    $existing = $db->queryOne(
        "SELECT id FROM cart WHERE id = ? AND user_id = ? LIMIT 1",
        [$cartId, $currentUserId]
    );

    if (!$existing) {
        Api::error('Target cart line record mismatch or access window denied.', 404);
        exit;
    }

    // 4. Update the exact item quantity row
    $updated = $db->update(
        'cart', 
        ['quantity' => $quantity], 
        ['id' => $cartId]
    );
    
    if (!$updated) {
        throw new Exception("Failed to execute item count modification changes down table structures.");
    }
    
    Api::success(['id' => $cartId, 'quantity' => $quantity], 'Cart quantity updated successfully.');

} catch (Exception $e) {
    Api::error('Server Error: ' . $e->getMessage(), 500);
}