<?php
/**
 * API: Fetch Pre-Auth Pricing Generation Pipeline Timeline (Doctor Perspective)
 * Path: /api/doctor/chart-preauth-timeline.php
 * Generates daily total generated pre-authorization prices for the current month,
 * isolated to the logged-in doctor's records at the active office location.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce provider access parameter protections
if (!$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access permissions. Doctor role required.', 403);
    exit;
}

// Extract Session Context Controls for multi-key database targeting
$officeId = $_SESSION['office_id'] ?? null;
$doctorId = $_SESSION['user_id'] ?? null; // Maps to structural user session identification key

if (!$officeId || !$doctorId) {
    Api::error('User session context missing office location assignment or doctor identification.', 400);
    exit;
}

try {
    // 1. Establish Current Month Boundaries
    $currentYear = date('Y');
    $currentMonth = date('m');
    $daysInMonth = (int)date('t'); // Dynamic number of days in the current month (e.g., 28, 30, 31)

    $startDateStr = "{$currentYear}-{$currentMonth}-01 00:00:00";
    $endDateStr   = "{$currentYear}-{$currentMonth}-{$daysInMonth} 23:59:59";

    // 2. Pre-populate all days of the current month with 0 to ensure sequential line continuity
    $timelineMap = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayLabel = str_pad($day, 2, '0', STR_PAD_LEFT); // Formats to matching JS keys: '01', '02', '03'...
        $timelineMap[$dayLabel] = 0.0;
    }

    // 3. Execute Day-by-Day Pre-Auth Value Query
    // JOINS pre_auth_cases to safely filter metrics by office_id and doctor_id
    $sql = "SELECT 
                DATE_FORMAT(pa.`created_at`, '%d') AS `day_string`,
                SUM(pa.`price`) AS `daily_value`
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.`case_id` = pac.`id`
            WHERE pac.`office_id` = ?
              AND pac.`doctor_id` = ?
              AND pa.`created_at` BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(pa.`created_at`, '%d')";

    $rows = $db->query($sql, [$officeId, $doctorId, $startDateStr, $endDateStr]) ?: [];

    // 4. Map query records into matching daily buckets
    foreach ($rows as $row) {
        $dayKey = $row['day_string'];
        $dailyValue = (float)$row['daily_value'];
        
        if (isset($timelineMap[$dayKey])) {
            $timelineMap[$dayKey] = $dailyValue;
        }
    }

    // 5. Structure separate sequential arrays for seamless ingestion by Chart.js datasets
    $labels = [];
    $values = [];

    foreach ($timelineMap as $label => $totalValue) {
        $labels[] = $label;
        $values[] = $totalValue;
    }

    // 6. Return Structured Payload Response Engine
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to construct doctor daily pre-auth timeline: ' . $e->getMessage(), 500);
}