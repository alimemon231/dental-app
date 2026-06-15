<?php
/**
 * API: Fetch Full Payment Ledger Details
 * Path: /emp-payments/get.php?id=XX
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$paymentId = (int) ($_GET['id'] ?? 0);
if (!$paymentId) {
    Api::error('Payment ID parameter is required.');
    exit;
}

try {
    // 1. Fetch Master Payment Record (including the text-based 'treatments' column)
    $sql = "SELECT 
                p.*,
                pat.name AS patient_name,
                doc.name AS provider_name,
                u_creator.name AS creator_name,
                u_editor.name AS editor_name
            FROM `payments` p
            INNER JOIN `patient` pat ON p.patient_id = pat.id
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            LEFT JOIN `users` u_creator ON p.created_by = u_creator.user_id
            LEFT JOIN `users` u_editor ON p.edited_by = u_editor.user_id
            WHERE p.id = ? 
            LIMIT 1";

    $payment = $db->queryOne($sql, [$paymentId]);

    if (!$payment) {
        Api::error('Payment record not found.', 404);
        exit;
    }

    // 2. Fetch all transactions for this payment with User joins
    $transSql = "SELECT 
                    pt.*,
                    u_creator.name AS creator_name,
                    u_editor.name AS editor_name
                 FROM `payment_transactions` pt
                 LEFT JOIN `users` u_creator ON pt.created_by = u_creator.user_id
                 LEFT JOIN `users` u_editor ON pt.edited_by = u_editor.user_id
                 WHERE pt.payment_id = ? 
                 ORDER BY pt.transaction_date DESC";
    $transactions = $db->query($transSql, [$paymentId]);

    // 3. Calculate Aggregates
    $totalPaid = 0;
    $totalRefunded = 0;
    foreach ($transactions as $t) {
        if ($t['payment_type'] === 'Payment') {
            $totalPaid += (float) $t['amount'];
        }
        if ($t['payment_type'] === 'Refund') {
            $totalRefunded += (float) $t['amount'];
        }
    }

    $netPaid = $totalPaid - $totalRefunded;
    $balanceDue = (float) $payment['total_amount'] - $netPaid;

    // 4. Construct Response
    $response = [
        'id'             => (int) $payment['id'],
        'patient_name'   => $payment['patient_name'],
        'provider_name'  => $payment['provider_name'] ?? 'Unassigned',
        'treatments'     => $payment['treatments'] ?? '', // Custom description text
        'treatment_names'=> $payment['treatments'] ?? '', // Kept for backwards rendering support in UI modals
        'total_amount'   => (float) $payment['total_amount'],
        'total_paid'     => $netPaid,
        'balance_due'    => $balanceDue,
        'status'         => $payment['status'],
        'created_at'     => $payment['payment_date'],
        'edit_time'      => $payment['edited_at'],
        'creator_name'   => $payment['creator_name'] ?? 'System',
        'editor_name'    => $payment['editor_name'] ?? '—',
        // Attach transaction history for the modal view
        'transactions'   => $transactions
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Failed to retrieve payment details: ' . $e->getMessage(), 500);
}