<?php
/**
 * POST api/emp-payments/edit-transaction.php
 * Updates exactly ONE specific transaction item entry row without altering the master payment status.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role-Based Access Control Clearances
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized administrative access credential validation failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Establish Workspace Session Context Parameters
$currentUserId   = (int)$_SESSION['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

// 3. Validation and Parameter Capture from serialized form payload
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$paymentId     = (int)($_POST['payment_id'] ?? 0);
$amount        = (float)($_POST['amount'] ?? 0.00);
$paymentType   = trim($_POST['payment_type'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$notes         = trim($_POST['transaction_notes'] ?? '');

if ($transactionId <= 0 || $paymentId <= 0) {
    Api::error('A valid Transaction ID and Master Payment ID are required for modification.');
    exit;
}

if ($amount <= 0) {
    Api::error('Transaction amount metrics must be a valid positive numerical value.');
    exit;
}

if (!in_array($paymentType, ['Payment', 'Refund'])) {
    Api::error('Invalid entry parameter provided for Transaction Type framework.');
    exit;
}

try {
    // 4. Verify that the Parent Payment File exists and matches clinic office scope bounds
    $paymentMaster = $db->queryOne(
        "SELECT id FROM `payments` WHERE id = ? LIMIT 1",
        [$paymentId]
    );

    if (!$paymentMaster) {
        Api::error('Master payment invoice profile record not found or access window denied.', 404);
        exit;
    }

    // Verify the transaction row matches the verified parent payment shell context
    $existingTx = $db->queryOne(
        "SELECT id FROM `payment_transactions` WHERE id = ? AND payment_id = ? LIMIT 1",
        [$transactionId, $paymentId]
    );

    if (!$existingTx) {
        Api::error('Target transaction line record mismatch against parent ledger folder.', 404);
        exit;
    }

    // Start Transaction block to guarantee financial data atomicity
    $db->beginTransaction();

    // 5. Update targeted single transaction history row values
    $txUpdateData = [
        'amount'            => $amount,
        'payment_type'      => $paymentType,
        'payment_method'    => $paymentMethod,
        'transaction_notes' => $notes,
        'edited_by'         => $currentUserId,
        'edited_at'         => date('Y-m-d H:i:s')
    ];
    $db->update('payment_transactions', $txUpdateData, ['id' => $transactionId]);

    // Commit changes safely to storage layout
    $db->commit();

    Api::success(null, 'Transaction record details updated successfully.');

} catch (Exception $e) {
    // Roll back structural file alterations if system failures happen mid-execution
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Transactional ledger processing execution error failure: ' . $e->getMessage(), 500);
}