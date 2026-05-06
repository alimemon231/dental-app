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
$currentUserId = $_SESSION['user_id'];
$itemId        = (int) ($_POST['item_id'] ?? 0);
$quantity      = (int) ($_POST['quantity'] ?? 1);

if (!$itemId || $quantity <= 0) {
    Api::error('Invalid item or quantity.');
    exit;
}

try {
    // 3. Check if the item already exists in this user's cart
    $existing = $db->queryOne(
        "SELECT id, quantity FROM cart WHERE user_id = ? AND item_id = ? LIMIT 1",
        [$currentUserId, $itemId]
    );

    if ($existing) {
        // 4. Update existing quantity
        $newQty = $existing['quantity'] + $quantity;
        $updated = $db->update('cart', ['quantity' => $newQty], ['id' => $existing['id']]);
        
        if (!$updated) {
            throw new Exception("Failed to update cart quantity.");
        }
        
        Api::success(['cart_id' => $existing['id']], 'Cart updated successfully.');
    } else {
        // 5. Insert new record into cart table
        $cartData = [
            'user_id'  => $currentUserId,
            'item_id'  => $itemId,
            'quantity' => $quantity
        ];

        $cartId = $db->insert('cart', $cartData);

        if (!$cartId) {
            throw new Exception("Failed to add item to cart.");
        }

        Api::success(['cart_id' => $cartId], 'Item added to cart.');
    }

} catch (Exception $e) {
    Api::error('Server Error: ' . $e->getMessage());
}