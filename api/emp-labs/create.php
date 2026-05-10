<?php
/**
 * API: Create Lab Case
 * Path: /api/emp-labs/create.php
 */

require_once __DIR__ . '/../../includes/Auth.php';


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

// 1. Capture Input Data
$currentUserId   = $_SESSION['user_id'];
$p_name          = trim($_POST['patient_name'] ?? '');
$provider_id     = trim($_POST['doctor_id'] ?? '');
$case_type_id    = trim($_POST['case_type_id'] ?? '');
$impression_type = trim($_POST['impression_type'] ?? '');
$u_arch          = trim($_POST['u_arch'] ?? '');
$l_arch          = trim($_POST['l_arch'] ?? '');
$next_visit      = trim($_POST['next_visit'] ?? ''); // Procedure ID
$notes           = trim($_POST['notes'] ?? '');

// 2. Validation
if (empty($p_name) || empty($provider_id) || empty($case_type_id) || empty($next_visit)) {
    Api::error('Patient, Doctor, Case Type, and Next Visit Procedure are required.');
    exit;
}

// 3. Identify the Office ID for the current staff user
$officeInfo = $db->queryOne(
    "SELECT ou.office_id, o.office_name 
     FROM office_users ou 
     JOIN offices o ON ou.office_id = o.id 
     WHERE ou.user_id = ? LIMIT 1",
    [$currentUserId]
);

if (!$officeInfo) {
    Api::error('You are not assigned to an office. Cannot create lab case.', 400);
    exit;
}

$officeId   = $officeInfo['office_id'];
$officeName = $officeInfo['office_name'];

// 4. Prepare Data for Database
// Matching your requested field names: p_name, office_id, provider, case_type, u_arch, l_arch, impression_type, next_visit, date_sent, sent_by, status
$labData = [
    'p_name'          => $p_name,
    'office_id'       => $officeId,
    'provider'        => $provider_id,   // Doctor ID
    'case_type'       => $case_type_id,  // Case Type ID
    'u_arch'          => $u_arch,        // Full or Tooth numbers
    'l_arch'          => $l_arch,        // Full or Tooth numbers
    'impression_type' => $impression_type,
    'next_visit'      => $next_visit,    // Procedure ID
    'notes'           => $notes,
    'sent_by'         => $currentUserId,
    'date_sent'       => date('Y-m-d H:i:s'),
    'status'          => 'Sent'
];

try {
    // 5. Insert into `lab_cases` table (adjust table name if necessary)
    $db->insert('labs', $labData);

   
    // 7. Success Response
    Api::success(null, 'Lab case created successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}