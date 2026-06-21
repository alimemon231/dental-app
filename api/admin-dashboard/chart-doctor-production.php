<?php
/**
 * API: Fetch Production Captured By Doctor (Admin Scope)
 * Path: /api/admin/chart-doctor-production.php
 * Aggregates current month gross treatment production values grouped by assigned doctor provider.
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
    // 1. Establish Current Month Timestamp Boundaries (First Day to Last Day)
    $currentYearMonth = date('Y-m'); 
    $startDateStr = $currentYearMonth . '-01 00:00:00';
    $endDateStr   = date('Y-m-t') . ' 23:59:59';

    // 2. Execute Aggregated Query Grouped by Doctor Provider Join
    // Sums up `total_amount` from the `payments` ledger matching your context structure fields
    $sql = "SELECT 
                IFNULL(doc.name, 'Unassigned Provider') AS provider_name,
                SUM(p.total_amount) AS production_value
            FROM `payments` p
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            WHERE p.payment_date BETWEEN ? AND ?
            GROUP BY p.provider_id, doc.name
            ORDER BY production_value DESC";

    $rows = $db->query($sql, [$startDateStr, $endDateStr]);

    // 3. Separate data fields cleanly into parallel arrays for Chart.js inputs
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        $labels[] = $row['provider_name'];
        $values[] = (float)$row['production_value'];
    }

    // 4. Return Structured API Success Map Payload
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to generate doctor production metrics payload: ' . $e->getMessage(), 500);
}