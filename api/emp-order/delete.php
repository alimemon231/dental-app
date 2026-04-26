<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization: Only Staff/Doctors (or Admin) can delete
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

// 2. Capture and Validate ID
$orderId = (int)($_POST['id'] ?? 0);
if (!$orderId) { 
    Api::error('Order ID is required.'); 
    exit; 
}

// 3. Status Check: Fetch current status and existence
$order = $db->queryOne("SELECT status FROM orders WHERE id = ?", [$orderId]);

if (!$order) {
    Api::error('Order not found.', 404);
    exit;
}

// 4. Critical Guard: Only allow deletion if 'pending'
if ($order['status'] !== 'pending') {
    Api::error('Cannot delete orders that are already ' . $order['status'] . '.', 400);
    exit;
}

try {
    $db->beginTransaction();

    // 5. Delete Child Records First (Order Items)
    // This maintains database integrity if you don't have ON DELETE CASCADE
    $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);

    // 6. Delete Parent Record (Order Header)
    $db->query("DELETE FROM orders WHERE id = ?", [$orderId]);

    $db->commit();
    Api::success(null, 'Order #' . $orderId . ' has been deleted.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Delete Failed: ' . $e->getMessage());
}