<?php
/**
 * API: Create Lab Case
 * Path: /api/emp-labs/create.php
 * * Logic to save lab case details including calculated or selected base price metrics.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture Input Data
$currentUserId   = $_SESSION['user_id'];
$p_id            = trim($_POST['patient_id'] ?? ''); 
$provider_id     = trim($_POST['doctor_id'] ?? '');
$case_type_id    = trim($_POST['case_type_id'] ?? '');
$impression_type = trim($_POST['impression_type'] ?? '');
$u_arch          = trim($_POST['u_arch'] ?? '');
$l_arch          = trim($_POST['l_arch'] ?? '');
$lab_provider    = trim($_POST['lab_provider'] ?? '');
$next_visit      = trim($_POST['next_visit'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// CAPTURED FIELD: Read price from incoming serial data payload
$price           = trim($_POST['price'] ?? '0.00');

// 2. Validation
if (empty($p_id) || empty($provider_id) || empty($case_type_id) || empty($next_visit)) {
    Api::error('Patient selection, Doctor, Case Type, and Next Visit Procedure are required.');
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
$labData = [
    'p_id'            => $p_id,          
    'office_id'       => $officeId,
    'provider'        => $provider_id,   
    'case_type'       => $case_type_id,  
    'price'           => floatval($price), // Saved explicitly into table data mapping row array
    'u_arch'          => $u_arch,        
    'l_arch'          => $l_arch,        
    'impression_type' => $impression_type,
    'next_visit'      => $next_visit,    
    'lab_provider'    => $lab_provider,
    'notes'           => $notes,
    'sent_by'         => $currentUserId,
    'date_sent'       => date('Y-m-d H:i:s'),
    'status'          => 'Sent'
];

try {
    // 5. Insert into table row mapping block
    $db->insert('labs', $labData);

    // 6. Success Response
    Api::success(null, 'Lab case created successfully.');

} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}