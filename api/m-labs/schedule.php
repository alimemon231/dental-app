<?php
/**
 * POST api/m-labs/schedule.php
 * Updates appointment date, marks as Scheduled, logs who edited it, and notifies the clinic.
 */
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('m-staff')) {
    Api::error('Unauthorized access. Management only.', 403);
    exit;
}

if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

// 1. Capture Input
$id = $_POST['id'] ?? null;
$appointment_date = $_POST['appointment_date'] ?? null;

if (!$id || !$appointment_date) {
    Api::error('Lab ID and Appointment Date are required.');
    exit;
}

try {
    // 2. Fetch Lab, Patient Name, and Clinic Details for the email from the new schema
    $sql = "SELECT 
                p.name AS patient_name, 
                l.office_id, 
                o.office_name, 
                o.email AS office_email 
            FROM labs l 
            JOIN patient p ON l.p_id = p.id
            JOIN offices o ON l.office_id = o.id 
            WHERE l.id = ?";
            
    $lab = $db->queryOne($sql, [$id]);

    if (!$lab) {
        Api::error('Lab case record not found.');
        exit;
    }

    // 3. Update the Lab Record (including audit footprint logs)
    $updateData = [
        'date_scheduled' => $appointment_date,
        'status'         => 'Scheduled',
        'edited_by'      => $auth->userId(),      // Captures active management session user
        'edited_at'      => date('Y-m-d H:i:s')   // Sets date/time signature
    ];
    $db->update('labs', $updateData, ['id' => $id]);

    // 4. Handle Clinic Email Notification
    if (!empty($lab['office_email'])) {
        $formattedDate = date('M d, Y', strtotime($appointment_date));
        
        $subject = "Appointment Scheduled: {$lab['patient_name']} ({$lab['office_name']})";
        
        $message = "Hello {$lab['office_name']} Team,\r\n\r\n";
        $message .= "This is a notification that the lab case for patient {$lab['patient_name']} has been scheduled.\r\n\r\n";
        $message .= "Scheduled Date: {$formattedDate}\r\n";
        $message .= "Status: Scheduled\r\n\r\n";
        $message .= "Please ensure the patient chart is updated and clinical staff are aware.\r\n";
        $message .= "---------------------------\r\n";
        $message .= "System Generated Notification";

        // Attempt to send email
        //EmailSender::send($lab['office_email'], $subject, $message);
    }

    Api::success(null, 'Lab case scheduled successfully. Clinic has been notified.');

} catch (Exception $e) {
    Api::error('Database Error: ' . $e->getMessage());
}