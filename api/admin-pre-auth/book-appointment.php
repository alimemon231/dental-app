<?php
/**
 * API: Book Appointment (Admin)
 * Path: /api/admin-pre-auth/book-appointment.php
 * Promotes an approved pre-auth item record to 'Scheduled' with Admin global scope.
 */

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Enforcement
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Validate Parameters
$preAuthId       = (int)($_POST['id'] ?? 0);
$appointmentDate = trim($_POST['appointment_date'] ?? '');

if ($preAuthId <= 0 || empty($appointmentDate)) {
    Api::error('A valid Pre-Authorization ID and Appointment Date are required.');
    exit;
}

try {
    // 3. Fetch record details (Admin scope: no office constraint)
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

    if (strtolower($targetLine['status']) !== 'approved') {
        Api::error('Only Approved requests can be scheduled. Current status: ' . $targetLine['status'], 400);
        exit;
    }

    // 4. Expiry Validation
    if (!empty($targetLine['approval_expire_date'])) {
        $expiryTs  = strtotime($targetLine['approval_expire_date']);
        $bookingTs = strtotime($appointmentDate);

        if ($bookingTs > $expiryTs) {
            Api::error("Cannot schedule. Approval expires on " . date('M d, Y', $expiryTs), 400);
            exit;
        }
    }

    // 5. Fetch Parent Case Details (Admin scope: removed office_id constraint)
    $caseId = (int)$targetLine['case_id'];
    $caseRecord = $db->queryOne(
        "SELECT pac.id AS case_id, pac.patient_id, pac.office_id,
                o.office_name, pat.name AS patient_name, pat.dob AS patient_dob
         FROM `pre_auth_cases` pac
         INNER JOIN `patient` pat ON pac.patient_id = pat.id
         LEFT JOIN `offices` o ON pac.office_id = o.id
         WHERE pac.id = ? LIMIT 1",
        [$caseId]
    );

    if (!$caseRecord) {
        Api::error('Associated parent case could not be resolved.', 404);
        exit;
    }

    // 6. Update Status
    $updateData = [
        'status'           => 'Scheduled',
        'appointment_date' => $appointmentDate,
        'edited_by'        => $currentUser['id'],
        'edit_time'        => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $preAuthId]);

    // 7. Dispatch Notification
    $officeName = $caseRecord['office_name'] ?: 'Unknown Clinic';
    $emailBody  = "Admin-Scheduled Appointment (Global Scope)\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Reference ID:    #{$preAuthId} | Case ID: #{$caseId}\r\n";
    $emailBody .= "Office:          {$officeName}\r\n";
    $emailBody .= "Patient:         {$caseRecord['patient_name']} (ID: #{$caseRecord['patient_id']})\r\n";
    $emailBody .= "Appointment:     " . date('M d, Y', strtotime($appointmentDate)) . "\r\n";
    $emailBody .= "Scheduled By:    {$currentUser['name']} (Admin)\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Treatment:       Tooth {$targetLine['teeth_number']}: {$targetLine['procedure_name']}\r\n";

   EmailSender::send('Ourayfax@gmail.com', "Admin Scheduled: {$caseRecord['patient_name']}", $emailBody);

    Api::success(null, 'Pre-authorization #' . $preAuthId . ' has been scheduled by Admin.');

} catch (Exception $e) {
    Api::error('Admin scheduling failure: ' . $e->getMessage(), 500);
}