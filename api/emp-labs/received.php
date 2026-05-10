<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed', 405); exit; }

$id = $_POST['id'] ?? null;
$status = 'Received'; // Explicitly setting to Received
$currentUserId = $_SESSION['user_id'];

if (!$id) {
    Api::error('Record ID is required.');
    exit;
}

try {
    // 1. Fetch full record with Joins for the email content
    $sql = "SELECT l.*, o.office_name, u.name as doctor_name, ct.name as case_name
            FROM labs l
            LEFT JOIN offices o ON l.office_id = o.id
            LEFT JOIN users u ON l.provider = u.user_id
            LEFT JOIN case_type ct ON l.case_type = ct.id
            WHERE l.id = ? LIMIT 1";
    $lab = $db->queryOne($sql, [$id]);

    if (!$lab) {
        Api::error('Lab case not found.');
        exit;
    }

    // 2. Update the record
    $updateData = [
        'status' => $status,
        'received_by' => $currentUserId,
        'date_received' => date('Y-m-d H:i:s')
    ];
    $db->update('labs', $updateData, ['id' => $id]);

    // 3. Get Receiver Name for email
    $receiver = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
    $receiverName = $receiver ? $receiver['name'] : 'Unknown Staff';

    // 4. Build and Send Email
    $emailBody = "Lab Case Marked as RECEIVED\r\n";
    $emailBody .= "---------------------------\r\n";
    $emailBody .= "Office:     " . $lab['office_name'] . "\r\n";
    $emailBody .= "Patient:    " . $lab['p_name'] . "\r\n";
    $emailBody .= "Doctor:     Dr. " . $lab['doctor_name'] . "\r\n";
    $emailBody .= "Case Type:  " . $lab['case_name'] . "\r\n";
    $emailBody .= "Impression: " . $lab['impression_type'] . "\r\n";
    $emailBody .= "Status:     Received\r\n";
    $emailBody .= "Received By: " . $receiverName . "\r\n";
    $emailBody .= "Date:       " . date('M d, Y H:i') . "\r\n";
    $emailBody .= "---------------------------\r\n";

    // Sending to the same administrative email used in Pre-Auth
    EmailSender::send('Ourayfax@gmail.com', "Lab RECEIVED: {$lab['p_name']} ({$lab['office_name']})", $emailBody);

    Api::success(null, "Case marked as Received and notification sent.");

} catch (Exception $e) {
    Api::error("Error: " . $e->getMessage());
}