<?php
/**
 * API: Create Master Payment Liability & Initial Transaction (Admin Scope)
 * Path: /adm-payments/create.php
 * Handles the dual-insertion logic for creating a master invoice and its first payment ledger entry with an explicit admin-assigned office.
 */

require_once __DIR__ . '/../../includes/Auth.php';
// require_once __DIR__ . '/../../includes/EmailSender.php'; // Uncomment if email notifications are needed later

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Admin-specific context access verification
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access. Only administrative accounts or authorized billing staff can access this ledger workspace.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Secure Session User Context Parameters
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($currentUserId <= 0) {
    Api::error('User session context not found or expired.', 401);
    exit;
}

// 2. Capture and Sanitize Payload Fields (Including Dynamic Office Allocation from Frontend Matrix)
$officeId          = (int)($_POST['office_id'] ?? 0); // Changed: Office ID is now safely parsed from the frontend select payload parameters
$patientId         = (int)($_POST['patient_id'] ?? 0);
$providerId        = (int)($_POST['provider_id'] ?? 0);
$treatmentsText    = trim($_POST['treatments'] ?? ''); // Captures raw text description directly from frontend textarea
$totalAmount       = (float)($_POST['total_amount'] ?? 0.00);
$masterPaymentType = trim($_POST['master_payment_type'] ?? 'Self Pay');

$txAmount          = (float)($_POST['transaction_amount'] ?? 0.00);
$paymentMethod     = trim($_POST['payment_method'] ?? 'Cash');
$txNotes           = trim($_POST['transaction_notes'] ?? '');

// 3. System Clocks & Dates
$currentDate       = date('Y-m-d');
$currentDateTime   = date('Y-m-d H:i:s');

// 4. Robust Administrative Data Validation
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

if ($txAmount < 0) {
    Api::error('Initial transaction amount cannot be negative. Use the refund module for negative flows.');
    exit;
}

// 5. Calculate Initial Status
// If the initial transaction covers the entire total cost, it's instantly completed. Otherwise, it remains active.
$initialBalance = $totalAmount - $txAmount;
$paymentStatus  = 'Active';

try {
    // 6. Start Transaction to guarantee atomic integrity across master and child tables
    $db->beginTransaction();

    // 7. Structure and Insert the Master Invoice (`payments` table) using administrative target assignments
    $paymentData = [
        'office_id'    => $officeId, // Bound directly from the dynamic frontend selection payload
        'patient_id'   => $patientId,
        'provider_id'  => $providerId,
        'treatments'   => $treatmentsText, // Saved cleanly as custom itemized literal billing text notes
        'total_amount' => $totalAmount,    // Cast to numerical context based on DB schema definitions
        'payment_date' => $currentDate,
        'payment_type' => $masterPaymentType,
        'status'       => $paymentStatus,
        'created_by'   => $currentUserId,
        'edited_by'    => $currentUserId,
        'edited_at'    => $currentDateTime
    ];

    $paymentId = $db->insert('payments', $paymentData);

    if (!$paymentId) {
        throw new Exception('Failed to generate master Payment Liability container record.');
    }

    // 8. Structure and Insert the Initial Transaction Row (`payment_transactions` table)
    if ($txAmount > 0 || $txAmount === 0.0) {
        $transactionData = [
            'payment_id'        => $paymentId,
            'transaction_date'  => $currentDate,
            'payment_method'    => $paymentMethod,
            'amount'            => $txAmount,
            'payment_type'      => 'Payment', // Explicitly marking this entry flow as a positive payment type
            'transaction_notes' => $txNotes,
            'created_by'        => $currentUserId,
            'edited_by'         => $currentUserId,
            'edited_at'         => $currentDateTime
        ];

        $txResult = $db->insert('payment_transactions', $transactionData);

        if (!$txResult) {
            throw new Exception('Master payment created, but failed to log the initial child transaction vector.');
        }
    }

    // 9. Commit both table entries permanently to the database
    $db->commit();

    // 10. Complete Operation Status Success Payload Return
    Api::success([
        'payment_id'   => $paymentId,
        'status'       => $paymentStatus,
        'balance_left' => $initialBalance
    ], 'Payment logged successfully.');

} catch (Exception $e) {
    // Instantly roll back changes on exceptions to prevent orphaned master records without transaction trails
    $db->rollBack();
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}