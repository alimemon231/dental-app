<?php
/**
 * API: Fetch Pre-Auth Volume By Office Site (Admin Scope)
 * Path: /api/admin/chart-office-volumes.php
 * Aggregates current month pre-auth total financial value grouped by clinic location via cases relationship.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce explicit administrative or clinical management authorization parameters
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

try {
    // 1. Establish Current Month Timestamp Boundaries (First to Last Day)
    $currentYearMonth = date('Y-m'); 
    $startDateStr = $currentYearMonth . '-01 00:00:00';
    $endDateStr   = date('Y-m-t') . ' 23:59:59';

    // 2. Execute Aggregated Query Grouped by Office Location Join through Cases
    // Connects `pre-auth` (pa) -> `pre_auth_cases` (pac) -> `offices` (o)
    $sql = "SELECT 
                IFNULL(o.office_name, 'Unassigned Office') AS name_of_office,
                SUM(pa.price) AS total_value
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
            LEFT JOIN `offices` o ON pac.office_id = o.id
            WHERE pa.created_at BETWEEN ? AND ?
            GROUP BY pac.office_id, o.office_name
            ORDER BY total_value DESC";

    $rows = $db->query($sql, [$startDateStr, $endDateStr]);

    // 3. Separate data fields cleanly into parallel arrays for Chart.js inputs
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        $labels[] = $row['name_of_office'];
        $values[] = (float)$row['total_value'];
    }

    // 4. Return Structured API Success Map Payload
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to generate office metrics payload: ' . $e->getMessage(), 500);
}