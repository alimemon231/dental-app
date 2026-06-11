<?php
/**
 * POST api/emp-payments/add-transaction.php
 * Appends a pristine transaction entry row to an existing parent payment container ledger.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role-Based Access Control Clearances (Synced with admin/staff permissions rules)
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized administrative access credential validation failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Establish Workspace Session Context Parameters
$currentUserId   = (int)($_SESSION['user_id'] ?? 0);
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($currentUserId <= 0) {
    Api::error('User session context not found or expired.', 401);
    exit;
}

// 3. Validation and Parameter Capture from serialized form payload
$paymentId   = (int)($_POST['payment_id'] ?? 0);
$amount      = (float)($_POST['amount'] ?? 0.00);
$txDate      = trim($_POST['transaction_date'] ?? '');
$paymentType = trim($_POST['payment_type'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$notes       = trim($_POST['transaction_notes'] ?? '');

// Robust structural validation checks
if ($paymentId <= 0) {
    Api::error('A valid Master Payment ID linking parameter context is strictly required.');
    exit;
}

if ($amount < 0) {
    Api::error('Transaction amount metrics cannot be negative values.');
    exit;
}

if (empty($txDate)) {
    Api::error('A clean historical transaction date marker is required.');
    exit;
}

if (!in_array($paymentType, ['Payment', 'Refund'])) {
    Api::error('Invalid entry parameter provided for Transaction Type framework.');
    exit;
}

try {
    // 4. Verify that the Parent Payment File exists
    $paymentMaster = $db->queryOne(
        "SELECT id FROM `payments` WHERE id = ? LIMIT 1",
        [$paymentId]
    );

    if (!$paymentMaster) {
        Api::error('Master payment invoice profile record not found or access window denied.', 404);
        exit;
    }

    // Start Transaction block to guarantee financial database atomicity
    $db->beginTransaction();

    // 5. Structure and Insert the New Transaction Row (`payment_transactions`)
    $currentDateTime = date('Y-m-d H:i:s');
    
    $transactionData = [
        'payment_id'        => $paymentId,
        'transaction_date'  => $txDate, // Extracted directly from input payload mapping
        'payment_method'    => $paymentMethod,
        'amount'            => $amount,
        'payment_type'      => $paymentType,
        'transaction_notes' => $notes,
        'created_by'        => $currentUserId, // Set auditing identity footprint
        'edited_by'         => $currentUserId,
        'edited_at'         => $currentDateTime
    ];

    $newTxId = $db->insert('payment_transactions', $transactionData);

    if (!$newTxId) {
        throw new Exception('Failed to generate entry record down child transaction database tables.');
    }

    // Commit changes safely to ledger storage layout
    $db->commit();

    Api::success([
        'transaction_id' => $newTxId,
        'payment_id'     => $paymentId
    ], 'New transaction statement ledger entry appended successfully.');

} catch (Exception $e) {
    // Roll back structural changes if mid-execution loops fail
    $db->rollBack();
    Api::error('Transactional ledger processing execution error failure: ' . $e->getMessage(), 500);
}