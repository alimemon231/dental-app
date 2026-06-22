<?php
/**
 * GET api/staff-dashboard/chart-labs-doctor-volumes.php
 * Fetch aggregated lab revenue share volumes grouped by provider/doctor 
 * restricted strictly to the user's logged-in clinic office context.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Authorization Safeguard Controls
if (!$auth->hasRole('admin') && !$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

// 2. Extract and Validate Session Context Scope
$officeId = (int)($_SESSION['office_id'] ?? 0);
if ($officeId <= 0) {
    Api::error('Active clinic location scope context missing or invalid.', 400);
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

// 3. Establish Calendar Month Boundaries for Active Metric Performance
$currentYearMonth = date('Y-m'); 
$startDateStr     = $currentYearMonth . '-01 00:00:00';
$endDateStr       = date('Y-m-t') . ' 23:59:59';

try {
    /**
     * 4. Fetch Raw Labs matching current office scope and timeframe
     * JOINs onto users table to resolve assigned doctor names instantly
     */
    $sql = "SELECT 
                l.provider,
                COALESCE(u.name, 'Unknown Provider') AS doctor_name,
                l.u_arch,
                l.l_arch,
                l.price
            FROM labs l
            LEFT JOIN users u ON l.provider = u.user_id
            WHERE l.office_id = ? 
              AND l.date_sent BETWEEN ? AND ?";

    $labsRows = $db->query($sql, [$officeId, $startDateStr, $endDateStr]) ?: [];

    // Temporary storage hash matrix bucket to group value accumulators by provider
    $providerAggregations = [];

    // 5. Aggregate Financial Value Maps Row-by-Row
    foreach ($labsRows as $lab) {
        $doctorName = $lab['doctor_name'];
        
        $uArchMultiplier = calculateArchMultiplier($lab['u_arch']);
        $lArchMultiplier = calculateArchMultiplier($lab['l_arch']);
        $basePrice       = floatval($lab['price'] ?? 0.00);

        // Standard structural financial formula validation matrix: (u_arch + l_arch) * price
        $rowTotalValue   = ($uArchMultiplier + $lArchMultiplier) * $basePrice;

        // Populate and accumulate totals per descriptive key name
        if (!isset($providerAggregations[$doctorName])) {
            $providerAggregations[$doctorName] = 0.00;
        }
        $providerAggregations[$doctorName] += $rowTotalValue;
    }

    // 6. Split calculations hash array into Chart.js native payload sets
    $chartLabels = [];
    $chartValues = [];

    foreach ($providerAggregations as $doctor => $totalRevenue) {
        $chartLabels[] = $doctor;
        $chartValues[] = round($totalRevenue, 2);
    }

    // 7. Dispatch Response Array Envelopes
    Api::success([
        'labels' => $chartLabels,
        'values' => $chartValues
    ], 'Provider lab distribution analytics compiled successfully.');

} catch (Exception $e) {
    Api::error('Provider distribution calculation failure: ' . $e->getMessage(), 500);
}