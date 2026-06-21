<?php
/**
 * API: Fetch Pre-Auth Status Matrix Share (Admin Scope)
 * Path: /api/admin/chart-preauth-status.php
 * Groups current month pre-auth workflows efficiently by their status using SQL aggregations.
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

try {
    // 1. Establish Current Month Timestamp Boundaries (First Day to Last Day)
    $currentYearMonth = date('Y-m'); 
    $startDateStr = $currentYearMonth . '-01 00:00:00';
    $endDateStr   = date('Y-m-t') . ' 23:59:59';

    // 2. Execute Aggregated Query Grouped by Status using `pre_auths` table matching your schema
    $sql = "SELECT `status`, COUNT(*) as `total_count`
            FROM `pre-auth`
            WHERE `created_at` BETWEEN ? AND ?
            GROUP BY `status`";
            
    $rows = $db->query($sql, [$startDateStr, $endDateStr]);

    // 3. Define Clean Display Labels Map for UI presentation matching your JS chart ordering expectations
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

    // Initialize temporary aggregation buckets to merge variations (e.g., requested vs sent)
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

        // Map raw status to user-friendly label; group unexpected states into 'Requested/Sent' as a baseline fallback
        $uiLabel = $statusDisplayMap[$rawStatus] ?? 'Requested/Sent';

        if (isset($groupedData[$uiLabel])) {
            $groupedData[$uiLabel] += $count;
        } else {
            $groupedData[$uiLabel] = $count;
        }
    }

    // 5. Separate data cleanly into twin parallel arrays for Chart.js inputs
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
    Api::error('Failed to construct status metrics processing map: ' . $e->getMessage(), 500);
}