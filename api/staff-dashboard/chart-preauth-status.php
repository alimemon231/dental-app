<?php
/**
 * API: Fetch Pre-Auth Status Matrix Share (Staff Office Scope)
 * Path: /api/staff/chart-preauth-status.php
 * Groups current month pre-auth workflows by status, scoped strictly to the user's office.
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

    // 2. Execute Aggregated Query JOINing pre_auth_cases to restrict rows by office_id
    $sql = "SELECT pa.`status`, COUNT(*) as `total_count`
            FROM `pre-auth` pa
            INNER JOIN `pre_auth_cases` pac ON pa.`case_id` = pac.`id`
            WHERE pac.`office_id` = ?
              AND pa.`created_at` BETWEEN ? AND ?
            GROUP BY pa.`status`";
            
    $rows = $db->query($sql, [$officeId, $startDateStr, $endDateStr]) ?: [];

    // 3. Define Clean Display Labels Map for UI presentation
    $statusDisplayMap = [
        'requested'  => 'Requested/Sent',
        'sent'       => 'Requested/Sent',
        'approved'   => 'Approved',
        'rejected'   => 'Rejected',
        'scheduled'  => 'Scheduled',
        'appealed'   => 'Appealed',
        'completed'  => 'Completed',
        'finalized'  => 'Completed'
    ];

    // Initialize temporary aggregation buckets to merge status variations cleanly
    $groupedData = [
        'Requested/Sent' => 0,
        'Approved'       => 0,
        'Rejected'       => 0,
        'Scheduled'      => 0,
        'Appealed'       => 0
    ];

    // 4. Process Database Rows into Unified Map Structs
    foreach ($rows as $row) {
        $rawStatus = strtolower(trim($row['status'] ?? ''));
        $count = (int)$row['total_count'];

        // Map raw status to user-friendly label; fallback to 'Requested/Sent' if unrecognized
        $uiLabel = $statusDisplayMap[$rawStatus] ?? 'Requested/Sent';

        if (isset($groupedData[$uiLabel])) {
            $groupedData[$uiLabel] += $count;
        } else {
            // Include unexpected mapped groupings cleanly into the response array structure if missing
            $groupedData[$uiLabel] = $count;
        }
    }

    // 5. Separate data cleanly into parallel arrays for Chart.js inputs
    $labels = [];
    $values = [];

    foreach ($groupedData as $label => $total) {
        $labels[] = $label;
        $values[] = $total;
    }

    // 6. Return Structured API Response Payload Map
    Api::success([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    Api::error('Failed to construct staff status metrics processing map: ' . $e->getMessage(), 500);
}