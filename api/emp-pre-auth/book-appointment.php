<?php
/**
 * API: Book Appointment
 * Path: /api/emp-pre-auth/book-appointment.php
 * Promotes the explicitly targeted approved pre-auth item record line to 'Scheduled' and validates expiry rules.
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

// 1. Validate Target Parameters (Accepts the specific line-level pre_auth_id from the frontend)
$preAuthId       = (int)($_POST['id'] ?? 0);
$appointmentDate = trim($_POST['appointment_date'] ?? '');

if ($preAuthId <= 0 || empty($appointmentDate)) {
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
    // 2. Fetch only the specifically targeted procedure line item row details
    $targetLine = $db->queryOne(
        "SELECT pa.id, pa.case_id, pa.status, pa.p_insurance_plan, pa.approval_expire_date, pa.teeth_number,
                proc.name AS procedure_name
         FROM `pre-auth` pa
         INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
         WHERE pa.id = ? LIMIT 1",
        [$preAuthId]
    );

    if (!$targetLine) {
        Api::error('Target pre-authorization record could not be found.', 404);
        exit;
    }

    // Validate that the line status is active and approved before booking
    if (strtolower($targetLine['status']) !== 'approved') {
        Api::error('Only Approved requests can be scheduled. Current status is: ' . $targetLine['status'], 400);
        exit;
    }

    // 3. Perform Insurance Expiry Validation Check against the requested appointment date
    if (!empty($targetLine['approval_expire_date'])) {
        $expiryTs  = strtotime($targetLine['approval_expire_date']);
        $bookingTs = strtotime($appointmentDate);

        if ($bookingTs > $expiryTs) {
            $formattedExpiry = date('M d, Y', $expiryTs);
            Api::error("Cannot schedule appointment. The insurance approval for procedure '{$targetLine['procedure_name']}' expires on {$formattedExpiry}. Please book an appointment before the approval expires.", 400);
            exit;
        }
    }

    // 4. Discover parent context envelope properties securely using the record's linked case identifier
    $caseId = !empty($targetLine['case_id']) ? (int)$targetLine['case_id'] : (int)$targetLine['id'];
    
    $caseRecord = $db->queryOne(
        "SELECT pac.id AS case_id, pac.patient_id, pac.doctor_id, pac.office_id,
                pat.name AS patient_name, pat.dob AS patient_dob
         FROM `pre_auth_cases` pac
         INNER JOIN `patient` pat ON pac.patient_id = pat.id
         WHERE pac.id = ? AND pac.office_id = ? LIMIT 1",
        [$caseId, $sessionOfficeId]
    );

    if (!$caseRecord) {
        Api::error('Access restricted or clinic scope mismatch for this record context.', 403);
        exit;
    }

    // 5. Gather Context Data for Audit Records and Email Content Logs
    $officeRow  = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$sessionOfficeId]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $creator     = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Unknown User';

    $patientName = $caseRecord['patient_name'];
    $patientDob  = $caseRecord['patient_dob'] ?: '—';

    // Format single target procedure neatly for the notification summary text log block
    $itemizedEmailRows = "  - Tooth {$targetLine['teeth_number']}: {$targetLine['procedure_name']}\r\n";

    // 6. Execute State Transformation Updates ONLY on the clicked item row
    $updateData = [
        'status'           => 'Scheduled',
        'appointment_date' => $appointmentDate,
        'edited_by'        => $currentUserId,
        'edit_time'        => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $preAuthId]);

    // 7. Build and Dispatch Email Notifications Packet
    $subject = "Appointment Scheduled: {$patientName} ({$officeName})";

    $emailBody = "Patient Appointment Scheduled (Isolated Item Execution)\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Pre-Auth Reference ID: #" . $preAuthId . "\r\n";
    $emailBody .= "Case Group Envelope:   #" . $caseId . "\r\n";
    $emailBody .= "Office Scope:          " . $officeName . "\r\n";
    $emailBody .= "Patient Directory:     " . $patientName . " (ID: #" . $caseRecord['patient_id'] . ")\r\n";
    $emailBody .= "Date of Birth:         " . $patientDob . "\r\n";
    $emailBody .= "Insurance Plan ID:     " . ($targetLine['p_insurance_plan'] ?: 'N/A') . "\r\n";
    $emailBody .= "Appointment Date:      " . date('M d, Y', strtotime($appointmentDate)) . "\r\n";
    $emailBody .= "Scheduled By Staff:    " . $creatorName . "\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Scheduled Treatment:\r\n";
    $emailBody .= $itemizedEmailRows;
    $emailBody .= "---------------------------------------------\r\n";

    // Dispatching out to fax dashboard inbox email destination hook
    // EmailSender::send('Ourayfax@gmail.com', $subject, $emailBody);

    // 8. Return standard API Success Object
    Api::success(null, 'Pre-authorization reference #' . $preAuthId . ' has been successfully updated to Scheduled.');

} catch (Exception $e) {
    Api::error('Scheduling state modification or procedure isolation failure: ' . $e->getMessage(), 500);
}