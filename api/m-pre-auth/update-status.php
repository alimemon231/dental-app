<?php
/**
 * API: Update Pre-Auth Status (Approve/Reject)
 * Path: /api/m-pre-auth/update-status.php
 */

require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/EmailSender.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Check: Only management or admin can process requests
if (!$auth->hasRole('m-staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Reviewer privileges required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Input
$preAuthId     = (int)($_POST['id'] ?? 0);
$newStatus     = trim($_POST['status'] ?? ''); // 'Approved' or 'Rejected'
$reviewerId    = $_SESSION['user_id']; 
$expiery  = $_POST['expiry_date'];

// 3. Basic Validation
if (!$preAuthId || !in_array($newStatus, ['Approved', 'Rejected'])) {
    Api::error('Invalid request parameters.');
    exit;
}

try {
    // 4. Fetch the existing record and Clinic Details
    // We join with 'offices' to get the clinic's email address
    $sql = "SELECT pa.*, o.office_name, o.email as clinic_email 
            FROM `pre-auth` pa
            JOIN offices o ON pa.office_id = o.id
            WHERE pa.id = ?";
    
    $record = $db->queryOne($sql, [$preAuthId]);

    if (!$record) {
        Api::error('Pre-auth record not found.', 404);
        exit;
    }

    // 5. Update the Database
    // We update status, the reviewer ID, and the timestamp
    $updateData = [
        'status'      => $newStatus,
        'approved_by' => $reviewerId,
        'approval_expire_date' => $expiery, 
    ];

    $db->update("pre-auth", $updateData, ['id' => $preAuthId]);

    // 6. Send Email Notification to the Clinic
    if (!empty($record['email'])) {
        $subject = "Pre-Auth Update: {$record['p_first_name']} {$record['p_last_name']} - {$newStatus}";
        
        $emailBody = "Pre-Authorization Status Update\r\n";
        $emailBody .= "----------------------------------\r\n";
        $emailBody .= "Patient:    " . $record['p_first_name'] . " " . $record['p_last_name'] . "\r\n";
        $emailBody .= "Office:     " . $record['office_name'] . "\r\n";
        $emailBody .= "Status:     " . strtoupper($newStatus) . "\r\n";
        $emailBody .= "Updated At: " . date('M d, Y h:i A') . "\r\n";
        $emailBody .= "----------------------------------\r\n";
        $emailBody .= "Please log in to the portal to view full details.\r\n";

        // Use the EmailSender utility
        //EmailSender::send($record['clinic_email'], $subject, $emailBody);
    }

    // 7. Success Response
    Api::success(null, "Pre-Auth has been successfully {$newStatus}.");

} catch (Exception $e) {
    Api::error('Server Error: ' . $e->getMessage());
}