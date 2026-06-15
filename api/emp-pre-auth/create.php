<?php
/**
 * API: Create Pre-Auth Case & Rows
 * Path: /emp-pre-auth/create.php
 * Handles multi-row itemized relational structure inputs with case meta matching the new schema.
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
$p_insurance     = (int)($_POST['p_insurance_plan'] ?? 0);
$providerId      = (int)($_POST['provider'] ?? $_POST['doctor_id'] ?? 0); // Captures provider/doctor context from payload
$treatment_types = $_POST['treatment_type'] ?? []; // Captured as an Array stack
$tooth_numbers   = $_POST['tooth_numbers'] ?? [];   // Captured as an Array stack

// 3. Robust Array Input Validations
if ($patientId <= 0 || $p_insurance <= 0 || empty($treatment_types) || empty($tooth_numbers)) {
    Api::error('Patient selection, insurance plan, and at least one procedure item are required.');
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

try {
    // Start Transaction to guarantee atomic mapping consistency across both new tables
    $db->beginTransaction();

    // 7. Insert metadata row into the primary `pre_auth_cases` table
    $caseData = [
        'patient_id' => $patientId,
        'doctor_id'  => $providerId,
        'office_id'  => $sessionOfficeId
    ];

    $caseId = $db->insert('pre_auth_cases', $caseData);

    if (!$caseId) {
        throw new Exception('Failed to generate master Pre-Auth Case container tracking key.');
    }

    // 8. Process Child Loop to unroll rows directly into individual itemized `pre-auth` records
    $itemizedEmailRows = "";
    $currentTimeStamp  = date('Y-m-d H:i:s');
    
    foreach ($treatment_types as $index => $procedureId) {
        $procId  = (int)$procedureId;
        $toothNo = (int)$tooth_numbers[$index];

        if ($procId <= 0) {
            continue; // Skip any empty validation rows safely
        }

        // Prepare the individual child row matching your exact `pre-auth` schema mapping requirements
        $individualPreAuthRow = [
            'case_id'              => $caseId,
            'procedure_id'         => $procId,
            'teeth_number'         => $toothNo,
            'p_insurance_plan'     => $p_insurance,
            'appointment_date'     => null, // Set during scheduling phase
            'created_at'           => $currentTimeStamp,
            'created_by'           => (int)$currentUserId,
            'approved_by'          => 0,    // Default unassigned validator flag
            'approval_expire_date' => null, // Evaluated during approval workflows
            'status'               => 'Requested',
            'edited_by'            => 0,
            'edit_time'            => $currentTimeStamp,
            'notes'                => ''    // Initial notes state
        ];

        // Insert directly as an isolated row to allow granular separate approvals down the line
        $db->insert('pre-auth', $individualPreAuthRow);

        // Optional text log resolution for email payload
        $procInfo = $db->queryOne("SELECT name FROM procedures WHERE id = ? LIMIT 1", [$procId]);
        $procName = $procInfo ? $procInfo['name'] : "Proc ID: #{$procId}";
        
        $itemizedEmailRows .= "  - Tooth {$toothNo}: {$procName}\r\n";
    }

    // Commit all operations safely to storage layers
    $db->commit();

    // 9. Build and Dispatch Email Notifications Packet
    $emailBody = "New Itemized Case Pre-Authorization Matrix Created\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Case ID Ref Mapping:   #" . $caseId . "\r\n";
    $emailBody .= "Office Scope:          " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:     " . $patientName . " (ID: #" . $patientId . ")\r\n";
    $emailBody .= "Date of Birth:         " . $patientDob . "\r\n";
    $emailBody .= "Insurance Code Map:    " . $p_insurance . "\r\n";
    $emailBody .= "Initial Case Status:   Sent (Pending Splitting Reviews)\r\n";
    $emailBody .= "Submitted By Staff:    " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

   EmailSender::send('Ourayfax@gmail.com', "New Case Pre-Auth: {$patientName} ({$officeName})", $emailBody);

    // 10. Complete Operation Status Success Payload Return
    Api::success(['case_id' => $caseId], 'Pre-Auth case metrics and itemized tracking rows split and created successfully.');

} catch (Exception $e) {
    // Instantly roll back nested changes on exceptions to maintain absolute reference integrity
   
        $db->rollBack();
    
    Api::error('Database transactional operational failure: ' . $e->getMessage(), 500);
}