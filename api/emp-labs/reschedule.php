<?php
/**
 * API: Reschedule Lab Appointment Coordinate
 * Path: /api/emp-labs/reschedule.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed', 405); 
    exit; 
}

// 1. Capture Primary Target Row ID
$id = (int)($_POST['id'] ?? 0);
if (!$id) { 
    Api::error('Missing ID: Lab Case Identification index key is required.'); 
    exit; 
}

// 2. Capture and Validate the Scheduled Date Input
$dateScheduled = trim($_POST['date_scheduled'] ?? '');

if (empty($dateScheduled)) {
    Api::error('Validation Error: A new date and time schedule must be provided to reschedule this procedure.');
    exit;
}

// Convert provided date format securely for SQL standard DATETIME insertion storage
$formattedDateScheduled = date('Y-m-d', strtotime($dateScheduled));

// 3. Fetch the target record first to verify current existence profile
$lab = $db->queryOne("SELECT id, status FROM `labs` WHERE id = ? LIMIT 1", [$id]);

if (!$lab) {
    Api::error('Lab case record not found.', 404);
    exit;
}

// 4. Update Data Payload Array Compilation
$updateData = [
    'date_scheduled' => $formattedDateScheduled,
    'edited_by'      => $auth->userId(),    // Captures the active staff user context tracing changes
    'edited_at'      => date('Y-m-d H:i:s') // Standard audit history timeline tracker update
];

try {
    // 5. Commit the targeted modification to the Database Matrix using standard update interface 
    $db->update('labs', $updateData, ['id' => $id]);
    
    Api::success([
        'lab_id' => $id,
        'new_schedule' => date('M d, Y', strtotime($formattedDateScheduled))
    ], 'Lab appointment has been successfully rescheduled.');

} catch (Exception $e) {
    Api::error('Reschedule processing step execution failure: ' . $e->getMessage(), 500);
}