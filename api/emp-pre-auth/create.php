<?php
/**
 * API: Create Pre-Auth
 * Path: /emp-pre-auth/create.php
 * Handles multi-row itemized relational structure inputs.
 */

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Establish Session and Context Parameters
$currentUserId   = $_SESSION['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if (!$currentUserId) {
    Api::error('User session context not found.', 401);
    exit;
}

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session. Cannot create pre-auth.', 400);
    exit;
}

// 2. Capture Relational Fields & Dynamic Arrays Matrix Components
$patientId       = (int)($_POST['patient_id'] ?? 0);
$p_insurance     = trim($_POST['p_insurance_plan'] ?? '');
$treatment_types = $_POST['treatment_type'] ?? []; // Captured as an Array stack
$tooth_numbers   = $_POST['tooth_numbers'] ?? [];   // Captured as an Array stack

// 3. Robust Array Input Validations
if ($patientId <= 0 || empty($p_insurance) || empty($treatment_types) || empty($tooth_numbers)) {
    Api::error('Patient selection, insurance, and at least one procedure item are required.');
    exit;
}

if (count($treatment_types) !== count($tooth_numbers)) {
    Api::error('Structural payload mismatch detected. Procedures data matrix is uneven.');
    exit;
}

// 4. Fetch the Office Name for Email logs safely
$officeRow = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$sessionOfficeId]);
$officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

// 5. Get the Creator User Name
$creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
$creatorName = $creator ? $creator['name'] : 'Unknown User';

// 6. Pull Patient Primary Details to keep legacy email logging intact
$patientRow = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$patientId]);
$patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$patientId}";
$patientDob  = $patientRow ? $patientRow['dob'] : '—';

// 7. Prepare Master Parent Database Array Payload
$preAuthData = [
    'patient_id'       => $patientId,
    'p_insurance_plan' => $p_insurance,
    'office_id'        => $sessionOfficeId, // Tied strictly to current active workspace selection
    'created_by'       => $currentUserId,
    'created_at'       => date('Y-m-d H:i:s'),
    'status'           => 'Create'
];

try {
    // Start Transaction to guarantee atomic mapping consistency across both tables
    $db->beginTransaction();

    // 8. Insert master record into parent table `pre-auth`
    $preAuthId = $db->insert('pre-auth', $preAuthData);

    if (!$preAuthId) {
        throw new Exception('Failed to generate base Pre-Auth row identifier.');
    }

    // 9. Process Child Loop to write dynamic list array indices cleanly
    $itemizedEmailRows = "";
    
    foreach ($treatment_types as $index => $procedureId) {
        $procId  = (int)$procedureId;
        $toothNo = trim($tooth_numbers[$index]);

        if ($procId <= 0 || $toothNo === '') {
            continue; // Skip any unintended validation slips safely
        }

        // Structural insertion execution targeting the linked procedure table
        $db->insert('pre_auth_procedures', [
            'pre_auth_id'  => $preAuthId,
            'procedure_id' => $procId,
            'tooth_number' => $toothNo
        ]);

        // Optional: Fetch string names for transparent notifications text layouts
        $procInfo = $db->queryOne("SELECT name FROM procedures WHERE id = ? LIMIT 1", [$procId]);
        $procName = $procInfo ? $procInfo['name'] : "Proc ID: #{$procId}";
        
        $itemizedEmailRows .= "  - Tooth {$toothNo}: {$procName}\r\n";
    }

    // Commit changes safely to storage
    $db->commit();

    // 10. Build and Dispatch Email Notifications Packet
    $emailBody = "New Multi-Procedure Pre-Authorization Created\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $preAuthId . "\r\n";
    $emailBody .= "Office Scope:         " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:    " . $patientName . " (ID: #" . $patientId . ")\r\n";
    $emailBody .= "Date of Birth:        " . $patientDob . "\r\n";
    $emailBody .= "Insurance Code Map:   " . $p_insurance . "\r\n";
    $emailBody .= "Status Condition:     Sent\r\n";
    $emailBody .= "Submitted By Staff:   " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

    // EmailSender::send('Ourayfax@gmail.com', "New Pre-Auth: {$patientName} ({$officeName})", $emailBody);

    // 11. Complete Operation Status Success Payload Return
    Api::success(['pre_auth_id' => $preAuthId], 'Pre-Auth itemized procedure records created and logged successfully.');

} catch (Exception $e) {
    // Instantly roll back nested changes on exceptions to maintain reference alignment safely
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}