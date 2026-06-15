<?php
/**
 * GET api/emp-pre-auth/list-scheduled.php
 * Lists unique pre-auth cases alongside their individual itemized treatments matching the 'Scheduled' status constraints.
 * Filters out individual itemized lines that are not scheduled, outside location scopes, or filter limits.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$currentUserId = $_SESSION['user_id'];
$sessionOfficeId = $_SESSION['office_id'];

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}

// 1. Capture incoming filter criteria and pagination arrays matching the JS parameters exactly
$limit       = 200;
$page        = max((int)($_GET['page'] ?? 1), 1);
$offset      = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$caseIdFilter = trim($_GET['case_id'] ?? '');

// 2. Build Dynamic WHERE Conditions for the Case Parent records
$whereClauses = ["pac.office_id = ?"];
$whereParams  = [$sessionOfficeId];

// If patient name filter string is provided
if (!empty($patientName)) {
    $whereClauses[] = "pat.name LIKE ?";
    $whereParams[]  = '%' . $patientName . '%';
}

// If specific Case ID is filtered (Checks explicit matching integers)
if (!empty($caseIdFilter) && is_numeric($caseIdFilter)) {
    $whereClauses[] = "pac.id = ?";
    $whereParams[]  = (int)$caseIdFilter;
}

// Restrict status pooling to 'Scheduled' base rules while acknowledging dropdown criteria overrides
$statusTargetList = ['Scheduled'];
if (!empty($statusFilter)) {
    // If user explicitly searches for a non-scheduled status on the scheduled board panel, return empty layout envelope cleanly
    if ($statusFilter !== 'Scheduled') {
        Api::success([
            'records'       => [],
            'total_records' => 0,
            'total_pages'   => 0,
            'current_page'  => $page
        ], 'Success');
        exit;
    }
}

$statusPlaceholders = implode(',', array_fill(0, count($statusTargetList), '?'));
$existsSubQueryCondition = "EXISTS (
    SELECT 1 FROM `pre-auth` pa 
    WHERE pa.case_id = pac.id 
      AND pa.status IN ($statusPlaceholders)
      AND pa.appointment_date IS NOT NULL
)";

$whereClauses[] = $existsSubQueryCondition;
foreach ($statusTargetList as $st) {
    $whereParams[] = $st;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

try {
    // 3. Count matching structural records cleanly to pass pagination envelopes down
    $countSql = "SELECT COUNT(DISTINCT pac.id) AS total
                 FROM `pre_auth_cases` pac
                 INNER JOIN `patient` pat ON pac.patient_id = pat.id
                 $whereSql";
    
    $totalCountResult = $db->queryOne($countSql, $whereParams);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    // 4. Fetch the paginated subset of parent cases containing scheduled line records
    $caseSql = "SELECT 
                    pac.id AS case_id,
                    pac.patient_id,
                    pac.doctor_id,
                    pac.office_id,
                    pat.name AS patient_name,
                    pat.dob AS patient_dob,
                    doc.name AS doctor_name
                FROM `pre_auth_cases` pac
                INNER JOIN `patient` pat ON pac.patient_id = pat.id
                LEFT JOIN `users` doc ON pac.doctor_id = doc.user_id
                $whereSql
                ORDER BY pac.id DESC
                LIMIT ? OFFSET ?";

    // Append limits securely onto standard dynamic parameters matrices
    $queryParams = $whereParams;
    $queryParams[] = $limit;
    $queryParams[] = $offset;

    $cases = $db->query($caseSql, $queryParams) ?: [];

    if (empty($cases)) {
        Api::success([
            'records'       => [],
            'total_records' => 0,
            'total_pages'   => 0,
            'current_page'  => $page
        ], 'Success');
        exit;
    }

    // Extract precise targeted Case IDs subset to gather procedure records collections
    $caseIds = array_column($cases, 'case_id');
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));

    // 5. Fetch ONLY individual itemized entries matching the 'Scheduled' constraints
    $itemizedSql = "SELECT 
                        pa.id AS pre_auth_id,
                        pa.case_id,
                        pa.procedure_id,
                        pa.teeth_number AS tooth_number,
                        pa.p_insurance_plan,
                        pa.appointment_date,
                        pa.created_at,
                        pa.created_by,
                        pa.status,
                        pa.notes,
                        proc.name AS procedure_name,
                        ins.name AS insurance_name,
                        u.name AS creator_name,
                        u_app.name AS approver_name
                    FROM `pre-auth` pa
                    INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
                    LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
                    LEFT JOIN `users` u ON pa.created_by = u.user_id
                    LEFT JOIN `users` u_app ON pa.approved_by = u_app.user_id
                    WHERE pa.case_id IN ($placeholders)
                      AND pa.status IN ($statusPlaceholders)
                      AND pa.appointment_date IS NOT NULL
                    ORDER BY pa.appointment_date ASC, pa.id ASC";

    // Combine runtime case query limits with target lookup status strings cleanly
    $itemizedParams = array_merge($caseIds, $statusTargetList);
    $allItems = $db->query($itemizedSql, $itemizedParams) ?: [];

    // Group structural treatments payload lists relative to matching Case references
    $itemsGroupedByCase = [];
    foreach ($allItems as $item) {
        $itemsGroupedByCase[$item['case_id']][] = [
            'pre_auth_id'          => (int)$item['pre_auth_id'],
            'procedure_id'         => (int)$item['procedure_id'],
            'procedure_name'       => $item['procedure_name'],
            'tooth_number'         => $item['tooth_number'],
            'p_insurance_plan'     => (int)$item['p_insurance_plan'],
            'insurance_name'       => $item['insurance_name'] ?: 'No Insurance',
            'status'               => $item['status'],
            'appointment_date'     => $item['appointment_date'],
            'appointment_date_fmt' => !empty($item['appointment_date']) ? date('M d, Y h:i A', strtotime($item['appointment_date'])) : '—',
            'created_at'           => $item['created_at'],
            'created_by'           => (int)$item['created_by'],
            'submitted_by'         => ((int)$item['created_by'] === (int)$currentUserId) ? 'You' : ($item['creator_name'] ?: 'System User'),
            'approver_name'        => $item['approver_name'] ?: 'Management',
            'time_ago'             => timeAgo($item['created_at']),
            'formatted_date'       => date('M d, Y', strtotime($item['created_at'])),
            'notes'                => $item['notes']
        ];
    }

    // 6. Compile data rows structurally, maintaining matching arrays for frontend js parsing loops
    $finalResponseData = [];
    foreach ($cases as $c) {
        $caseId = $c['case_id'];
        $proceduresList = $itemsGroupedByCase[$caseId] ?? [];
        
        // Safety skip checks mapping active array profiles cleanly
        if (empty($proceduresList)) {
            continue;
        }

        $primaryItem = $proceduresList[0];
        $names = array_column($proceduresList, 'procedure_name');
        $teeth = array_column($proceduresList, 'tooth_number');

        $finalResponseData[] = [
            'id'                 => $caseId,
            'patient_id'         => (int)$c['patient_id'],
            'patient_name'       => $c['patient_name'],
            'patient_dob'        => $c['patient_dob'],
            'doctor_id'          => (int)$c['doctor_id'],
            'doctor_name'        => $c['doctor_name'] ?: 'Unassigned Doctor',
            'office_id'          => (int)$c['office_id'],
            
            // Core references matching initial template mapping expectations
            'p_insurance_plan'   => $primaryItem['p_insurance_plan'],
            'insurance_name'     => $primaryItem['insurance_name'],
            'appointment_date'   => $primaryItem['appointment_date'],
            'appointment_date_fmt' => $primaryItem['appointment_date_fmt'],
            'created_at'         => $primaryItem['created_at'],
            'time_ago'           => $primaryItem['time_ago'],
            'formatted_date'     => $primaryItem['formatted_date'],
            'submitted_by'       => $primaryItem['submitted_by'],
            'approver_name'      => $primaryItem['approver_name'],
            'status'             => $primaryItem['status'],

            // Imploded structural presentation mapping layouts
            'procedure_name'     => implode(', ', $names),
            'tooth_numbers'      => implode(', ', $teeth),
            'procedures_list'    => $proceduresList
        ];
    }

    // 7. Returns response schema wrapped into page indexing properties
    Api::success([
        'records'       => $finalResponseData,
        'total_records' => (int)$totalCount,
        'total_pages'   => (int)$totalPages,
        'current_page'  => $page
    ], 'Success');

} catch (Exception $e) {
    Api::error('Scheduled data stream mapping failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert standard datetime into historical scale tracking variants
 */
function timeAgo($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '—';
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}