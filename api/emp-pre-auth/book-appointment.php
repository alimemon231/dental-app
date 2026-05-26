<?php
/**
 * API: Book Appointment
 * Path: /api/emp-pre-auth/book-appointment.php
 * Promotes an approved pre-auth record status condition securely to 'Scheduled' and validates expiry rules.
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

// 1. Validate Target Parameters
$id              = (int)($_POST['id'] ?? 0);
$appointmentDate = trim($_POST['appointment_date'] ?? '');

if ($id <= 0 || empty($appointmentDate)) {
    Api::error('A valid Pre-Authorization ID and Appointment Date are required to execute scheduling.');
    exit;
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
$currentUserId   = $_SESSION['user_id'];

if ($sessionOfficeId <= 0) {
    Api::error('Workspace session expired or clinic scope context lost.', 400);
    exit;
}

try {
    // 2. Verify that the targeted record exists, belongs to the active clinic scope, and contains approval details
    $record = $db->queryOne(
        "SELECT id, status, patient_id, p_insurance_plan, office_id, approval_expire_date 
         FROM `pre-auth` 
         WHERE id = ? AND office_id = ? LIMIT 1",
        [$id, $sessionOfficeId]
    );

    if (!$record) {
        Api::error('Target pre-authorization record could not be found or access is restricted.', 404);
        exit;
    }

    if (strtolower($record['status']) !== 'approved') {
        Api::error('Only Approved requests can be scheduled. Current status is: ' . $record['status'], 400);
        exit;
    }

    // 3. Perform Insurance Expiry Validation Checks against requested Date
    if (!empty($record['approval_expire_date'])) {
        $expiryTs = strtotime($record['approval_expire_date']);
        $bookingTs = strtotime($appointmentDate);

        if ($bookingTs > $expiryTs) {
            $formattedExpiry = date('M d, Y', $expiryTs);
            Api::error("Cannot schedule appointment. The insurance approval expires on {$formattedExpiry}. Please book an appointment before the approval expires.", 400);
            exit;
        }
    }

    // 4. Gather Context Data for Audit Records and Email Content Logs
    $officeRow  = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$sessionOfficeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $creator     = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    $patientRow  = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$record['patient_id']]);
    $patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$record['patient_id']}";
    $patientDob  = $patientRow ? $patientRow['dob'] : '—';

    // 5. Fetch linked itemized procedures for this specific pre-auth
    $proceduresList = $db->query(
        "SELECT pap.tooth_number, proc.name AS procedure_name 
         FROM `pre_auth_procedures` pap
         INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
         WHERE pap.pre_auth_id = ?",
        [$id]
    ) ?: [];

    // Format itemized dynamic loops row rows cleanly for the text document block
    $itemizedEmailRows = "";
    if (!empty($proceduresList)) {
        foreach ($proceduresList as $proc) {
            $itemizedEmailRows .= "  - Tooth {$proc['tooth_number']}: {$proc['procedure_name']}\r\n";
        }
    } else {
        $itemizedEmailRows .= "  - No itemized procedures attached.\r\n";
    }

    // 6. Execute State Transformation Updates with tracking timestamps
    $updateData = [
        'status'           => 'Scheduled',
        'appointment_date' => $appointmentDate,
        'edited_by'        => $currentUserId,
        'edit_time'        => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 7. Build and Dispatch Email Notifications Packet
    $subject = "Appointment Scheduled: {$patientName} ({$officeName})";

    $emailBody = "Patient Appointment Scheduled\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $id . "\r\n";
    $emailBody .= "Office Scope:         " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:    " . $patientName . " (ID: #" . $record['patient_id'] . ")\r\n";
    $emailBody .= "Date of Birth:        " . $patientDob . "\r\n";
    $emailBody .= "Insurance Plan ID:    " . $record['p_insurance_plan'] . "\r\n";
    $emailBody .= "Appointment Date:     " . date('M d, Y', strtotime($appointmentDate)) . "\r\n";
    $emailBody .= "Approval Expiry Date: " . ($record['approval_expire_date'] ? date('M d, Y', strtotime($record['approval_expire_date'])) : 'N/A') . "\r\n";
    $emailBody .= "Scheduled By Staff:   " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Itemized Treatments Matrix:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

    // Dispatching out to fax dashboard inbox email destination hook
    // EmailSender::send('Ourayfax@gmail.com', $subject, $emailBody);

    // 8. Return standard API Success Object
    Api::success(null, 'Pre-authorization reference #' . $id . ' has been updated to Scheduled and appointment set.');

} catch (Exception $e) {
    Api::error('Scheduling state modification or notification failure: ' . $e->getMessage(), 500);
}