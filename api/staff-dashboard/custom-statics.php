<?php
/**
 * API: Fetch Dynamic Dashboard Statistics Metrics for Staff
 * Path: /api/staff-static/get-dashboard-metrics.php
 * Fetches, filters, maps, and isolates metric widgets visible to office staff.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Structural Access Gate Constraint (Enforce Staff or Admin role)
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Staff operational clearance required.', 403);
    exit;
}

// 2. Establish Active Session Context Parameters
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic workspace context missing.', 401);
    exit;
}

// Target exactly the first day of the current month (Format: Y-m-01)
$targetStaticMonth = date('Y-m-01');


    // 3. Fetch all active users/doctors linked to this specific office location
    // Used later to map and filter 'all' doctor datasets down to the current office scope
    $officeUsersSql = "
        SELECT ou.user_id, u.name 
        FROM `office_users` ou
        INNER JOIN `users` u ON ou.user_id = u.user_id
        WHERE ou.office_id = ?
    ";
    $officeUsersRaw = $db->query($officeUsersSql, [$sessionOfficeId]) ?: [];
    
    $localOfficeProviders = [];
    foreach ($officeUsersRaw as $user) {
        $localOfficeProviders[(int)$user['user_id']] = $user['name'];
    }

    /**
     * 4. Target Query Execution (Staff Visibility Level)
     * Optimization: Removed BETWEEN condition. Matches indexed first-of-month target directly.
     */
    $sql = "
        SELECT * FROM `statics`
        WHERE `staff_visiblity` = 1
          AND `static_month` = ?
    ";

    $rawWidgets = $db->query($sql, [$targetStaticMonth]);
    $formattedPayload = [];

    /**
     * 5. Granular In-Memory Context Matrix Reduction Engine
     */
    foreach ($rawWidgets as $widget) {
        $rawDataString = trim($widget['json_data']);
        $decodedData = json_decode($rawDataString, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedData)) {
            continue; // Skip structural anomalies safely
        }

        $filteredDataBlock = [];
        $targetType = $widget['target_type'];
        $targetId   = $widget['target_id'];
        $chartType  = $widget['chart_type'];
        $label      = $widget['static_label'];

        // SCENARIO A: Widget targets a global layout matrix ('all')
        if ($targetId === 'all') {
            if ($targetType === 'office') {
                // Global office dataset: isolate ONLY the current office's data node
                if (isset($decodedData[$sessionOfficeId])) {
                    $filteredDataBlock = $decodedData[$sessionOfficeId];
                }
            } 
            elseif ($targetType === 'doctor') {
                /**
                 * Global Doctor Data Node Matrix:
                 * Filter out and isolate only the data points corresponding to doctors 
                 * currently assigned to this staff member's home office workspace context.
                 */
                foreach ($localOfficeProviders as $provId => $provName) {
                    if (isset($decodedData[$provId])) {
                        // Map the metric value to the Doctor's legible profile name
                        $filteredDataBlock[$provName] = $decodedData[$provId];
                    }
                }

                // Per specifications: If it was a card asset mapping globally across doctors,
                // morph it into a bar chart distribution so staff can easily compare performance.
                if ($chartType === 'card' && !empty($filteredDataBlock)) {
                    $chartType = 'bar';
                }
            }
        } 
        // SCENARIO B: Widget is pre-targeted structurally to explicit individual IDs
        else {
            $targetIdInt = (int)$targetId;
            if ($targetType === 'office') {
                // Ensure this specific single office record matches the active session configuration
                if ($targetIdInt === $sessionOfficeId) {
                    $filteredDataBlock = $decodedData;
                }
            } 
            elseif ($targetType === 'doctor') {
                // Single doctor specific record: Verify if this doctor belongs to this office location
                if (isset($localOfficeProviders[$targetIdInt])) {
                    $filteredDataBlock = $decodedData;
                    // Append the doctor's clear text name directly into the workspace layout heading
                    $doctorName = $localOfficeProviders[$targetIdInt];
                    $label .= " - Dr. " . $doctorName;
                }
            }
        }

        // Safe evaluation: bypass formatting if no data matching this office scope remains
        if ($filteredDataBlock === null || $filteredDataBlock === []) {
            continue;
        }

        // 6. Build out response matrix
        $formattedPayload[] = [
            'chart_type' => $chartType,
            'label'      => $label,
            'data'       => $filteredDataBlock
        ];
    }

    // 7. Deliver Envelope Array Sequence Response Map
    Api::success($formattedPayload);

