<?php
/**
 * API: Fetch Isolated Transaction Parameters for Modification Mapping
 * Path: /emp-payments/get-transaction.php?id=XX
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Validation and Parameter Capture
$transactionId = (int)($_GET['id'] ?? 0);
if (!$transactionId) {
    Api::error('Transaction record parameter ID is required.');
    exit;
}

try {
    // 2. Fetch targeted single transaction row alongside operational audit user metrics
    $sql = "SELECT 
                pt.*,
                u_creator.name AS creator_name,
                u_editor.name AS editor_name
            FROM `payment_transactions` pt
            LEFT JOIN `users` u_creator ON pt.created_by = u_creator.user_id
            LEFT JOIN `users` u_editor ON pt.edited_by = u_editor.user_id
            WHERE pt.id = ? 
            LIMIT 1";

    $transaction = $db->queryOne($sql, [$transactionId]);

    if (!$transaction) {
        Api::error('Specified transaction record log not found.', 404);
        exit;
    }

    // 3. Format payload datatypes cleanly for the incoming Javascript Form mapper
    $response = [
        'id'                => (int)$transaction['id'],
        'payment_id'        => (int)$transaction['payment_id'],
        'amount'            => (float)$transaction['amount'],
        'payment_type'      => $transaction['payment_type'],
        'payment_method'    => $transaction['payment_method'],
        'transaction_date'  => $transaction['transaction_date'],
        'transaction_notes' => $transaction['transaction_notes'] ?: '',
        'created_by'        => (int)$transaction['created_by'],
        'edited_by'         => $transaction['edited_by'] ? (int)$transaction['edited_by'] : null,
        'edited_at'         => $transaction['edited_at'] ?: '',
        'creator_name'      => $transaction['creator_name'] ?: 'System',
        'editor_name'       => $transaction['editor_name'] ?: '—'
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database lookup failed on transaction item: ' . $e->getMessage(), 500);
}