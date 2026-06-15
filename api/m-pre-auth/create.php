<?php
/**
 * API: Create Pre-Auth
 * Path: /emp-pre-auth/create.php
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

// 2. Capture Input Data
$currentUserId   = $_SESSION['user_id'];
$p_first_name    = trim($_POST['p_first_name'] ?? '');
$p_last_name     = trim($_POST['p_last_name'] ?? '');
$p_dob           = trim($_POST['p_dob'] ?? '');
$p_insurance     = trim($_POST['p_insurance_plan'] ?? '');
$treatment_type  = trim($_POST['treatment_type'] ?? '');
$tooth_numbers   = trim($_POST['tooth_numbers'] ?? '');

// 3. Validation
if (empty($p_first_name) || empty($p_last_name) || empty($p_dob) || empty($p_insurance) || empty($treatment_type) || empty($tooth_numbers)) {
    Api::error('All fields are required to submit a pre-auth.');
    exit;
}

// 4. Identify the Office ID AND Office Name for this user
$officeInfo = $db->queryOne(
    "SELECT ou.office_id, o.office_name as office_name 
     FROM office_users ou 
     JOIN offices o ON ou.office_id = o.id 
     WHERE ou.user_id = ? LIMIT 1",
    [$currentUserId]
);

if (!$officeInfo) {
    Api::error('You are not assigned to an office. Cannot create pre-auth.', 400);
    exit;
}
$officeId   = $officeInfo['office_id'];
$officeName = $officeInfo['office_name'];

// 5. Get the Creator's Name
$creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$currentUserId]);
$creatorName = $creator ? $creator['name'] : 'Unknown User';

// 6. Prepare Data for Database
$preAuthData = [
    'p_first_name'     => $p_first_name,
    'p_last_name'      => $p_last_name,
    'p_dob'            => $p_dob,
    'p_insurance_plan' => $p_insurance,
    'treatment_type'   => $treatment_type,
    'tooth_numbers'    => $tooth_numbers,
    'office_id'        => $officeId,
    'created_by'       => $currentUserId,
    'created_at'       => date('Y-m-d H:i:s'),
    'status'           => 'Sent'
];

try {
    // 7. Insert into `pre-auth` table
    $db->insert('pre-auth', $preAuthData);

    // 8. Build and Send Email Notification (Professional Version)
    $emailBody = "New Pre-Authorization Created\r\n";
    $emailBody .= "---------------------------\r\n";
    $emailBody .= "Office:     " . $officeName . "\r\n";
    $emailBody .= "Patient:    " . $p_first_name . " " . $p_last_name . "\r\n";
    $emailBody .= "DOB:        " . $p_dob . "\r\n";
    $emailBody .= "Insurance:  " . $p_insurance . "\r\n";
    $emailBody .= "Treatment:  " . $treatment_type . "\r\n";
    $emailBody .= "Tooth #:    " . $tooth_numbers . "\r\n";
    $emailBody .= "Status:     Sent\r\n";
    $emailBody .= "Submitted By: " . $creatorName . "\r\n";
    $emailBody .= "---------------------------\r\n";

    // Notify the fax/admin email
    EmailSender::send('Ourayfax@gmail.com', "New Pre-Auth: {$p_first_name} {$p_last_name} ({$officeName})", $emailBody);

    // 9. Success Response
    Api::success(null, 'Pre-Auth created and notification sent.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}