<?php
/**
 * POST api/emp-pre-auth/send-pre-auth.php
 * Promotes an individual procedure row status to 'Sent' and dispatches case email alerts.
 */
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Validate Target Parameter
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    Api::error('A valid Pre-Authorization ID is required to execute transmission.');
    exit;
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$currentUserId   = $_SESSION['user_id'];

if ($sessionOfficeId <= 0) {
    Api::error('Workspace session expired or clinic scope context lost.', 400);
    exit;
}

try {
    // 2. Verify that the targeted row exists and matches session context parameters
    $record = $db->queryOne(
        "SELECT * FROM `pre-auth` WHERE id = ? LIMIT 1",
        [$id]
    );

    if (!$record) {
        Api::error('Target pre-authorization record could not be found or access is restricted.', 404);
        exit;
    }

    $caseId = (int)$record['case_id'];

    // Double check authorization safety context via parent case profile lookup
    $caseMetadata = $db->queryOne(
        "SELECT id, patient_id, office_id FROM `pre_auth_cases` WHERE id = ? AND office_id = ? LIMIT 1",
        [$caseId, $sessionOfficeId]
    );

    if (!$caseMetadata) {
        Api::error('Pre-Auth record context lost or access denied across clinic branches.', 403);
        exit;
    }

    // Allow states matching 'requested', 'create', or 'created' variations flexibly
    $normalizedStatus = strtolower(trim($record['status']));
    if ($normalizedStatus !== 'create' && $normalizedStatus !== 'created' && $normalizedStatus !== 'requested') {
        Api::error('This record cannot be marked as sent because its current state is: ' . $record['status'], 400);
        exit;
    }

    // 3. Gather Context Data for Email Content Logs
    $officeRow  = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$sessionOfficeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $creator     = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    // FIX: Grab patient details via the caseMetadata ID array key instead of itemized record array key
    $patientRow  = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$caseMetadata['patient_id']]);
    $patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$caseMetadata['patient_id']}";
    $patientDob  = $patientRow ? $patientRow['dob'] : '—';

    // 4. Fetch the target row's details using your schema format
    $proceduresList = $db->query(
        "SELECT pa.teeth_number AS tooth_number, proc.name AS procedure_name 
         FROM `pre-auth` pa
         INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
         WHERE pa.id = ? 
         LIMIT 1",
        [$id]
    ) ?: [];

    // Format target procedure details loop cleanly for the text string
    $itemizedEmailRows = "";
    if (!empty($proceduresList)) {
        foreach ($proceduresList as $proc) {
            $itemizedEmailRows .= "  - Tooth " . ($proc['tooth_number'] ?: '—') . ": {$proc['procedure_name']}\r\n";
        }
    } else {
        $itemizedEmailRows .= "  - No itemized procedures attached.\r\n";
    }

    // 5. Execute State Transformation Update
    $updateData = [
        'status'    => 'Sent',
        'edited_by' => $currentUserId,
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 6. Build and Dispatch Email Notifications Packet
    $emailBody = "Pre-Authorization Dispatched / Marked as Sent\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $id . "\r\n";
    $emailBody .= "Parent Case ID:        #" . $caseId . "\r\n";
    $emailBody .= "Office Scope:          " . $officeName . "\r\n";
    // FIX: Map text block references from caseMetadata configuration data maps
    $emailBody .= "Patient Directory:    " . $patientName . " (ID: #" . $caseMetadata['patient_id'] . ")\r\n";
    $emailBody .= "Date of Birth:        " . $patientDob . "\r\n";
    $emailBody .= "Insurance Plan ID:    " . $record['p_insurance_plan'] . "\r\n";
    $emailBody .= "Status Condition:     Sent\r\n";
    $emailBody .= "Dispatched By Staff:  " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

    // Dispatching out to fax dashboard inbox email destination hook
   EmailSender::send('Ourayfax@gmail.com', "Dispatched Pre-Auth: {$patientName} ({$officeName})", $emailBody);

    // 7. Return API Success Object
    Api::success(null, 'Pre-authorization reference #' . $id . ' has been updated to Sent and metrics dispatched.');

} catch (Exception $e) {
    Api::error('Transmission state modification or notification failure: ' . $e->getMessage(), 500);
}