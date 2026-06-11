<?php
/**
 * POST api/emp-payments/update-payment-shell.php
 * Synchronizes parent payment ledger parameters matching fields exactly with create.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Establish Operational Role Clearances
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Only billing staff and doctors can process payments.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Establish Secure Session Context Parameters
$paymentId       = (int)($_POST['payment_id'] ?? 0);
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$currentUserId   = (int)($_SESSION['user_id'] ?? 0);

if ($paymentId <= 0) {
    Api::error('A valid Master Payment Record ID reference is required for modifications.');
    exit;
}

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session. Cannot bind payment to a facility.', 400);
    exit;
}

// 3. Verify Original Existence and Cross-Office Access Security Limits
$currentPayment = $db->queryOne(
    "SELECT id FROM `payments` WHERE id = ? AND office_id = ? LIMIT 1", 
    [$paymentId, $sessionOfficeId]
);

if (!$currentPayment) {
    Api::error('Target master payment profile not found or workspace location mismatch.', 404);
    exit;
}

// 4. Capture and Sanitize Payload Fields (Identical naming conventions to create.php)
$patientId        = (int)($_POST['patient_id'] ?? 0);
$providerId       = (int)($_POST['provider_id'] ?? 0);
$treatmentIdsJson = trim($_POST['treatment_ids'] ?? ''); // Arrives as stringified JSON from frontend array
$totalAmount      = (float)($_POST['total_amount'] ?? 0.00);
$paymentDate      = trim($_POST['payment_date'] ?? '');

$currentDateTime   = date('Y-m-d H:i:s');

// 5. Robust Data Validation
if ($patientId <= 0 || $providerId <= 0) {
    Api::error('Patient selection and Provider assignment are strictly required.');
    exit;
}

if (empty($treatmentIdsJson) || $treatmentIdsJson === '[]') {
    Api::error('At least one treatment classification must be attached to the ledger.');
    exit;
}

if ($totalAmount <= 0) {
    Api::error('Total treatment liability cost must be greater than zero.');
    exit;
}

if (empty($paymentDate)) {
    Api::error('Statement date is strictly required.');
    exit;
}

// 6. JSON Transformation Logic (Matching create.php exactly)
$treatmentString = "";
$decodedTreatments = json_decode($treatmentIdsJson, true);

if (is_array($decodedTreatments)) {
    $ids = array_map(function($item) {
        return (int)$item['id'];
    }, $decodedTreatments);
    
    // Formats into exactly a "6" or "6,12,85" string configuration
    $treatmentString = implode(',', $ids);
} else {
    Api::error('Structural processing failure: Invalid treatment payload data format.');
    exit;
}

try {
    // 7. Start Transaction to guarantee atomic integrity across master table updates
    $db->beginTransaction();

    // 8. Structure and Execute the Master Invoice Database Update Payload
    $masterUpdatePayload = [
        'patient_id'    => $patientId,
        'provider_id'   => $providerId,      // Correct column identifier key used here
        'treatment_ids' => $treatmentString, // Saved cleanly as flat comma-separated values 
        'total_amount'  => $totalAmount,
        'payment_date'  => $paymentDate,
        'edited_by'     => $currentUserId,   // Matched explicit auditing schemas 
        'edited_at'     => $currentDateTime  // Matched explicit auditing schemas
    ];

    $db->update('payments', $masterUpdatePayload, ['id' => $paymentId]);

    // 9. Commit table transformations permanently to database
    $db->commit();
    
    Api::success(null, 'Payment ledger adjustments and transaction alignments saved successfully.');

} catch (Exception $e) {
    // Instantly roll back changes on operational exceptions
    $db->rollBack();
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}