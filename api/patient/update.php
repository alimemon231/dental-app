<?php
/**
 * POST api/patient/update.php
 * Updates an existing patient profile while tracking modifications and checking office permissions.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force active session verification
$auth->requireAuth();

// Authorization Guard (Permit staff role members to manage details updates)
if (!$auth->hasRole('staff') && !$auth->hasRole('admin') && !$auth->hasRole('doctor')) {
    Api::error('You are not authorized for this operation', 403);
    exit;
}

// Ensure proper Request Method
if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

$patientId = (int)($_POST['patient_id'] ?? $_POST['id'] ?? 0);
if (!$patientId) {
    Api::error('Patient ID is required for executing updating structures.', 400);
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    Api::error('User tracking session context not found.', 401);
    exit;
}

try {
    // 1. Fetch existing entry to authenticate cross-office data ownership integrity
    $existingPatient = $db->queryOne("SELECT * FROM `patient` WHERE id = ? LIMIT 1", [$patientId]);
    if (!$existingPatient) {
        Api::error('Patient profile registry record not found.', 404);
        exit;
    }

    // 2. Fetch logged-in user permissions to block unauthorized external injections
    $assignedOfficesSql = "SELECT office_id FROM `office_users` WHERE user_id = ?";
    $assignedRows = $db->query($assignedOfficesSql, [$currentUserId]);
    $allowedOfficeIds = array_map(function($row) { return (int)($row['office_id'] ?? $row['id']); }, $assignedRows);

    if (empty($allowedOfficeIds)) {
        Api::error('Access Denied. You do not belong to any active clinic locations.', 403);
        exit;
    }

    // Verify existing assignment safety
    if (!in_array((int)$existingPatient['office_id'], $allowedOfficeIds)) {
        Api::error('Access Denied. You do not have permission to alter records from this facility.', 403);
        exit;
    }

    // 3. Fallback tracking logic for office assignment variables
    $officeId = (int)($_POST['office_id'] ?? 0);
    if ($officeId <= 0) {
        $officeId = (int)($existingPatient['office_id']); // Maintain current office selection if missing from payload
    }

    // Verify target destination office safety if changed
    if (!in_array($officeId, $allowedOfficeIds)) {
        Api::error('Access Denied. You cannot move a record into a facility you do not control.', 403);
        exit;
    }

    // 4. Capture, Trim, and Sanitize inbound client payload data mapping strings
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'dob'         => trim($_POST['dob'] ?? ''),
        'mobile'      => trim($_POST['phone'] ?? $_POST['mobile'] ?? ''), // Maps frontend 'phone' to backend DB column 'mobile'
        'email'       => trim($_POST['email'] ?? ''),
        'address'     => trim($_POST['address'] ?? ''),
        'office_id'   => $officeId,
        'edited_by'   => (int)$currentUserId,  // Tracks who committed this modification
        'edited_time' => date('Y-m-d H:i:s')   // Sets execution stamp entry
    ];

    // 5. Explicit validation check
    if (
        empty($data['name']) || 
        empty($data['dob']) || 
        empty($data['mobile']) || 
        empty($data['address']) ||
        $data['office_id'] <= 0
    ) {
        Api::error('All fields are required, including a valid office assignment context.');
        exit;
    }

    // 6. Execute Database Write Transaction safely
    $db->beginTransaction();

    // Perform selective updates where target patient ID row aligns
    $db->update('patient', $data, ['id' => $patientId]);

    $db->commit();
    Api::success(['patient_id' => $patientId], 'Patient profile records modified and audited successfully.');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('System Database Transaction Pipeline Error: ' . $e->getMessage());
}