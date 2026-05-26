<?php
/**
 * API: Create Pre-Auth (with Dynamic Metrics & Backdating Capability)
 * Path: /emp-pre-auth/create.php
 * Handles multi-row itemized relational structures from precise form data payloads.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce role permission protection layers 
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Actor Context Session Constants
$currentUserId = $_SESSION['user_id'] ?? 0;
if ($currentUserId <= 0) {
    Api::error('User session context not found.', 401);
    exit;
}

// 2. Parse Incoming Payload Parameters
// Works whether submitted via standard POST headers or a raw JSON payload string
$patientId       = (int)($_POST['patient_id'] ?? 0);
$officeId        = (int)($_POST['office_id'] ?? 0);
$pInsurancePlan  = (int)($_POST['p_insurance_plan'] ?? 0);
$status          = trim($_POST['status'] ?? 'Create');

// Capture dates supplied by the form layout to allow backdated entry historical synchronization
$formCreatedAt   = trim($_POST['created_at'] ?? '');
$appointmentDate = trim($_POST['appointment_date'] ?? '');

// Capture relational itemized multi-row array matrices
$procedures      = $_POST['procedures'] ?? []; 

// 3. Robust Matrix Validations
if ($patientId <= 0 || $officeId <= 0 || $pInsurancePlan <= 0 || empty($procedures)) {
    Api::error('Patient, Office, Insurance Plan, and at least one procedure row item are required.');
    exit;
}

// 4. Format and Prepare Date Inputs Structurally
$finalCreatedAt = !empty($formCreatedAt) ? date('Y-m-d H:i:s', strtotime($formCreatedAt)) : date('Y-m-d H:i:s');
$finalApptDate  = !empty($appointmentDate) ? date('Y-m-d', strtotime($appointmentDate)) : null;

// 5. Prepare Master Parent Database Array Payload Matching Your Table Layout Schema
$preAuthData = [
    'patient_id'       => $patientId,
    'p_insurance_plan' => $pInsurancePlan,
    'appointment_date' => $finalApptDate,
    'office_id'        => $officeId,       // Overridden directly by incoming parameter selection layout
    'created_at'       => $finalCreatedAt,  // Enables backdating support records seamlessly
    'created_by'       => $currentUserId,
    'approved_by'       => $currentUserId,
    'status'           => $status,          // Saved explicitly ("Completed", "Scheduled", etc.)
    'notes'            => trim($_POST['notes'] ?? '')
];

try {
    // Start Transaction to guarantee absolute database reference atomic consistency 
    $db->beginTransaction();

    // 6. Insert master record layout targeting parent table `pre-auth`
    $preAuthId = $db->insert('pre-auth', $preAuthData);

    if (!$preAuthId) {
        throw new Exception('Failed to generate base Pre-Auth row identifier.');
    }

    // 7. Process Child Procedure Nested Items Matrix Loop
    foreach ($procedures as $item) {
        $procId  = (int)($item['procedure_id'] ?? 0);
        $toothNo = trim($item['tooth_number'] ?? '');

        if ($procId <= 0) {
            continue; // Safely drop invalid structural indices or rows
        }

        // Structural itemization rows targeted towards relational mapping arrays
        $db->insert('pre_auth_procedures', [
            'pre_auth_id'  => $preAuthId,
            'procedure_id' => $procId,
            'tooth_number' => $toothNo
        ]);
    }

    // Commit tracking records permanently to database engine storage
    $db->commit();

    // 8. Return Success Operational Transactional Envelope Output Structure
    Api::success([
        'pre_auth_id' => $preAuthId
    ], 'Pre-Auth historical record matrix synchronized successfully.');

} catch (Exception $e) {
    // Instantly roll back tracking mutations to safeguard alignment balance state
    
        $db->rollBack();
    
    Api::error('Database transactional workflow runtime execution exception: ' . $e->getMessage(), 500);
}