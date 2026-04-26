<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Check (Only Doctors/Staff)
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate ID
$orderId = (int)($_POST['id'] ?? 0);
if (!$orderId) {
    Api::error('Order ID is required.');
    exit;
}

// 3. Find the Order and check current status
$order = $db->queryOne("SELECT status FROM orders WHERE id = ?", [$orderId]);

if (!$order) {
    Api::error('Order not found.', 404);
    exit;
}

// 4. Safety Pin: Only allow approval if status is 'pending'
if ($order['status'] !== 'pending') {
    Api::error('This order is already ' . $order['status'] . ' and cannot be approved again.', 400);
    exit;
}

try {
    // 5. Update Order Status
    $updateData = [
        'status'      => 'approved',
        'approved_by' => $_SESSION['user_id'], // Doctor/Staff who clicked the button
        // 'updated_at' => date('Y-m-d H:i:s') // Optional: if you have this column
    ];

    $result = $db->update('orders', $updateData, ['id' => $orderId]);

    if ($result) {
        Api::success(null, 'Order #' . $orderId . ' has been approved successfully.');
    } else {
        throw new Exception("Failed to update database.");
    }

} catch (Exception $e) {
    Api::error('Approval Failed: ' . $e->getMessage());
}