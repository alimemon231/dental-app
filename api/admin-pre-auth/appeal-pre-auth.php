<?php
/**
 * POST api/admin-pre-auth/appeal-pre-auth.php
 * Promotes a pre-auth record status to 'Appealed' with global Admin scope.
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
    Api::error('A valid Pre-Authorization row ID is required.');
    exit;
}

try {
    // 3. Fetch record details (Removed office_id constraint for Admin scope)
    $recordSql = "
        SELECT 
            pa.id, 
            pa.status, 
            pa.p_insurance_plan,
            pac.id AS case_id,
            pac.patient_id, 
            pac.office_id 
        FROM `pre-auth` pa
        INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
        WHERE pa.id = ? 
        LIMIT 1
    ";
    $record = $db->queryOne($recordSql, [$id]);

    if (!$record) {
        Api::error('Target pre-authorization record not found.', 404);
        exit;
    }

    // Only allow appeals if current status is a rejection state
    $currentStatus = strtolower($record['status']);
    if ($currentStatus !== 'rejected' && $currentStatus !== 'denied') {
        Api::error('Record cannot be appealed. Current state: ' . $record['status'], 400);
        exit;
    }

    // 4. Gather Context for Emails
    $officeRow = $db->queryOne("SELECT office_name FROM offices WHERE id = ? LIMIT 1", [$record['office_id']]);
    $officeName = $officeRow ? $officeRow['office_name'] : 'Unknown Clinic';

    $patientRow = $db->queryOne("SELECT name, dob FROM patient WHERE id = ? LIMIT 1", [$record['patient_id']]);
    $patientName = $patientRow ? $patientRow['name'] : "Patient ID: #{$record['patient_id']}";
    $patientDob  = $patientRow ? $patientRow['dob'] : '—';

    $proceduresList = $db->query(
        "SELECT pa.teeth_number AS tooth_number, proc.name AS procedure_name 
         FROM `pre-auth` pa
         INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
         WHERE pa.id = ?",
        [$id]
    ) ?: [];

    $itemizedEmailRows = "";
    foreach ($proceduresList as $proc) {
        $itemizedEmailRows .= "   - Tooth {$proc['tooth_number']}: {$proc['procedure_name']}\r\n";
    }

    // 5. Execute Update
    $updateData = [
        'status'    => 'Appealed',
        'edited_by' => $currentUser['id'],
        'edit_time' => date('Y-m-d H:i:s')
    ];

    $db->update('pre-auth', $updateData, ['id' => $id]);

    // 6. Build and Dispatch Email
    $emailBody = "ATTENTION MANAGEMENT: Admin-Initiated Pre-Authorization Appeal\r\n";
    $emailBody .= "=====================================================\r\n";
    $emailBody .= "Pre-Auth Reference ID: #{$id}\r\n";
    $emailBody .= "Parent Case ID:        #{$record['case_id']}\r\n";
    $emailBody .= "Office Scope:          {$officeName}\r\n";
    $emailBody .= "Patient Directory:     {$patientName} (ID: #{$record['patient_id']})\r\n";
    $emailBody .= "Date of Birth:         {$patientDob}\r\n";
    $emailBody .= "Appealed By:           {$currentUser['name']} (Admin)\r\n";
    $emailBody .= "-----------------------------------------------------\r\n";
    $emailBody .= "Itemized Treatment:\r\n{$itemizedEmailRows}";
    
    // EmailSender::send('Ourayfax@gmail.com', "URGENT ADMIN APPEAL: {$patientName} ({$officeName})", $emailBody);

    Api::success(null, 'Pre-authorization #' . $id . ' has been updated to Appealed.');

} catch (Exception $e) {
    Api::error('Appeal failure: ' . $e->getMessage(), 500);
}