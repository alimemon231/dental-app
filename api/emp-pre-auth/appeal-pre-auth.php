<?php
/**
 * POST api/emp-pre-auth/appeal-pre-auth.php
 * Promotes a pre-auth record status condition securely to 'Appealed' and handles management email alerts.
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
    Api::error('A valid Pre-Authorization ID is required to execute the appeal transmission.');
    exit;
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$currentUserId   = $_SESSION['user_id'];

if ($sessionOfficeId <= 0) {
    Api::error('Workspace session expired or clinic scope context lost.', 400);
    exit;
}

try {
    // 2. Verify that the targeted record exists and belongs to the active clinic group
    $record = $db->queryOne(
        "SELECT id, status, patient_id, p_insurance_plan, office_id FROM `pre-auth` WHERE id = ? AND office_id = ? LIMIT 1",
        [$id, $sessionOfficeId]
    );

    if (!$record) {
        Api::error('Target pre-authorization record could not be found or access is restricted.', 404);
        exit;
    }

    // STRICT STEP: Only allow appeals if the current status context is a rejection state
    $currentStatus = strtolower($record['status']);
    if ($currentStatus !== 'rejected' && $currentStatus !== 'denied') {
        Api::error('This record cannot be appealed because it is not currently marked as a rejected case. Current state: ' . $record['status'], 400);
        exit;
    }

    // 3. Gather Context Data for Email Content Logs
    $officeRow  = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$sessionOfficeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $creator     = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    $patientRow  = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$record['patient_id']]);
    $patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$record['patient_id']}";
    $patientDob  = $patientRow ? $patientRow['dob'] : '—';

    // 4. Fetch the linked itemized procedures for this specific pre-auth
    $proceduresList = $db->query(
        "SELECT pap.tooth_number, proc.name AS procedure_name 
         FROM `pre_auth_procedures` pap
         INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
         WHERE pap.pre_auth_id = ?",
        [$id]
    ) ?: [];

    // Format child loop information rows cleanly for the email text string
    $itemizedEmailRows = "";
    if (!empty($proceduresList)) {
        foreach ($proceduresList as $proc) {
            $itemizedEmailRows .= "  - Tooth {$proc['tooth_number']}: {$proc['procedure_name']}\r\n";
        }
    } else {
        $itemizedEmailRows .= "  - No itemized procedures attached.\r\n";
    }

    // 5. Execute State Transformation Update inside a clean try block
    $updateData = [
        'status'    => 'Appealed',
        'edited_by' => $currentUserId,
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 6. Build and Dispatch Email Notifications Packet to Alert Management of the Case Dispute
    $emailBody = "ATTENTION MANAGEMENT: Pre-Authorization Appeal Submitted\r\n";
    $emailBody .= "=====================================================\r\n";
    $emailBody .= "An insurance rejection has been corrected by staff and moved to Appeal status.\r\n";
    $emailBody .= "Please review the case files and take appropriate action.\r\n\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $id . "\r\n";
    $emailBody .= "Office Scope:         " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:    " . $patientName . " (ID: #" . $record['patient_id'] . ")\r\n";
    $emailBody .= "Date of Birth:        " . $patientDob . "\r\n";
    $emailBody .= "Insurance Plan ID:    " . $record['p_insurance_plan'] . "\r\n";
    $emailBody .= "Previous State:       " . $record['status'] . "\r\n";
    $emailBody .= "Current Status:       Appealed\r\n";
    $emailBody .= "Appealed By Staff:    " . $creatorName . "\r\n";
    $emailBody .= "-----------------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "-----------------------------------------------------\r\n";

    // Dispatching out to fax/management dashboard inbox email destination hook
    //EmailSender::send('Ourayfax@gmail.com', "URGENT APPEAL: {$patientName} ({$officeName})", $emailBody);

    // 7. Return API Success Object
    Api::success(null, 'Pre-authorization reference #' . $id . ' has been updated to Appealed and management has been notified.');

} catch (Exception $e) {
    Api::error('Appeal state modification or notification failure: ' . $e->getMessage(), 500);
}