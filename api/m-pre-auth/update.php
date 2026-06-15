<?php
/**
 * API: Update Pre-Auth
 * Path: /emp-pre-auth/update.php
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

// 1. Capture and Validate ID
$id = (int)($_POST['preauth_id'] ?? 0);
if (!$id) {
    Api::error('Record ID is required for update.');
    exit;
}

// 2. Fetch the Current Record to check status and compare changes
$currentRecord = $db->queryOne("SELECT * FROM `pre-auth` WHERE id = ? AND created_by = ?", [$id, $_SESSION['user_id']]);

if (!$currentRecord) {
    Api::error('Record not found or access denied.', 404);
    exit;
}

// 3. STRICT CHECK: Only allow updates if status is 'Sent'
if ($currentRecord['status'] !== 'Sent') {
    Api::error('This record cannot be modified because it is already ' . $currentRecord['status'] . '.', 400);
    exit;
}

// 4. Capture New Form Data
$newData = [
    'p_first_name'     => trim($_POST['p_first_name'] ?? $currentRecord['p_first_name']),
    'p_last_name'      => trim($_POST['p_last_name'] ?? $currentRecord['p_last_name']),
    'p_dob'            => trim($_POST['p_dob'] ?? $currentRecord['p_dob']),
    'p_insurance_plan' => trim($_POST['p_insurance_plan'] ?? $currentRecord['p_insurance_plan']),
    'treatment_type'   => trim($_POST['treatment_type'] ?? $currentRecord['treatment_type']),
    'tooth_numbers'    => trim($_POST['tooth_numbers'] ?? $currentRecord['tooth_numbers'])
];

// 5. Generate Change Log for Email
$changes = "";
foreach ($newData as $key => $newValue) {
    $oldValue = $currentRecord[$key];
    if ($oldValue != $newValue) {
        $label = str_replace(['p_', '_'], ['', ' '], $key); // Clean labels for email
        $changes .= ucfirst($label) . ": '{$oldValue}' → '{$newValue}'\r\n";
    }
}

// 6. Perform Update if changes exist
if (empty($changes)) {
    Api::success(null, 'No changes detected.');
    exit;
}

try {
    $db->update('pre-auth', $newData, ['id' => $id]);

    // 7. Get Submitter Name and Office for the Email
    $creator = $db->queryOne("SELECT name FROM users WHERE user_id = ? LIMIT 1", [$_SESSION['user_id']]);
    $office  = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$currentRecord['office_id']]);
    
    $creatorName = $creator ? $creator['name'] : 'Staff';
    $officeName  = $office ? $office['office_name'] : 'Unknown Office';

    // 8. Build Update Notification Email
    $emailBody = "Pre-Authorization Modification Record\r\n";
    $emailBody .= "-----------------------------------\r\n";
    $emailBody .= "Office:       " . $officeName . "\r\n";
    $emailBody .= "Patient:      " . $newData['p_first_name'] . " " . $newData['p_last_name'] . "\r\n";
    $emailBody .= "Modified By:  " . $creatorName . "\r\n";
    $emailBody .= "Status:       Sent (Modified)\r\n";
    $emailBody .= "-----------------------------------\r\n";
    $emailBody .= "CHANGES MADE:\r\n";
    $emailBody .= $changes;
    $emailBody .= "-----------------------------------\r\n";

    // Notify the fax/admin email
    EmailSender::send('Ourayfax@gmail.com', "Modified Pre-Auth: {$newData['p_first_name']} {$newData['p_last_name']} ({$officeName})", $emailBody);

    Api::success(null, 'Pre-Auth updated and notification sent.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}