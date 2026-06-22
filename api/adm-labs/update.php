<?php
/**
 * API: Update Lab Case Component Data Matrix
 * Path: /api/emp-labs/update.php
 *
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Verification check for execution authorization permissions
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access to update laboratory workflows.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture Primary Targeting Key Context
$labId = (int)($_POST['id'] ?? 0);

if ($labId <= 0) {
    Api::error('A valid Lab Case Identification tracking index key is required for modification.');
    exit;
}

// 2. Capture All Base Payload Update Fields
$currentUserId   = $_SESSION['user_id'];
$p_id            = trim($_POST['patient_id'] ?? '');
$officeId        = trim($_POST['office_id'] ?? ''); 
$provider_id     = trim($_POST['doctor_id'] ?? '');
$case_type_id    = trim($_POST['case_type_id'] ?? '');
$impression_type = trim($_POST['impression_type'] ?? '');

// New Context: Capture and isolate structural price mapping parameters safely during updates
$price           = trim($_POST['price'] ?? '0.00');

// Capture raw arch fields or fallback to JavaScript inputs
$u_arch          = trim($_POST['u_arch_input'] ?? ($_POST['u_arch'] ?? ''));
$l_arch          = trim($_POST['l_arch_input'] ?? ($_POST['l_arch'] ?? ''));
$arch_selector   = trim($_POST['arch_selector'] ?? '');

// UTILIZE ARCH SELECTOR: If tooth chart values are empty, utilize arch selector to define 'Full' arch selections
if (empty($u_arch) && empty($l_arch) && !empty($arch_selector)) {
    if ($arch_selector === 'upper') {
        $u_arch = 'Full';
    } elseif ($arch_selector === 'lower') {
        $l_arch = 'Full';
    } elseif ($arch_selector === 'both') {
        $u_arch = 'Full';
        $l_arch = 'Full';
    }
}

$lab_provider    = trim($_POST['lab_provider'] ?? '');
$next_visit      = trim($_POST['next_visit'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// Capture Direct Date Fields sent from Frontend
$date_sent       = trim($_POST['date_sent'] ?? '');
$date_received   = trim($_POST['date_received'] ?? '');
$date_scheduled  = trim($_POST['date_scheduled'] ?? '');
$date_complete   = trim($_POST['date_complete'] ?? '');

// Handle dynamic entry status from payload. Fallback to default baseline 'Sent'
$statusInput     = trim($_POST['status'] ?? 'Sent');
$status          = ucfirst(strtolower($statusInput)); 

// Custom Timestamp logic handling if update step overrides standard server execution runtime clock
$createdAtInput  = trim($_POST['created_at'] ?? '');
$baseTimestamp   = !empty($createdAtInput) ? date('Y-m-d H:i:s', strtotime($createdAtInput)) : date('Y-m-d H:i:s');

// 3. Validate Essential Field Bounds
if (empty($p_id) || empty($case_type_id) || empty($next_visit)) {
    Api::error('Patient selection, Case Type, and Next Visit Procedure are required to commit modifications.');
    exit;
}

// Ensure doctor_id/provider logic processes correctly if frontend passed 'undefined' string values
if (empty($provider_id) || $provider_id === 'undefined') {
    $provider_id = null; 
}

// 4. Office ID Retrieval Engine fallback
if (empty($officeId) || $officeId === 'undefined') {
    $officeInfo = $db->queryOne(
        "SELECT ou.office_id 
         FROM office_users ou 
         WHERE ou.user_id = ? LIMIT 1",
        [$currentUserId]
    );
    
    if (!$officeInfo) {
        Api::error('Workspace Context Error: Target office field missing or unassigned.', 400);
        exit;
    }
    $officeId = $officeInfo['office_id'];
}

// Re-enforce explicit status definitions safely
if ($status === 'Received')  { $status = 'Received'; }
if ($status === 'Scheduled') { $status = 'Scheduled'; }
if ($status === 'Done')      { $status = 'Done'; }

// 5. Verify the existence of the row before modifying it
$existingLab = $db->queryOne("SELECT id FROM `labs` WHERE id = ? LIMIT 1", [$labId]);
if (!$existingLab) {
    Api::error('The target laboratory case record row could not be found.', 404);
    exit;
}

// 6. Package Compiled Array Block for Clean Table Schema Engine Mapping
$labUpdateData = [
    'p_id'            => intval($p_id),
    'office_id'       => intval($officeId),
    'provider'        => !empty($provider_id) ? intval($provider_id) : null,
    'case_type'       => intval($case_type_id),
    'u_arch'          => $u_arch,
    'l_arch'          => $l_arch,
    'impression_type' => $impression_type,
    'next_visit'      => $next_visit,
    'lab_provider'    => !empty($lab_provider) ? intval($lab_provider) : null,
    'status'          => $status,
    'notes'           => $notes,
    
    // Explicit Dynamic Value Injection Context for Updates
    'price'           => floatval($price),
    
    // Explicit Dates captured from the Frontend Payload
    'date_sent'       => !empty($date_sent) ? $date_sent : null,
    'date_received'   => !empty($date_received) ? $date_received : null,
    'date_scheduled'  => !empty($date_scheduled) ? $date_scheduled : null,
    'date_complete'   => !empty($date_complete) ? $date_complete : null, 
    
    // Tracking assignments modified to contextually trace modifications
    'sent_by'         => $currentUserId, // Keeps record of last admin/staff interacting with this row state
    'edited_at'       => $baseTimestamp  // Dynamic update time payload string 'YYYY-MM-DD HH:MM:SS'
];

// 7. DB Execution Sequence Update Pipeline
try {
    $db->update('labs', $labUpdateData, ['id' => $labId]);
    Api::success(['lab_id' => $labId], 'Lab case record synchronized and updated successfully.');
} catch (Exception $e) {
    Api::error('Database Error Update Execution Failure: ' . $e->getMessage(), 500);
}