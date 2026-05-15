<?php
/**
 * API: Book Appointment
 * Path: /api/emp-pre-auth/book-appointment.php
 */

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

$id              = (int)($_POST['id'] ?? 0);
$appointmentDate = trim($_POST['appointment_date'] ?? '');
$currentUserId   = $_SESSION['user_id'];

if (!$id || empty($appointmentDate)) {
    Api::error('Record ID and Appointment Date are required.');
    exit;
}


    // 4. Fetch the Current Record including the approval_expiry_date
    $sql = "SELECT pa.*, o.office_name 
            FROM `pre-auth` pa 
            JOIN offices o ON pa.office_id = o.id
            WHERE pa.id = ? AND pa.created_by = ? LIMIT 1";
    
    $record = $db->queryOne($sql, [$id, $currentUserId]);

    if (!$record) {
        Api::error('Record not found or access denied.', 404);
        exit;
    }

    if ($record['status'] !== 'Approved') {
        Api::error('Only Approved requests can be scheduled. Current status: ');
        exit;
    }

    // --- NEW EXPIRY DATE VALIDATION ---
    if (!empty($record['approval_expire_date'])) {
        $expiryTs = strtotime($record['approval_expire_date']);
        $bookingTs = strtotime($appointmentDate);

        if ($bookingTs > $expiryTs) {
            $formattedExpiry = date('M d, Y', $expiryTs);
            Api::error("Cannot schedule appointment. The insurance approval expires on {$formattedExpiry}. Please book an appointment before the approval expires.");
            exit;
        }
    }
    // ----------------------------------

    // 5. Update the Database
    $updateData = [
        'status'           => 'Scheduled',
        'appointment_date' => $appointmentDate,
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 6. Notify Management via Email
    $creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $creatorName = $creator ? $creator['name'] : 'Staff Member';

    $subject = "Appointment Scheduled: {$record['p_first_name']} {$record['p_last_name']} ({$record['office_name']})";

    $emailBody = "Patient Appointment Scheduled\r\n";
    $emailBody .= "-----------------------------------\r\n";
    $emailBody .= "Office:           " . $record['office_name'] . "\r\n";
    $emailBody .= "Patient:          " . $record['p_first_name'] . " " . $record['p_last_name'] . "\r\n";
    $emailBody .= "Appointment Date: " . date('M d, Y', strtotime($appointmentDate)) . "\r\n";
    $emailBody .= "Expiry Date:      " . ($record['approval_expire_date'] ? date('M d, Y', strtotime($record['approval_expire_date'])) : 'N/A') . "\r\n";
    $emailBody .= "Scheduled By:     " . $creatorName . "\r\n";
    $emailBody .= "-----------------------------------\r\n";

    // EmailSender::send('Ourayfax@gmail.com', $subject, $emailBody);

    Api::success(null, 'Appointment scheduled and management notified.');

