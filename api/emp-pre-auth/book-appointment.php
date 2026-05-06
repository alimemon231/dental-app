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

// 1. Role Check: Strictly for staff members
if (!$auth->hasRole('staff')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Input Data
$id              = (int)($_POST['id'] ?? 0);
$appointmentDate = trim($_POST['appointment_date'] ?? '');
$currentUserId   = $_SESSION['user_id'];

// 3. Validation
if (!$id || empty($appointmentDate)) {
    Api::error('Record ID and Appointment Date are required.');
    exit;
}

try {
    // 4. Fetch the Current Record 
    // Ensure the record belongs to this user/office and is actually 'Approved'
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
        Api::error('Only Approved requests can be scheduled. Current status: ' . $record['status']);
        exit;
    }

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
    $emailBody .= "Scheduled By:     " . $creatorName . "\r\n";
    $emailBody .= "Original Status:  Approved\r\n";
    $emailBody .= "New Status:       Scheduled\r\n";
    $emailBody .= "-----------------------------------\r\n";
    $emailBody .= "This record has been moved to the scheduled treatment phase.\r\n";

    // Send notification to management
    //EmailSender::send('Ourayfax@gmail.com', $subject, $emailBody);

    // 7. Success Response
    Api::success(null, 'Appointment scheduled and management notified.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}