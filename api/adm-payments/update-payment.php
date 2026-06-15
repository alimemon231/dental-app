<?php
/**
 * POST api/adm-payments/update-payment-shell.php
 * Synchronizes parent payment ledger parameters matching fields exactly with administrative create.php parameters.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Establish Operational Role Clearances
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access. Only administrative accounts or authorized billing staff can modify payments.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Establish Secure Session Context Parameters
$paymentId     = (int)($_POST['payment_id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($paymentId <= 0) {
    Api::error('A valid Master Payment Record ID reference is required for modifications.');
    exit;
}

// 3. Verify Original Existence (Broad admin clearance checking without strict session office lockdowns)
$currentPayment = $db->queryOne(
    "SELECT id FROM `payments` WHERE id = ? LIMIT 1", 
    [$paymentId]
);

if (!$currentPayment) {
    Api::error('Target master payment profile not found.', 404);
    exit;
}

// 4. Capture and Sanitize Payload Fields (Including dynamic Office modifications from frontend)
$officeId       = (int)($_POST['office_id'] ?? 0); // Added: Office ID is now safely parsed directly from the frontend request parameter matrix
$patientId      = (int)($_POST['patient_id'] ?? 0);
$providerId     = (int)($_POST['provider_id'] ?? 0);
$treatmentsText = trim($_POST['treatments'] ?? ''); // Captures raw text description directly from frontend payload
$totalAmount    = (float)($_POST['total_amount'] ?? 0.00);
$paymentDate    = trim($_POST['payment_date'] ?? '');

$currentDateTime = date('Y-m-d H:i:s');

// 5. Robust Data Validation
if ($officeId <= 0) {
    Api::error('Facility allocation context missing. An explicit Office location assignment is strictly required.');
    exit;
}

if ($patientId <= 0 || $providerId <= 0) {
    Api::error('Patient selection and Provider assignment are strictly required.');
    exit;
}

if ($treatmentsText === '') {
    Api::error('Please input custom treatments description text for the customer bill.');
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

try {
    // 6. Start Transaction to guarantee atomic integrity across master table updates
    $db->beginTransaction();

    // 7. Structure and Execute the Master Invoice Database Update Payload (Now containing structural office migration data)
    $masterUpdatePayload = [
        'office_id'    => $officeId,       // Added: Updates the clinic node bound to this master billing container
        'patient_id'   => $patientId,
        'provider_id'  => $providerId,     // Correct column identifier key used here
        'treatments'   => $treatmentsText, // Saved cleanly as custom itemized literal billing text notes
        'total_amount' => $totalAmount,
        'payment_date' => $paymentDate,
        'edited_by'    => $currentUserId,  // Matched explicit auditing schemas 
        'edited_at'    => $currentDateTime // Matched explicit auditing schemas
    ];

    $db->update('payments', $masterUpdatePayload, ['id' => $paymentId]);

    // 8. Commit table transformations permanently to database
    $db->commit();
    
    Api::success(null, 'Payment ledger adjustments and office transaction alignments saved successfully.');

} catch (Exception $e) {
    // Instantly roll back changes on operational exceptions
    $db->rollBack();
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}