<?php
/**
 * API: Fetch Dynamic Dashboard Statistics Metrics for Doctors
 * Path: /api/doctor-static/get-dashboard-metrics.php
 * Fetches, filters, and reduces matrix widgets visible to the active logged-in doctor.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Structural Access Gate Constraint (Enforce Doctor role)
if (!$auth->hasRole('doctor')) {
    Api::error('Unauthorized access. Doctor diagnostic operational clearance required.', 403);
    exit;
}

// 2. Establish Active Session Matrix Context Parameters
$currentDoctorId = (int) ($_SESSION['user_id'] ?? 0);
$sessionOfficeId = (int) ($_SESSION['office_id'] ?? 0);

if ($currentDoctorId <= 0 || $sessionOfficeId <= 0) {
    Api::error('Doctor session or active clinic workspace context context missing.', 401);
    exit;
}

// Capture Current Date Context Boundaries (Level 1: Current Month and Year)
$currentYearMonthStart = date('Y-m-01'); // E.g., '2026-06-01'
$currentYearMonthEnd = date('Y-m-t');  // E.g., '2026-06-30'

try {
    /**
     * 3. Target Query Execution (Levels 1, 2, & 3)
     * Filters:
     * - Must be explicitly visible to doctors (`doctor_visiblity = 1`)
     * - Must fall within the active calendar month boundary (`static_month`)
     * - Must belong either to the current active office workspace context, 
     * OR matched directly to the individual doctor ID, OR configured globally ('all')
     */
    $sql = "
        SELECT id, static_label, target_type, target_id, chart_type, json_data 
        FROM `statics`
        WHERE `doctor_visiblity` = 1
          AND `static_month` BETWEEN ? AND ?
          AND (
               (`target_type` = 'office' AND (`target_id` = ? OR `target_id` = 'all'))
               OR 
               (`target_type` = 'doctor' AND (`target_id` = ? OR `target_id` = 'all'))
          )
    ";

    $params = [
        $currentYearMonthStart,
        $currentYearMonthEnd,
        $sessionOfficeId,
        $currentDoctorId
    ];

    $rawWidgets = $db->query($sql, $params);
    $formattedPayload = [];

    /**
     * 4. Granular In-Memory Filtering and Reduction Level Engine
     */
    foreach ($rawWidgets as $widget) {
        $rawDataString = trim($widget['json_data']);
        $decodedData = json_decode($rawDataString, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedData)) {
            continue; // Skip malformed rows safely
        }

        $filteredDataBlock = [];
        $targetType = $widget['target_type'];
        $targetId = $widget['target_id'];

        /**
         * Context Isolation Logic:
         * If the widget target is set to 'all', the json_data root keys represent 
         * entity reference keys (like office IDs or doctor IDs). We parse inward to isolate 
         * ONLY the specific subset matching the active context layout parameters.
         */
        if ($targetId === 'all') {
            if ($targetType === 'doctor' && isset($decodedData[$currentDoctorId])) {
                // If it maps out globally across multiple doctors, pull only this doctor's nested block
                $filteredDataBlock = $decodedData[$currentDoctorId];
            } elseif ($targetType === 'office' && isset($decodedData[$sessionOfficeId])) {
                // If it maps out globally across multiple offices, pull only this office's nested block
                $filteredDataBlock = $decodedData[$sessionOfficeId];
            }
        } else {
            // If the record is already structurally pre-targeted to this exact office or doctor, pass it directly
            $filteredDataBlock = $decodedData;
        }

        // Safe evaluation: only skip if the data block is completely missing (null) or an empty array
        if ($filteredDataBlock === null || $filteredDataBlock === []) {
            continue;
        }

        // 5. Shape output structure to serve frontend data consumers perfectly
        $formattedPayload[] = [
            'chart_type' => $widget['chart_type'],
            'label' => $widget['static_label'],
            'data' => $filteredDataBlock
        ];
    }

    // 6. Return Structured Envelope Sequence Response Map
    Api::success($formattedPayload);

} catch (Exception $e) {
    Api::error('Database metrics lookup execution context failure: ' . $e->getMessage(), 500);
}