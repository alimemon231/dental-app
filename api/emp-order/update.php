<?php
/**
 * POST api/orders/update.php
 * Updates a pending order while checking that the modified total does not cross the monthly budget.
 */
require_once __DIR__ . '/../../includes/Auth.php';

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

// 1. Capture Data
$orderId      = (int)($_POST['order_id'] ?? 0);
$orderDate    = trim($_POST['o_date'] ?? '');
$expectedDate = trim($_POST['r_date'] ?? '');
$items        = $_POST['items'] ?? [];

// 2. Validation
if (!$orderId) { Api::error('Order ID is required.'); exit; }
if (empty($orderDate) || empty($expectedDate)) { Api::error('Dates are required.'); exit; }
if (empty($items) || !is_array($items)) { Api::error('Item list cannot be empty.'); exit; }

// 3. Verify Order Existence, Office Scope, and Status Context
$existingOrder = $db->queryOne("SELECT office_id, status FROM orders WHERE id = ?", [$orderId]);

if (!$existingOrder) {
    Api::error('Order not found.', 404);
    exit;
}

if ($existingOrder['status'] !== 'pending') {
    Api::error('Cannot edit orders that are already ' . $existingOrder['status'] . '.', 400);
    exit;
}

$officeId = (int)$existingOrder['office_id'];

// 4. Compute Total Cost of the Modified Cart Request
$incomingOrderTotal = 0.0;
foreach ($items as $item) {
    $incomingOrderTotal += (float)$item['price'] * (int)$item['qty'];
}

// 5. Extract Timeframe Parameters from the Order to Check the Right Budget Month
$orderTime = strtotime($orderDate);
$budgetYear = (int)date('Y', $orderTime);
$budgetMonth = (int)date('m', $orderTime);

// 6. Validate Monthly Budget Assignment Limits
$budgetRow = $db->queryOne(
    "SELECT budget_amount FROM monthly_budget WHERE office_id = ? AND budget_year = ? AND budget_month = ? LIMIT 1",
    [$officeId, $budgetYear, $budgetMonth]
);

if (!$budgetRow) {
    Api::error("No budget assigned for this order's month. Update rejected.", 400);
    exit;
}

$allowedBudget = (float)$budgetRow['budget_amount'];

try {
    $db->beginTransaction();

    // 7. Calculate Historical Monthly Spending EXCLUDING this specific order
    $historicalSpentRow = $db->queryOne(
        "SELECT SUM(oi.price * oi.quantity) as total_spent 
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.id
         WHERE o.office_id = ? 
           AND o.id != ? 
           AND YEAR(o.order_date) = ? 
           AND MONTH(o.order_date) = ? 
           AND o.activation = 'active'
           AND o.status IN ('approved', 'pending')",
        [$officeId, $orderId, $budgetYear, $budgetMonth]
    );

    $amountAlreadySpent = (float)($historicalSpentRow['total_spent'] ?? 0);
    $projectedTotalExpenses = $amountAlreadySpent + $incomingOrderTotal;

    // 8. Enforce Financial Budget Boundaries
    if ($projectedTotalExpenses > $allowedBudget) {
        $remainingBudget = $allowedBudget - $amountAlreadySpent;
        if ($remainingBudget < 0) { $remainingBudget = 0; }
        
        throw new Exception(sprintf(
            "Budget exceeded. Modified items total $%s, but you only have $%s remaining of your $%s monthly allowance.",
            number_format($incomingOrderTotal, 2),
            number_format($remainingBudget, 2),
            number_format($allowedBudget, 2)
        ));
    }

    // 9. Update Order Header Layout Row Data
    $orderData = [
        'order_date'             => $orderDate,
        'expected_received_date' => $expectedDate,
    ];
    $db->update('orders', $orderData, ['id' => $orderId]);

    // 10. Re-insert Itemized Items Matrix Breakdown Structure
    $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);

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
    Api::success(null, 'Order modified and budget validated successfully.');

} catch (Exception $e) {
    $db->rollBack();
    Api::error('Update Failed: ' . $e->getMessage());
}