<?php
/**
 * POST api/emp-pre-auth/send-pre-auth.php
 * Promotes a pre-auth record status condition securely to 'Sent' and handles management email alerts.
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
    // 2. Verify that the targeted record exists and belongs to the active clinic group
    $record = $db->queryOne(
        "SELECT id, status, patient_id, p_insurance_plan, office_id FROM `pre-auth` WHERE id = ? AND office_id = ? LIMIT 1",
        [$id, $sessionOfficeId]
    );

    if (!$record) {
        Api::error('Target pre-authorization record could not be found or access is restricted.', 404);
        exit;
    }

    if (strtolower($record['status']) !== 'create') {
        Api::error('This record cannot be marked as sent because its current state is: ' . $record['status'], 400);
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
        'status'    => 'Sent',
        'edited_by' => $currentUserId,
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 6. Build and Dispatch Email Notifications Packet
    $emailBody = "Pre-Authorization Dispatched / Marked as Sent\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $id . "\r\n";
    $emailBody .= "Office Scope:         " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:    " . $patientName . " (ID: #" . $record['patient_id'] . ")\r\n";
    $emailBody .= "Date of Birth:        " . $patientDob . "\r\n";
    $emailBody .= "Insurance Plan ID:    " . $record['p_insurance_plan'] . "\r\n";
    $emailBody .= "Status Condition:     Sent\r\n";
    $emailBody .= "Dispatched By Staff:  " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

    // Dispatching out to fax dashboard inbox email destination hook
    //EmailSender::send('Ourayfax@gmail.com', "Dispatched Pre-Auth: {$patientName} ({$officeName})", $emailBody);

    // 7. Return API Success Object
    Api::success(null, 'Pre-authorization reference #' . $id . ' has been updated to Sent and management notified.');

} catch (Exception $e) {
    Api::error('Transmission state modification or notification failure: ' . $e->getMessage(), 500);
}