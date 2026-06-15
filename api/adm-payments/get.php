<?php
/**
 * API: Fetch Full Payment Ledger Details (Admin Scope)
 * Path: /adm-payments/get.php?id=XX
 * Aggregates and sends full payment payload including custom treatment descriptions, dynamic transactions history, and clinic identification tags.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Ensure user has administrative or authorized clinical/billing workspace permissions
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

$paymentId = (int) ($_GET['id'] ?? 0);
if (!$paymentId) {
    Api::error('Payment ID parameter is required.');
    exit;
}

try {
    // 1. Fetch Master Payment Record (including treatments text and left joined office metadata)
    $sql = "SELECT 
                p.*,
                pat.name AS patient_name,
                doc.name AS provider_name,
                off.office_name AS office_name,
                u_creator.name AS creator_name,
                u_editor.name AS editor_name
            FROM `payments` p
            INNER JOIN `patient` pat ON p.patient_id = pat.id
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            LEFT JOIN `offices` off ON p.office_id = off.id
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

    // 4. Construct Response Payload Map
    $response = [
        'id'             => (int) $payment['id'],
        'patient_name'   => $payment['patient_name'],
        'provider_name'  => $payment['provider_name'] ?? 'Unassigned',
        'office_name'    => $payment['office_name'] ?? ('Office #' . $payment['office_id']), // Added for client interface elements mapping
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
        // Attach transaction ledger rows array
        'transactions'   => $transactions
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Failed to retrieve payment details: ' . $e->getMessage(), 500);
}