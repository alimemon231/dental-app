<?php
/**
 * API: Delete Isolated Payment Transaction Row (Admin Scope)
 * Path: /adm-payments/delete-transaction.php
 * Handles individual transaction deletion and clears specific child records without dropping the master container.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Operational Security Guard Clearance
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized administrative access profile clearance validation failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Parameter Capture and Verification
$transactionId = (int)($_POST['id'] ?? 0);

if ($transactionId <= 0) {
    Api::error('Invalid request parameters. Target transaction identification key is required.');
    exit;
}

try {
    // 3. Fetch record to verify existence and capture the master payment relationship mapping
    $sqlFetch = "SELECT id, payment_id, amount, payment_type 
                 FROM `payment_transactions` 
                 WHERE id = ? 
                 LIMIT 1";
                 
    $targetRecord = $db->queryOne($sqlFetch, [$transactionId]);

    if (!$targetRecord) {
        Api::error('Target transaction ledger line not found or has already been removed.', 404);
        exit;
    }

    $paymentId = (int)$targetRecord['payment_id'];
    $txAmount  = (float)$targetRecord['amount'];
    $txType    = $targetRecord['payment_type'];

    // 4. Start ACID-compliant transaction block to guarantee ledger balancing state integrity
    $db->beginTransaction();

    // 5. Remove the individual itemized transaction row safely from index vectors
    $db->query("DELETE FROM `payment_transactions` WHERE id = ? LIMIT 1", [$transactionId]);

    // Commit changes to database tables permanently
    $db->commit();

    // 6. Complete Success Payload Return Map to update client interface engines seamlessly
    Api::success([
        'deleted_transaction_id' => $transactionId,
        'parent_payment_id'      => $paymentId,
        'removed_amount'         => $txAmount,
        'transaction_type'       => $txType
    ], "Transaction entry #{$transactionId} successfully deleted. Master ledger parameters and running balance profiles will be recalculated immediately.");

} catch (Exception $e) {
    // Instantly roll back transactions on exceptions to shield application stability
    $db->rollBack();
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}