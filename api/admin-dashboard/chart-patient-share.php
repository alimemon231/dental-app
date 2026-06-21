<?php
/**
 * API: Fetch Active Patient Volume Share By Clinic Site (Admin Scope)
 * Path: /api/admin/chart-patient-share.php
 * Performance-optimized query that counts total patient volumes grouped by clinic office location.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce explicit administrative or clinical/billing management authorization parameters
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

try {
    // Execute an optimized aggregation query grouping total patients by office
    // Using the exact table names 'patient' and 'offices' matching your core directory schema
    $sql = "SELECT 
                IFNULL(o.office_name, 'Unassigned Office') AS name_of_office,
                COUNT(p.id) AS patient_count
            FROM `patient` p
            INNER JOIN `offices` o ON p.office_id = o.id
            GROUP BY p.office_id, o.office_name
            ORDER BY patient_count DESC";

    $rows = $db->query($sql);

    // Separate records into the two distinct tracking arrays your Chart.js ajax loop demands
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        $labels[] = $row['name_of_office'];
        $values[] = (int)$row['patient_count'];
    }

    // Return the formatted object array payload to match fronted expectations cleanly
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to aggregate patient volume metrics: ' . $e->getMessage(), 500);
}