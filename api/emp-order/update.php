<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

// 1. Capture Data
$orderId      = (int)($_POST['order_id'] ?? 0);
$orderDate    = trim($_POST['o_date'] ?? '');
$expectedDate = trim($_POST['r_date'] ?? '');
$items        = $_POST['items'] ?? [];

// 2. Validation
if (!$orderId) { Api::error('Order ID is required.'); exit; }
if (empty($orderDate) || empty($expectedDate)) { Api::error('Dates are required.'); exit; }
if (empty($items)) { Api::error('Item list cannot be empty.'); exit; }

// 3. Check Current Status
$existingOrder = $db->queryOne("SELECT status FROM orders WHERE id = ?", [$orderId]);

if (!$existingOrder) {
    Api::error('Order not found.', 404);
    exit;
}

if ($existingOrder['status'] !== 'pending') {
    Api::error('Cannot edit orders that are already ' . $existingOrder['status'] . '.', 400);
    exit;
}

try {
    $db->beginTransaction();

    // 4. Update Order Header
    $orderData = [
        'order_date'             => $orderDate,
        'expected_received_date' => $expectedDate,
        // We keep created_by the same, but you could add an 'updated_at' here
    ];
    
    $db->update('orders', $orderData, ['id' => $orderId]);

    // 5. Update Order Items (The "Delete and Re-insert" Pattern)
    // First, remove all old items for this order
    $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);

    // Now, insert the new list from the frontend
    foreach ($items as $item) {
        $itemData = [
            'order_id' => $orderId,
            'item_id'  => $item['id'],
            'price'    => $item['price'],
            'quantity' => $item['qty']
        ];

        $insertedItem = $db->insert('order_items', $itemData);
        if (!$insertedItem) {
            throw new Exception("Failed to update item: " . ($item['name'] ?? 'ID '.$item['id']));
        }
    }

    $db->commit();
    Api::success(null, 'Order updated successfully.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Update Failed: ' . $e->getMessage());
}