<?php
/**
 * API: Read/List Dynamic Statistics Widgets
 * Path: /adm-static/list.php
 * Handles custom-configured widget records with dynamic targets and structural name resolution.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Enforcer Layer
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

if (Api::method() !== 'GET') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture Filters & Pagination Inputs
$limit  = min((int)($_GET['limit'] ?? 20), 100);
$page   = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$monthFilter = trim($_GET['month'] ?? '');      // Expected format: "YYYY-MM"
$targetType  = trim($_GET['target_type'] ?? ''); // Filter by 'office' or 'doctor'

// 3. Construct Dynamic WHERE Filters Array Matrix
$where = ["1=1"];
$params = [];

// Handle Year-Month Partial Substring Matching (e.g., "2026-07%")
if (!empty($monthFilter)) {
    $where[] = "s.static_month LIKE ?";
    $params[] = $monthFilter . '%';
}

// Target Type Restriction Mapping
if (!empty($targetType)) {
    $where[] = "s.target_type = ?";
    $params[] = $targetType;
}

$whereSql = implode(" AND ", $where);

try {
    /**
     * 4. Main SQL Query Execution
     * Dynamically switches inline select fields to resolve target entity profiles 
     * based on the structural content type rules requested.
     */
    $sql = "SELECT 
                s.*,
                CASE 
                    WHEN s.target_id = 'all' THEN 'All Assigned Profiles'
                    WHEN s.target_type = 'office' THEN (
                        SELECT o.office_name FROM `offices` o WHERE o.id = s.target_id LIMIT 1
                    )
                    WHEN s.target_type = 'doctor' THEN (
                        SELECT u.name FROM `users` u WHERE u.user_id = s.target_id LIMIT 1
                    )
                    ELSE 'Unknown Entity Reference'
                END AS target_profile_name
            FROM `statics` s
            WHERE $whereSql
            ORDER BY s.id DESC
            LIMIT ? OFFSET ?";

    // Merge standard filtering datasets with absolute integer pagination boundaries
    $queryData = array_merge($params, [$limit, $offset]);
    $records = $db->query($sql, $queryData) ?: [];

    /**
     * 5. Generate Total Record Count for Pagination
     */
    $countSql = "SELECT COUNT(*) as total FROM `statics` s WHERE $whereSql";
    $totalCountResult = $db->queryOne($countSql, $params);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    /**
     * 6. Data Post-Processing and Human-Readable Normalization
     */
    foreach ($records as &$r) {
        // Human-friendly date translation (e.g., "2026-07-01" -> "July 2026")
        if (!empty($r['static_month'])) {
            $r['formatted_month'] = date('F Y', strtotime($r['static_month']));
        } else {
            $r['formatted_month'] = '—';
        }

        // Clean safety fallbacks for data schemas
        $r['static_label'] = $r['static_label'] ?: 'Unnamed Dynamic Metric Grid';
        $r['chart_type']   = strtoupper($r['chart_type'] ?: 'CARD');
        
        // Auto-decode JSON structures safely so frontend script arrays can read them directly without eval statements
        $r['decoded_dataset'] = json_decode($r['json_data'], true) ?: (object)[];
    }
    unset($r); // Sever looping link reference pointers cleanly

    // 7. Output Standardized Payload Response Envelope Layout
    Api::success([
        'records'       => $records,
        'total_records' => (int)$totalCount,
        'total_pages'   => (int)$totalPages,
        'current_page'  => $page
    ], 'Dynamic statistics widget profiles array synchronized successfully.');

} catch (Exception $e) {
    Api::error('Data stream query tracking execution exception: ' . $e->getMessage(), 500);
}