<?php
/**
 * API: Get Dynamic Statistics Widget Record
 * Path: /adm-static/get.php?id=XX
 * Fetches a single static widget configuration for administrative editing.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Administrative Role Gate
if ($auth->userRole() !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Validate input parameter
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Api::error('A valid static widget record identifier is required.');
    exit;
}

try {
    /**
     * 3. Target Query Execution
     * Fetches the static record by ID.
     */
    $sql = "SELECT * FROM `statics` WHERE id = ? LIMIT 1";
    $record = $db->queryOne($sql, [$id]);

    if (!$record) {
        Api::error('The requested dynamic metric widget could not be found.', 404);
        exit;
    }

    /**
     * 4. Normalize Data Payload
     * We return the raw database record, including the JSON string and visibility integers.
     * The frontend's populateEditForm() function will handle the JSON.parse() and 
     * visibility mapping.
     */
    $response = [
        'id'               => (int)$record['id'],
        'static_month'     => $record['static_month'], // Expect YYYY-MM-DD
        'static_label'     => $record['static_label'],
        'target_type'      => $record['target_type'],
        'target_id'        => $record['target_id'],
        'chart_type'       => $record['chart_type'],
        'json_data'        => $record['json_data'], // Returned as original JSON string for frontend parsing
        'staff_visiblity'  => (int)$record['staff_visiblity'],
        'doctor_visiblity' => (int)$record['doctor_visiblity'],
        'created_at'       => $record['created_at']
    ];

    Api::success($response);

} catch (Exception $e) {
    Api::error('Database retrieval execution exception: ' . $e->getMessage(), 500);
}