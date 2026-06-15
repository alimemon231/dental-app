<?php
/**
 * POST /admin-order/delete.php
 * Administrative script to strictly purge an order and its cascaded item dependencies 
 * from the database, bypassing status state restrictions.
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

// 1. Capture and Validate ID
$orderId = (int)($_POST['id'] ?? 0);
if (!$orderId) { 
    Api::error('Target Order ID parameter is required.'); 
    exit; 
}

// 2. Existence Check
$order = $db->queryOne("SELECT id FROM orders WHERE id = ?", [$orderId]);
if (!$order) {
    Api::error('Order record could not be found.', 404);
    exit;
}

try {
    $db->beginTransaction();

    // 3. Delete Child Records First (Order Items)
    // Eliminates foreign key dependency constraints if ON DELETE CASCADE is absent
    $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);

    // 4. Delete Parent Record (Order Header)
    $db->query("DELETE FROM orders WHERE id = ?", [$orderId]);

    $db->commit();
    Api::success(null, 'Order #' . $orderId . ' and its items have been successfully purged.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Delete Operation Failed: ' . $e->getMessage());
}