<?php
/**
 * API: Create Dynamic Statistics Widget Matrix
 * Path: /adm-static/create.php
 * Handles custom-configured multi-entity data matrices injected as JSON payloads.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce strict administrative permission protection layer 
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized administrative access privileges.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Parse and Sanitize Incoming Form Payload Parameters
$staticMonth     = trim($_POST['static_month'] ?? '');
$staticLabel     = trim($_POST['static_label'] ?? '');
$targetType      = trim($_POST['target_type'] ?? '');
$targetId        = trim($_POST['target_id'] ?? '');
$chartType       = trim($_POST['chart_type'] ?? '');

// Visibility toggles cast directly to standard numerical format types
$staffVisibility  = (int)($_POST['staff_visiblity'] ?? 1);
$doctorVisibility = (int)($_POST['doctor_visiblity'] ?? 1);

// Capture the stringified JSON packet payload map matching your configuration
$jsonDataRaw     = trim($_POST['json_data'] ?? '');

// 2. Validate Constraints Matrix
if (empty($staticMonth) || empty($staticLabel) || empty($targetType) || empty($targetId) || empty($chartType) || empty($jsonDataRaw)) {
    Api::error('All configuration fields including Month, Label, Target Scope, Chart Type, and Dataset values are required.');
    exit;
}

// Validate Enum options matching database schema setup constraints exactly
if (!in_array($targetType, ['office', 'doctor'])) {
    Api::error('Invalid target scope destination type parameter selection.');
    exit;
}

// Validate that incoming json payload string is valid syntactic JSON before hitting storage layer
$decodedJson = json_decode($jsonDataRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    Api::error('Malformed dataset layout payload context: Invalid JSON structure provided.');
    exit;
}

// 3. Format Date Input Structurally to Uniform "YYYY-MM-01" Format Layout Bounds
$finalStaticMonth = date('Y-m-d', strtotime($staticMonth));

// 4. Assemble Database Row Object Target Matrix Array matching your `statics` Schema
$staticWidgetData = [
    'static_month'     => $finalStaticMonth,
    'static_label'     => $staticLabel,
    'target_type'      => $targetType,
    'target_id'        => $targetId, // Can store string "all" or specific numerical identification string index
    'chart_type'       => $chartType,
    'json_data'        => $jsonDataRaw, // Stored as valid string text payload mapping parameters
    'staff_visiblity'  => $staffVisibility,
    'doctor_visiblity' => $doctorVisibility
    // Note: created_at and updated_at handled automatically via DEFAULT database engine timestamp triggers
];

try {
    // 5. Execute single transactional write to insert your dynamic metrics row configuration
    $staticId = $db->insert('statics', $staticWidgetData);

    if (!$staticId) {
        throw new Exception('Failed to generate operational metrics record sequence database identifier.');
    }

    // 6. Return Clean Success Envelope Output Layout Context
    Api::success([
        'static_id' => $staticId
    ], 'Dynamic matrix metrics dashboard component published successfully.');

} catch (Exception $e) {
    Api::error('Database runtime schema mutation execution exception: ' . $e->getMessage(), 500);
}