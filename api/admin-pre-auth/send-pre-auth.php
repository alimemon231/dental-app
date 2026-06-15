<?php
/**
 * POST api/admin-pre-auth/send-pre-auth.php
 * Promotes an individual procedure row status to 'Sent' (Admin Global Scope).
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

// 2. Validate Target Parameter
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    Api::error('A valid Pre-Authorization ID is required.');
    exit;
}

try {
    // 3. Verify that the targeted row exists
    $record = $db->queryOne(
        "SELECT * FROM `pre-auth` WHERE id = ? LIMIT 1",
        [$id]
    );

    if (!$record) {
        Api::error('Target pre-authorization record not found.', 404);
        exit;
    }

    $caseId = (int)$record['case_id'];

    // 4. Admin check: Look up parent case without office_id constraint
    $caseMetadata = $db->queryOne(
        "SELECT pac.id, pac.patient_id, pac.office_id, o.office_name 
         FROM `pre_auth_cases` pac
         LEFT JOIN `offices` o ON pac.office_id = o.id 
         WHERE pac.id = ? LIMIT 1",
        [$caseId]
    );

    if (!$caseMetadata) {
        Api::error('Associated parent case could not be resolved.', 404);
        exit;
    }

    // Allow states matching 'requested', 'create', or 'created'
    $normalizedStatus = strtolower(trim($record['status']));
    if ($normalizedStatus !== 'create' && $normalizedStatus !== 'created' && $normalizedStatus !== 'requested') {
        Api::error('Record cannot be marked as sent. Current state: ' . $record['status'], 400);
        exit;
    }

    // 5. Context Data for Logs
    $officeName = $caseMetadata['office_name'] ?: 'Unknown Clinic';
    $adminName  = $currentUser['name'] ?: 'Administrator';

    $patientRow  = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$caseMetadata['patient_id']]);
    $patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$caseMetadata['patient_id']}";
    $patientDob  = $patientRow ? $patientRow['dob'] : '—';

    $proceduresList = $db->query(
        "SELECT pa.teeth_number AS tooth_number, proc.name AS procedure_name 
         FROM `pre-auth` pa
         INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
         WHERE pa.id = ? LIMIT 1",
        [$id]
    ) ?: [];

    $itemizedEmailRows = "";
    foreach ($proceduresList as $proc) {
        $itemizedEmailRows .= "  - Tooth " . ($proc['tooth_number'] ?: '—') . ": {$proc['procedure_name']}\r\n";
    }

    // 6. Execute State Update
    $updateData = [
        'status'    => 'Sent',
        'edited_by' => $currentUser['id'],
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 7. Dispatch Notification
    $emailBody = "Admin Dispatched Pre-Auth\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Reference ID: #{$id} | Case ID: #{$caseId}\r\n";
    $emailBody .= "Office:       {$officeName}\r\n";
    $emailBody .= "Patient:      {$patientName} (DOB: {$patientDob})\r\n";
    $emailBody .= "Admin User:   {$adminName}\r\n";
    $emailBody .= "---------------------------------------------\r\n";
    $emailBody .= "Treatments:\r\n{$itemizedEmailRows}";
    
   EmailSender::send('Ourayfax@gmail.com', "Admin Dispatched: {$patientName} ({$officeName})", $emailBody);

    Api::success(null, 'Pre-authorization #' . $id . ' has been updated to Sent via Admin override.');

} catch (Exception $e) {
    Api::error('Admin transmission failure: ' . $e->getMessage(), 500);
}