<?php
/**
 * GET api/adm-labs/get-edit.php
 * Fetches raw single lab case data mapped specifically for hydration into an Edit Form.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin/Staff Security Check (Allow staff/admin roles to retrieve editable data)
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

// 2. Validate ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Api::error('Invalid lab case ID.', 400);
    exit;
}

/**
 * 3. Fetch Raw Core Lab Data
 */
$sql = "SELECT l.* FROM labs l WHERE l.id = ?";
$data = $db->queryOne($sql, [$id]);

if (!$data) {
    Api::error('Lab case not found.', 404);
    exit;
}

/**
 * 4. Map & Format Fields for JS Hydration Engine Logic
 * Returns raw database IDs and raw standard YYYY-MM-DD formats for native inputs.
 */
$editData = [
    'id'              => (int) $data['id'],
    'patient_id'      => (int) $data['p_id'],
    'price'      => (int) $data['price'],
    'office_id'       => (int) $data['office_id'],
    'doctor_id'       => $data['provider'] ? (int) $data['provider'] : null,
    'case_type_id'    => (int) $data['case_type'],
    'impression_type' => $data['impression_type'] ?: '',
    'lab_provider'    => $data['lab_provider'] ? (int) $data['lab_provider'] : null,
    'next_visit'      => $data['next_visit'] ? (int) $data['next_visit'] : null,
    'status'          => $data['status'] ?: 'Sent',
    'notes'           => $data['notes'] ?: '',
   // Replace the bottom mapping section of your get-edit.php with this:

    // Direct raw date mappings passed seamlessly to element targets without view format breaks
    'date_sent'       => ($data['date_sent'] && $data['date_sent'] !== '0000-00-00') ? $data['date_sent'] : null,
    'date_received'   => ($data['date_received'] && $data['date_received'] !== '0000-00-00') ? $data['date_received'] : null,
    'date_scheduled'  => ($data['date_scheduled'] && $data['date_scheduled'] !== '0000-00-00') ? $data['date_scheduled'] : null,
    'date_complete'   => ($data['date_complete'] && $data['date_complete'] !== '0000-00-00') ? $data['date_complete'] : null,
    
    // Arch parameters mapping (Removed the non-existent arch_selector)
    'u_arch'          => $data['u_arch'] ?: '',
    'l_arch'          => $data['l_arch'] ?: ''
];

// 5. Success Response
Api::success($editData, 'Lab edit details streams mapped successfully.');

