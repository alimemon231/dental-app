<?php
/**
 * API: Fetch Pre-Auth Revenue Volumes by Doctor (Staff Office Scope)
 * Path: /api/staff/chart-preauth-doctor-volumes.php
 * Aggregates monthly pre-auth pipeline valuations grouped distinctly by provider name.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce explicit administrative or authorized workspace check matching your dashboard rules
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

// Extract Session Context Controls
$officeId = $_SESSION['office_id'] ?? null;
if (!$officeId) {
    Api::error('User session context missing office location assignment.', 400);
    exit;
}

try {
    // 1. Establish Current Month Timestamp Boundaries (First Day to Last Day)
    $currentYearMonth = date('Y-m'); 
    $startDateStr = $currentYearMonth . '-01 00:00:00';
    $endDateStr   = date('Y-m-t') . ' 23:59:59';

    // 2. Query distinct provider totals using explicit relational JOIN matches
    // Connects pre-auth items to cases (for office checking) and cases to users (for names)
    $sql = "SELECT u.`name` AS `doctor_name`, SUM(pa.`price`) AS `total_volume`
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.`case_id` = pac.`id`
            INNER JOIN `users` u ON pac.`doctor_id` = u.`user_id`
            WHERE pac.`office_id` = ?
              AND pa.`created_at` BETWEEN ? AND ?
            GROUP BY pac.`doctor_id`, u.`name`
            ORDER BY `total_volume` DESC";
            
    $rows = $db->query($sql, [$officeId, $startDateStr, $endDateStr]) ?: [];

    // 3. Separate metrics cleanly into parallel arrays for Chart.js inputs
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        // Fallback placeholder formatting for missing data states
        $labels[] = !empty($row['doctor_name']) ? trim($row['doctor_name']) : 'Unknown Provider';
        $values[] = (float)($row['total_volume'] ?? 0.0);
    }

    // 4. Return Structured API Response Payload Map
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to construct doctor pipeline performance map: ' . $e->getMessage(), 500);
}