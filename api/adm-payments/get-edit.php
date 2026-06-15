<?php
/**
 * API: Fetch Isolated Main Payment Parameters (No Child Transactions - Admin Scope)
 * Path: /adm-payments/get-edit.php
 * Collects isolated master liability details and clinical context parameters for global admin management.
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

if (Api::method() !== 'GET') {
    Api::error('Method not allowed.', 405);
    exit;
}

// 2. Parameter Capture and Verification
$paymentId = (int)($_GET['id'] ?? 0);

if ($paymentId <= 0) {
    Api::error('A valid Master Payment Record ID key identifier is required.');
    exit;
}

try {
    // 3. Collect Isolated Master Liability Details (Removing strict session office restriction for broad admin capability)
    $sql = "SELECT 
                p.id,
                p.office_id,
                p.patient_id,
                p.provider_id,
                p.total_amount,
                p.treatments,
                p.payment_date,
                p.status,
                pat.name AS patient_name,
                doc.name AS provider_name,
                off.office_name AS office_name
            FROM `payments` p
            INNER JOIN `patient` pat ON p.patient_id = pat.id
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            LEFT JOIN `offices` off ON p.office_id = off.id
            WHERE p.id = ? 
            LIMIT 1";

    $paymentData = $db->queryOne($sql, [$paymentId]);

    if (!$paymentData) {
        Api::error('Target master financial payment record not found.', 404);
        exit;
    }

    // 4. Backward Compatibility Mapping & Fallbacks
    // Pass the raw custom text directly under 'treatments' and 'treatment_names' to prevent UI rendering breakages
    $paymentData['office_name']     = $paymentData['office_name'] ?? ('Office #' . $paymentData['office_id']);
    $paymentData['treatments']      = $paymentData['treatments'] ?? '';
    $paymentData['treatment_names'] = $paymentData['treatments'] ?? '';

    // 5. Return Data Package Array
    Api::success($paymentData, 'Master record data parsed successfully.');

} catch (Exception $e) {
    Api::error('Database lookup matrix processing failure error: ' . $e->getMessage(), 500);
}