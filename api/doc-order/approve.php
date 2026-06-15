<?php
/**
 * API: Approve Supply Order & Dispatch Itemized Confirmation Email
 * Path: /api/orders/approve.php
 */
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

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
$order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$orderId]);

if (!$order) {
    Api::error('Order not found.', 404);
    exit;
}

// 4. Safety Pin: Only allow approval if status is 'pending'
if ($order['status'] !== 'pending') {
    Api::error('This order is already ' . $order['status'] . ' and cannot be approved again.', 400);
    exit;
}

$currentUserId = $_SESSION['user_id'];

try {
    $db->beginTransaction();

    // 5. Update Order Status
    $updateData = [
        'status'      => 'approved',
        'approved_by' => $currentUserId, // Doctor/Staff who clicked the button
    ];

    $db->update('orders', $updateData, ['id' => $orderId]);

    // 6. Gather Itemized Child Breakdown Lines Joining the 'items' Table for Item Code
    $itemsSql = "SELECT oi.price, oi.quantity, oi.item_id, i.name, i.item_code 
                 FROM order_items oi
                 LEFT JOIN items i ON oi.item_id = i.id
                 WHERE oi.order_id = ?";
    $orderItems = $db->query($itemsSql, [$orderId]);

    $itemizedEmailRows = "";
    $totalOrderCost = 0;

    foreach ($orderItems as $item) {
        $itemName     = !empty($item['name']) ? $item['name'] : "Item ID: #{$item['item_id']}";
        $itemCode     = !empty($item['item_code']) ? $item['item_code'] : 'N/A';
        $itemSubtotal = (float)$item['price'] * (int)$item['quantity'];
        $totalOrderCost += $itemSubtotal;

        // Included item_code directly in the breakdown lines matching your requested email structure
        $itemizedEmailRows .= "  - [Code: {$itemCode}] {$itemName} (Qty: {$item['quantity']} @ \${$item['price']}) - Total: \${$itemSubtotal}\r\n";
    }

    // 7. Fetch Office Profile Location Context 
    $officeId = $order['office_id'];
    $officeRow = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$officeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    // 8. Fetch User Profile Context for the Approving Party
    $approver = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $approverName = $approver ? $approver['name'] : 'Unknown User';

    // 9. Fetch User Profile Context for the Original Submitting Party
    $creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$order['created_by']]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    $db->commit();

    // 10. Build and Dispatch Email Notifications Packet (Identical layout to create.php)
    $emailBody = "Supply Order Approved Notification\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Order ID Ref Mapping:   #" . $orderId . "\r\n";
    $emailBody .= "Office Scope:           " . $officeName . "\r\n";
    $emailBody .= "Original Order Date:    " . $order['order_date'] . "\r\n";
    $emailBody .= "Expected Reception:     " . $order['expected_received_date'] . "\r\n";
    $emailBody .= "Updated Order Status:   Approved\r\n";
    $emailBody .= "Submitted By Staff:     " . $creatorName . "\r\n";
    $emailBody .= "Approved By Operator:   " . $approverName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Supplies Breakdown:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Total Order Value:      $" . number_format($totalOrderCost, 2) . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";

    // Dispatches using the exactly matched syntax rule from create.php
    EmailSender::send('ouraysupplies@gmail.com', "Order Approved: #{$orderId} ({$officeName})", $emailBody);

    Api::success(null, 'Order #' . $orderId . ' has been approved successfully and notifications dispatched.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Approval Failed: ' . $e->getMessage());
}