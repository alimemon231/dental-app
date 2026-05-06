<?php
/**
 * POST api/orders/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$currentUserId = $_SESSION['user_id'];
$orderDate     = trim($_POST['o_date'] ?? '');
$expectedDate  = trim($_POST['r_date'] ?? '');
$items         = $_POST['items'] ?? []; 

if (empty($orderDate) || empty($expectedDate)) {
    Api::error('Order and Reception dates are required.');
    exit;
}

if (empty($items) || !is_array($items)) {
    Api::error('At least one item is required to create an order.');
    exit;
}

$officeRow = $db->queryOne(
    "SELECT office_id FROM office_users WHERE user_id = ? LIMIT 1",
    [$currentUserId]
);

if (!$officeRow) {
    Api::error('You are not assigned to an office. Cannot create order.', 400);
    exit;
}
$officeId = $officeRow['office_id'];

try {
    $db->beginTransaction();

    // 1. Insert into `orders` table
    $orderData = [
        'office_id'              => $officeId,
        'order_date'             => $orderDate,
        'expected_received_date' => $expectedDate,
        'created_by'             => $currentUserId,
        'approved_by'            => null,
        'status'                 => 'pending',
        'activation'             => 'active'
    ];

    $orderId = $db->insert('orders', $orderData);

    if (!$orderId) {
        throw new Exception("Failed to create order header.");
    }

    // 2. Insert into `order_items` table
    foreach ($items as $item) {
        $itemData = [
            'order_id' => $orderId,
            'item_id'  => $item['id'],
            'price'    => $item['price'],
            'quantity' => $item['qty']
        ];

        $insertedItem = $db->insert('order_items', $itemData);
        if (!$insertedItem) {
            throw new Exception("Failed to insert item ID: " . $item['id']);
        }
    }

    /**
     * 3. NEW: CLEAR CART AFTER ORDER PLACEMENT
     * This removes all items belonging to the current user from the cart table.
     */
    $clearCart = $db->query("DELETE FROM cart WHERE user_id = ?", [$currentUserId]);
    
    // We don't strictly throw an exception if the cart was already empty, 
    // but the logic ensures a fresh start for the user's next session.

    $db->commit();
    Api::success(['order_id' => $orderId], 'Order created successfully and cart cleared.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Server Error: ' . $e->getMessage());
}