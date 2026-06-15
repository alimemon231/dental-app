<?php
/**
 * POST /admin-order/update.php
 * Administrative script to update order headers, change office assignments,
 * override statuses, and synchronize order line items via a delete-and-reinsert pattern.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Require authentication and ensure user is an administrator or management role
$auth->requireAuth();
if (!$auth->hasRole('admin') && !$auth->hasRole('management')) {
    Api::error('Unauthorized administrative access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture Form Payload Parameters
$orderId      = (int)($_POST['order_id'] ?? 0);
$officeId     = (int)($_POST['office_id'] ?? 0);
$orderDate    = trim($_POST['o_date'] ?? '');
$expectedDate = trim($_POST['r_date'] ?? '');
$status       = trim($_POST['status'] ?? '');
$items        = $_POST['items'] ?? [];

// 2. Base Validation Checks
if (!$orderId) { 
    Api::error('Target Order ID parameter is required.'); 
    exit; 
}
if (!$officeId) { 
    Api::error('Target Office Source parameter is required.'); 
    exit; 
}
if (empty($orderDate) || empty($expectedDate)) { 
    Api::error('Order and Expected delivery dates are required.'); 
    exit; 
}
if (empty($items)) { 
    Api::error('Item transaction array list cannot be empty.'); 
    exit; 
}

// 3. Verify Order Existence
$existingOrder = $db->queryOne("SELECT id, status FROM orders WHERE id = ?", [$orderId]);
if (!$existingOrder) {
    Api::error('Order record could not be found.', 404);
    exit;
}

try {
    $db->beginTransaction();

    // 4. Update Order Header Parameters (Including status and office mutations)
    $orderData = [
        'office_id'              => $officeId,
        'order_date'             => $orderDate,
        'expected_received_date' => $expectedDate
    ];

    // If the form passes a specific status string, apply it to the data array
    if (!empty($status)) {
        $orderData['status'] = strtolower($status);
        
        // Optional automation: If an admin marks it approved, bind their user ID as the approver
        if (strtolower($status) === 'approved') {
            $orderData['approved_by'] = $_SESSION['user_id'];
        }
    }
    
    $db->update('orders', $orderData, ['id' => $orderId]);

    // 5. Sync Order Items Matrix (Delete and Re-insert Pattern)
    // Purge old relational item rows
    $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);

    // Re-insert modern list payload entries from frontend state array
    foreach ($items as $item) {
        $itemData = [
            'order_id' => $orderId,
            'item_id'  => (int)$item['id'],
            'price'    => (float)$item['price'],
            'quantity' => (int)$item['qty']
        ];

        $insertedItem = $db->insert('order_items', $itemData);
        if (!$insertedItem) {
            throw new Exception("Failed to insert item relation row: " . ($item['name'] ?? 'ID '.$item['id']));
        }
    }

    $db->commit();
    Api::success(null, 'Administrative order parameters updated successfully.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Database Update Transaction Failed: ' . $e->getMessage());
}