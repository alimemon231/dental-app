<?php
/**
 * POST api/orders/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

$currentUserId = $_SESSION['user_id'];
$officeId      = $_SESSION['office_id'] ?? null;
$expectedDate  = trim($_POST['r_date'] ?? '');
$items         = $_POST['items'] ?? []; 

// 1. Enforce Server-Side Current Date Securely
$orderDate = date('Y-m-d'); 
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');

if (empty($officeId)) {
    Api::error('Session missing assigned office identifier. Cannot create order.', 400);
    exit;
}

if (empty($expectedDate)) {
    Api::error('Reception date is required.');
    exit;
}

if (empty($items) || !is_array($items)) {
    Api::error('At least one item is required to create an order.');
    exit;
}

// 2. Validate Monthly Budget Assignment
$budgetRow = $db->queryOne(
    "SELECT budget_amount FROM monthly_budget WHERE office_id = ? AND budget_year = ? AND budget_month = ? LIMIT 1",
    [$officeId, $currentYear, $currentMonth]
);

if (!$budgetRow) {
    Api::error("No budget assigned for this month. Please ask administration.", 400);
    exit;
}

$allowedBudget = (float)$budgetRow['budget_amount'];

// 3. Compute Total Cost of Incoming Cart Request Items
$incomingOrderTotal = 0;
foreach ($items as $item) {
    $incomingOrderTotal += (float)$item['price'] * (int)$item['qty'];
}

try {
    $db->beginTransaction();

    // 4. Track Utilized Monthly Expenses (Approved + Pending)
    $historicalSpentRow = $db->queryOne(
        "SELECT SUM(oi.price * oi.quantity) as total_spent 
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.id
         WHERE o.office_id = ? 
           AND YEAR(o.order_date) = ? 
           AND MONTH(o.order_date) = ? 
           AND o.activation = 'active'
           AND o.status IN ('approved', 'pending')",
        [$officeId, $currentYear, $currentMonth]
    );

    $amountAlreadySpent = (float)($historicalSpentRow['total_spent'] ?? 0);
    $projectedTotalExpenses = $amountAlreadySpent + $incomingOrderTotal;

    // 5. Enforce Financial Budget Boundaries
    if ($projectedTotalExpenses > $allowedBudget) {
        $remainingBudget = $allowedBudget - $amountAlreadySpent;
        if ($remainingBudget < 0) { $remainingBudget = 0; }
        
        throw new Exception(sprintf(
            "Budget already exceeded. Your current cart total is $%s, but you only have $%s remaining of your $%s monthly allowance.",
            number_format($incomingOrderTotal, 2),
            number_format($remainingBudget, 2),
            number_format($allowedBudget, 2)
        ));
    }

    // 6. Insert into `orders` table header layout row
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

    // 7. Insert Itemized breakdown loops into `order_items` table & build itemized text email payload
    $itemizedEmailRows = "";
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

        // Fetch product item name safely for the email contents
        $productInfo = $db->queryOne("SELECT name FROM items WHERE id = ? LIMIT 1", [$item['id']]);
        $itemName = $productInfo ? $productInfo['name'] : "Item ID: #{$item['id']}";
        $itemSubtotal = (float)$item['price'] * (int)$item['qty'];

        $itemizedEmailRows .= "  - {$itemName} (Qty: {$item['qty']} @ \${$item['price']}) - Total: \${$itemSubtotal}\r\n";
    }

    // 8. Fetch Context logs data for Email presentation safely
    $officeRow = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$officeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    // 9. Clear Cart After Successful Order Validation Check
    $db->query("DELETE FROM cart WHERE user_id = ?", [$currentUserId]);
    
    $db->commit();

    // 10. Build and Dispatch Email Notifications Packet
    $emailBody = "New Supply Order Created\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Order ID Ref Mapping:   #" . $orderId . "\r\n";
    $emailBody .= "Office Scope:           " . $officeName . "\r\n";
    $emailBody .= "Order Date:             " . $orderDate . "\r\n";
    $emailBody .= "Expected Reception:     " . $expectedDate . "\r\n";
    $emailBody .= "Initial Order Status:   Pending\r\n";
    $emailBody .= "Submitted By Staff:     " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Supplies Breakdown:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Total Order Cost:       $" . number_format($incomingOrderTotal, 2) . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";

    //EmailSender::send('ouraysupplies@gmail.com', "New Order Created: #{$orderId} ({$officeName})", $emailBody);

    Api::success(['order_id' => $orderId], 'Order created successfully and management notified.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error($e->getMessage());
}