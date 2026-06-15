<?php
/**
 * API: Cascade Purge Master Payment Record & Child Transactions (Admin Scope)
 * Path: /adm-payments/delete.php
 * Handles atomic deletion of a master billing liability entry along with all linked payment/refund ledger lines.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Administrative Workspace Access Clearance Guard
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized administrative access profile clearance validation failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Target Parameter Identity
$paymentId = (int)($_POST['id'] ?? 0);

if ($paymentId <= 0) {
    Api::error('Invalid request parameters. Target Master Payment Record ID identifier is required.');
    exit;
}

try {
    // 3. Verify Master Invoice Existence (Broad scope execution omitting session location lockdowns)
    $sqlFetch = "SELECT id, total_amount FROM `payments` WHERE id = ? LIMIT 1";
    $masterInvoice = $db->queryOne($sqlFetch, [$paymentId]);

    if (!$masterInvoice) {
        Api::error('Target master financial payment record not found or has already been dropped.', 404);
        exit;
    }

    // 4. Calculate Sibling Transaction Footprint for Detailed Auditing Response Message Metrics
    $sqlCount = "SELECT COUNT(*) as tx_count FROM `payment_transactions` WHERE payment_id = ?";
    $txResult = $db->queryOne($sqlCount, [$paymentId]);
    $deletedTransactionsCount = (int)($txResult['tx_count'] ?? 0);

    // 5. Initiate ACID-Compliant Transaction Block to guarantee structural referential integrity
    $db->beginTransaction();

    // A. Wipe all linked financial logs from child ledger vector mapping layouts (`payment_transactions`)
    if ($deletedTransactionsCount > 0) {
        $db->query("DELETE FROM `payment_transactions` WHERE payment_id = ?", [$paymentId]);
    }

    // B. Wipe the parent invoice ledger container record cleanly (`payments`)
    $db->query("DELETE FROM `payments` WHERE id = ? LIMIT 1", [$paymentId]);

    // Commit both table modifications concurrently to the database index clusters permanently
    $db->commit();

    // 6. Build contextual status report summary based on the complexity of the deletion scope
    $messageAppendix = $deletedTransactionsCount > 0 
        ? " Cascaded and cleared {$deletedTransactionsCount} associated transaction history rows."
        : " Master payment container shell cleanly dropped (no child ledger lines were present).";

    // 7. Complete Success Payload Return Map to update client interface engines seamlessly
    Api::success([
        'purged_master_id'     => $paymentId,
        'transactions_cleared' => $deletedTransactionsCount
    ], "Master liability payment account #{$paymentId} successfully expunged." . $messageAppendix);

} catch (Exception $e) {
    // Instantly restore previous state settings on operational exceptions to protect relational state tracks
    $db->rollBack();
    Api::error('Database transactional operation failure: ' . $e->getMessage(), 500);
}