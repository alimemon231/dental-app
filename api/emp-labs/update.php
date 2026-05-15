<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed', 405); exit; }

$id = $_POST['id'] ?? null;
if (!$id) { Api::error('Missing ID'); exit; }

// Prepare Data (matching your create logic)
$updateData = [
    'p_name'          => trim($_POST['patient_name'] ?? ''),
    'provider'        => trim($_POST['doctor_id'] ?? ''),
    'case_type'       => trim($_POST['case_type_id'] ?? ''),
    'impression_type' => trim($_POST['impression_type'] ?? ''),
    'u_arch'          => trim($_POST['u_arch'] ?? ''),
    'l_arch'          => trim($_POST['l_arch'] ?? ''),
    'next_visit'      => trim($_POST['next_visit'] ?? ''),
    'lab_provider'      => trim($_POST['lab_provider'] ?? ''),
    'notes'           => trim($_POST['notes'] ?? '')
];

// 1. Fetch the record first
$lab = $db->selectOne("labs", ['id' => $id]);

// 2. Check if the record exists
if (!$lab) {
    Api::error('Lab case not found.', 404);
    exit;
}

// 3. Check the status
if ($lab['status'] !== 'Sent') {
    Api::error('This case is already being processed and can no longer be edited.', 403);
    exit;
}

try {
    $db->update('labs', $updateData, ['id' => $id]);
    Api::success(null, 'Lab case updated successfully.');
} catch (Exception $e) {
    Api::error('Update failed: ' . $e->getMessage());
}