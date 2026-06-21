<?php
/**
 * API: Fetch Revenue Collections Trend Timeline (Doctor Perspective)
 * Path: /api/doctor/chart-revenue-timeline.php
 * Generates daily total treatment cost production for the current month,
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

    // 3. Execute Day-by-Day Production Value Query directly on the payments table
    // Sums up `total_amount` (Total Cost) for treatments booked under this doctor
    $sql = "SELECT 
                DATE_FORMAT(`payment_date`, '%d') AS `day_string`,
                SUM(`total_amount`) AS `daily_production`
            FROM `payments`
            WHERE `office_id` = ?
              AND `provider_id` = ?
              AND `payment_date` BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(`payment_date`, '%d')";

    $rows = $db->query($sql, [$officeId, $doctorId, $startDateStr, $endDateStr]) ?: [];

    // 4. Map query records into matching daily buckets
    foreach ($rows as $row) {
        $dayKey = $row['day_string'];
        $dailyProduction = (float)$row['daily_production'];
        
        if (isset($timelineMap[$dayKey])) {
            $timelineMap[$dayKey] = $dailyProduction;
        }
    }

    // 5. Structure separate sequential arrays for seamless ingestion by Chart.js datasets
    $labels = [];
    $values = [];

    foreach ($timelineMap as $label => $totalCost) {
        $labels[] = $label;
        $values[] = $totalCost;
    }

    // 6. Return Structured Payload Response Engine
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to construct doctor daily production cost trend line: ' . $e->getMessage(), 500);
}