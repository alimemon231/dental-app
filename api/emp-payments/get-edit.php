<?php
/**
 * API: Fetch Isolated Main Payment Parameters (No Child Transactions)
 * Path: /emp-payments/get-edit.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Operational Security Guard Clearance
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized administrative access profile clearance validation failure.', 403);
    exit;
}

if (Api::method() !== 'GET') {
    Api::error('Method not allowed.', 405);
    exit;
}

// 2. Parameter Capture and Verification
$paymentId       = (int)($_GET['id'] ?? 0);
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($paymentId <= 0) {
    Api::error('A valid Master Payment Record ID key identifier is required.');
    exit;
}

try {
    // 3. Collect Isolated Master Liability Details
    $sql = "SELECT 
                p.id,
                p.patient_id,
                p.provider_id,
                p.total_amount,
                p.treatment_ids,
                p.payment_date,
                p.status,
                pat.name AS patient_name,
                doc.name AS provider_name
            FROM `payments` p
            INNER JOIN `patient` pat ON p.patient_id = pat.id
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            WHERE p.id = ? AND p.office_id = ? LIMIT 1";

    $paymentData = $db->queryOne($sql, [$paymentId, $sessionOfficeId]);

    if (!$paymentData) {
        Api::error('Target master financial payment record not found or scope cross-office access denied.', 404);
        exit;
    }

    // Initialize unified treatments target array container
    $paymentData['treatment'] = [];
    $rawIds = trim($paymentData['treatment_ids'] ?? '');

    if (!empty($rawIds)) {
        // Scenario A: Check if it's already a valid JSON structure
        $decoded = json_decode($rawIds, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // It's a structured JSON object/array; pass it straight through
            $paymentData['treatments'] = $decoded;
        } else {
            // Scenario B: Handle legacy setups (single ID raw integers or comma-separated list values)
            // Break down commas if multiple values are stored as a CSV string fallback
            $individualIds = array_filter(array_map('intval', explode(',', $rawIds)));

            if (!empty($individualIds)) {
                // Generate parameterized placeholders safely (?, ?, ?) for the array length
                $placeholders = implode(',', array_fill(0, count($individualIds), '?'));
                
                $treatmentSql = "SELECT id, name FROM `procedures` WHERE id IN ($placeholders)";
                $treatmentsList = $db->query($treatmentSql, $individualIds);
                
                if ($treatmentsList) {
                    foreach ($treatmentsList as $treatment) {
                        $paymentData['treatments'][] = [
                            'id' => (string)$treatment['id'],
                            'name' => $treatment['name']
                        ];
                    }
                }
            }
        }
    }

    // 4. Return Data Package Array
    Api::success($paymentData, 'Master record data parsed successfully.');

} catch (Exception $e) {
    Api::error('Database lookup matrix processing failure error: ' . $e->getMessage(), 500);
}