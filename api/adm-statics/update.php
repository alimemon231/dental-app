<?php
/**
 * API: Update Dynamic Statistics Widget Matrix
 * Path: /adm-static/update.php
 * Handles updates to existing custom-configured multi-entity data matrices.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Enforce administrative permission
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized administrative access privileges.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Validate ID existence
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    Api::error('A valid widget identifier is required for updates.');
    exit;
}

// 3. Parse and Sanitize Incoming Form Payload
$staticMonth      = trim($_POST['static_month'] ?? '');
$staticLabel      = trim($_POST['static_label'] ?? '');
$targetType       = trim($_POST['target_type'] ?? '');
$targetId         = trim($_POST['target_id'] ?? '');
$chartType        = trim($_POST['chart_type'] ?? '');

$staffVisibility  = (int)($_POST['staff_visiblity'] ?? 1);
$doctorVisibility = (int)($_POST['doctor_visiblity'] ?? 1);
$jsonDataRaw      = trim($_POST['json_data'] ?? '');

// 4. Validate Constraints Matrix
if (empty($staticMonth) || empty($staticLabel) || empty($targetType) || empty($targetId) || empty($chartType) || empty($jsonDataRaw)) {
    Api::error('All configuration fields are required.');
    exit;
}

// Ensure JSON is valid before updating
$decodedJson = json_decode($jsonDataRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    Api::error('Malformed dataset payload: Invalid JSON structure.');
    exit;
}

// 5. Assemble Update Array
$updateData = [
    'static_month'     => date('Y-m-d', strtotime($staticMonth)),
    'static_label'     => $staticLabel,
    'target_type'      => $targetType,
    'target_id'        => $targetId,
    'chart_type'       => $chartType,
    'json_data'        => $jsonDataRaw,
    'staff_visiblity'  => $staffVisibility,
    'doctor_visiblity' => $doctorVisibility
];

try {
    // 6. Execute Update
    // Assuming your Database class 'update' method takes (table, data, where_clause)
    $affectedRows = $db->update('statics', $updateData, ['id' => $id]);

    if ($affectedRows === false) {
        throw new Exception('Database update execution failed.');
    }

    Api::success(['id' => $id], 'Dynamic matrix metrics widget updated successfully.');

} catch (Exception $e) {
    Api::error('Database runtime update exception: ' . $e->getMessage(), 500);
}