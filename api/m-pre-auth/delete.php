<?php
/**
 * API: Delete Pre-Auth
 * Path: /emp-pre-auth/delete.php
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
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Record ID is required for deletion.');
    exit;
}

// 2. Fetch the Record to verify ownership and status
$currentUserId = $_SESSION['user_id'];
$record = $db->queryOne("SELECT * FROM `pre-auth` WHERE id = ? AND created_by = ?", [$id, $currentUserId]);

if (!$record) {
    Api::error('Record not found or you do not have permission to delete it.', 404);
    exit;
}

// 3. STRICT CHECK: Only allow deletion if status is 'Sent'
// We don't want staff deleting records that are already 'Approved' or 'Processed'
if ($record['status'] !== 'Sent') {
    Api::error('Cannot delete a record that has already been ' . $record['status'] . '.', 400);
    exit;
}

try {
    // 4. Delete the Record
    $deleted = $db->delete('pre-auth', ['id' => $id]);

    if ($deleted) {
        // 5. Get Office Name for the notification
        $office = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$record['office_id']]);
        $officeName = $office ? $office['office_name'] : 'Unknown Office';

        // 6. Notify Admin/Fax about the deletion
        $emailBody = "Pre-Authorization DELETED / CANCELLED\r\n";
        $emailBody .= "-------------------------------------\r\n";
        $emailBody .= "Office:     " . $officeName . "\r\n";
        $emailBody .= "Patient:    " . $record['p_first_name'] . " " . $record['p_last_name'] . "\r\n";
        $emailBody .= "Action:     Record removed from system by staff.\r\n";
        $emailBody .= "Date:       " . date('Y-m-d H:i:s') . "\r\n";
        $emailBody .= "-------------------------------------\r\n";

        //EmailSender::send('Ourayfax@gmail.com', "Deleted Pre-Auth: {$record['p_first_name']} {$record['p_last_name']}", $emailBody);

        Api::success(null, 'Pre-Auth record successfully deleted.');
    } else {
        throw new Exception("Failed to delete record from database.");
    }

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}