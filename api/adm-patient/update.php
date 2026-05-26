<?php
/**
 * POST api/adm-patient/update.php
 * Administrative global patient update endpoint.
 * Bypasses all strict user-office scoped permissions to allow universal profile changes and location re-assignments.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force active administrative session verification
$auth->requireAuth();

// Strict security barrier: Only admin role members can bypass office constraints
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Administrative permissions required.', 403);
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
    // 1. Fetch existing entry to ensure the patient registry record exists
    $existingPatient = $db->queryOne("SELECT * FROM `patient` WHERE id = ? LIMIT 1", [$patientId]);
    if (!$existingPatient) {
        Api::error('Patient profile registry record not found.', 404);
        exit;
    }

    // 2. Fallback tracking logic for office assignment variables (Admin can select any valid location ID)
    $officeId = (int)($_POST['office_id'] ?? 0);
    if ($officeId <= 0) {
        $officeId = (int)($existingPatient['office_id']); // Maintain current office selection if missing from payload
    }

    // 3. Capture, Trim, and Sanitize inbound client payload data mapping strings
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'dob'         => trim($_POST['dob'] ?? ''),
        'mobile'      => trim($_POST['phone'] ?? $_POST['mobile'] ?? ''), // Maps frontend 'phone' to backend DB column 'mobile'
        'email'       => trim($_POST['email'] ?? ''),
        'address'     => trim($_POST['address'] ?? ''),
        'office_id'   => $officeId,                        // Admin bypasses in_array checks; any office ID is authorized
        'edited_by'   => (int)$currentUserId,              // Tracks which admin committed this modification
        'edited_time' => date('Y-m-d H:i:s')               // Sets execution stamp entry
    ];

    // 4. Explicit validation check
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

    // 5. Execute Database Write Transaction safely
    $db->beginTransaction();

    // Perform global updates where target patient ID row aligns
    $db->update('patient', $data, ['id' => $patientId]);

    $db->commit();
    Api::success(['patient_id' => $patientId], 'Patient profile records modified and audited successfully via admin bypass.');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('System Database Transaction Pipeline Error: ' . $e->getMessage());
}