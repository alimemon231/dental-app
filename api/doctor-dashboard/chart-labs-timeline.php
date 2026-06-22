<?php
/**
 * API: Fetch Doctor-Specific Lab Revenue Trends Timeline (Line Chart)
 * Path: /api/doctor/chart-labs-timeline.php
 * Generates daily total lab dynamic value production for the current month,
 * isolated to the logged-in doctor's records at the active office location context.
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

/**
 * Helper function to calculate the multiplier value based on arch text format
 */
function calculateArchMultiplier($archValue) {
    $archValue = trim($archValue ?? '');
    
    // Rule 1: If no data, value is 0
    if ($archValue === '') {
        return 0;
    }
    
    // Rule 2: If value is 'Full', value is 1
    if (strcasecmp($archValue, 'Full') === 0) {
        return 1;
    }
    
    // Rule 3: If it contains comma-separated tooth numbers, count them
    $teethArray = explode(',', $archValue);
    // Filter out any accidental empty strings from split trailing commas
    $cleanTeethArray = array_filter(array_map('trim', $teethArray), function($val) {
        return $val !== '';
    });
    
    return count($cleanTeethArray);
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
        $dayLabel = str_pad($day, 2, '0', STR_PAD_LEFT); // Formats to matching string keys: '01', '02', '03'...
        $timelineMap[$dayLabel] = 0.0;
    }

    // 3. Select all active lab records matching filters to execute math row-by-row in PHP
    $sql = "SELECT 
                DATE_FORMAT(`date_sent`, '%d') AS `day_string`,
                `u_arch`, 
                `l_arch`, 
                `price`
            FROM `labs`
            WHERE `office_id` = ?
              AND `provider` = ?
              AND `date_sent` BETWEEN ? AND ?";

    $rows = $db->query($sql, [$officeId, $doctorId, $startDateStr, $endDateStr]) ?: [];

    // 4. Map query records, execute structural arch formulas, and aggregate into daily buckets
    foreach ($rows as $row) {
        $dayKey = $row['day_string'];
        
        $uArchMultiplier = calculateArchMultiplier($row['u_arch']);
        $lArchMultiplier = calculateArchMultiplier($row['l_arch']);
        $basePrice       = floatval($row['price'] ?? 0.00);

        // Compute (u_arch + l_arch) * price dynamic formula metrics
        $rowTotalValue   = ($uArchMultiplier + $lArchMultiplier) * $basePrice;
        
        if (isset($timelineMap[$dayKey])) {
            $timelineMap[$dayKey] += $rowTotalValue;
        }
    }

    // 5. Structure separate sequential arrays for seamless ingestion by Chart.js datasets
    $labels = [];
    $values = [];

    foreach ($timelineMap as $label => $totalCost) {
        $labels[] = $label;
        $values[] = round($totalCost, 2); // Round clean values to double-float digits
    }

    // 6. Return Structured Payload Response Envelope
    Api::success([
        'labels' => $labels,
        'values' => $values
    ], 'Doctor daily lab distribution trend line structured successfully.');

} catch (Exception $e) {
    Api::error('Failed to construct doctor daily lab production trend line: ' . $e->getMessage(), 500);
}