<?php
/**
 * GET /admin-pre-auth/list.php
 * Unified Admin workspace pipeline tracker. Groups itemized pre-authorizations 
 * into row-spanned parent cases with robust global filtering matching front-end layouts.
 * * FIX: Filters applied directly to item arrays so non-matching rows within a case are discarded.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Enforcement Override
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Capture and Sanitize Incoming Filter Parameters
$limit   = 100; 
$page    = max((int) ($_GET['page'] ?? 1), 1);
$offset  = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$caseId      = trim($_GET['case_id'] ?? '');
$officeId    = $_GET['office_id'] ?? null; 
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['endDate'] ?? null;
$status      = $_GET['status'] ?? null; 

// 3. Build Dynamic WHERE Conditions for Master Group Isolation
$where = ["1=1"];
$params = [];

if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

if (!empty($caseId)) {
    $where[] = "pac.id = ?";
    $params[] = (int)$caseId;
}

if (!empty($officeId)) {
    $where[] = "pac.office_id = ?";
    $params[] = (int)$officeId;
}

if (!empty($startDate)) {
    $where[] = "DATE(pa.created_at) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "DATE(pa.created_at) <= ?";
    $params[] = $endDate;
}

if (!empty($status)) {
    if (is_array($status)) {
        $placeholders = implode(',', array_fill(0, count($status), '?'));
        $where[] = "pa.status IN ($placeholders)";
        foreach ($status as $s) {
            $params[] = $s;
        }
    } else {
        $where[] = "pa.status = ?";
        $params[] = $status;
    }
}

$whereSql = implode(" AND ", $where);

try {
    /**
     * 4. Query Total Master Cases Matching Filters (For Pagination Calculations)
     */
    $countSql = "SELECT COUNT(DISTINCT pac.id) as total 
                 FROM `pre_auth_cases` pac
                 INNER JOIN `pre-auth` pa ON pa.case_id = pac.id
                 LEFT JOIN patient pat ON pac.patient_id = pat.id
                 WHERE $whereSql";
                 
    $totalCountResult = $db->queryOne($countSql, $params);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    /**
     * 5. Fetch Paginated Case Sub-structures
     */
    $caseSql = "SELECT DISTINCT pac.id AS case_pk_id
                FROM `pre_auth_cases` pac
                INNER JOIN `pre-auth` pa ON pa.case_id = pac.id
                LEFT JOIN patient pat ON pac.patient_id = pat.id
                WHERE $whereSql
                ORDER BY pac.id DESC
                LIMIT ? OFFSET ?";

    $caseQueryData = array_merge($params, [$limit, $offset]);
    $paginatedCases = $db->query($caseSql, $caseQueryData) ?: [];

    $records = [];

    if (!empty($paginatedCases)) {
        $caseIds = array_column($paginatedCases, 'case_pk_id');
        $casePlaceholders = implode(',', array_fill(0, count($caseIds), '?'));

        /**
         * 6. Pull Detailed Itemized Records
         * FIX: We change `WHERE pac.id IN (...)` to `WHERE pac.id IN (...) AND $whereSql` 
         * and append the filter parameters a second time to drop unmatched procedures.
         */
        $detailSql = "SELECT 
                        pa.*, 
                        pa.id AS pre_auth_id,
                        pa.teeth_number AS tooth_number, 
                        pa.price AS procedure_price, -- Changed: Pulling transaction tracking cost from pre-auth column metrics
                        pac.id AS id, 
                        pac.office_id,
                        pac.patient_id,
                        pac.doctor_id,
                        pat.name AS patient_name,
                        pat.dob AS patient_dob,
                        o.office_name AS clinic_name, 
                        u_creator.name AS creator_name,
                        u_approver.name AS approver_name,
                        u_editor.name AS editor_name,
                        ins.name AS insurance_name,
                        doc.name AS doctor_name,
                        proc.name AS procedure_name
                    FROM `pre-auth` pa
                    INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
                    LEFT JOIN patient pat ON pac.patient_id = pat.id
                    LEFT JOIN offices o ON pac.office_id = o.id
                    LEFT JOIN users u_creator ON pa.created_by = u_creator.user_id
                    LEFT JOIN users u_approver ON pa.approved_by = u_approver.user_id
                    LEFT JOIN users u_editor ON pa.edited_by = u_editor.user_id
                    LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
                    LEFT JOIN users doc ON pac.doctor_id = doc.user_id
                    LEFT JOIN procedures proc ON pa.procedure_id = proc.id
                    WHERE pac.id IN ($casePlaceholders) AND $whereSql
                    ORDER BY pac.id DESC, pa.id ASC";

        // Merge caseIds together with filter parameters for execution array binding
        $detailParams = array_merge($caseIds, $params);
        $rawDetails = $db->query($detailSql, $detailParams) ?: [];

        /**
         * 7. Grouping Pipeline Transformation Engine
         */
        $grouped = [];
        foreach ($rawDetails as $row) {
            $cId = $row['id'];
            
            if (!isset($grouped[$cId])) {
                $grouped[$cId] = [
                    'id'               => (int)$row['id'],
                    'office_id'        => (int)$row['office_id'],
                    'patient_id'       => (int)$row['patient_id'],
                    'doctor_id'        => (int)$row['doctor_id'],
                    'patient_name'     => $row['patient_name'],
                    'patient_dob'      => $row['patient_dob'] ? date('m/d/Y', strtotime($row['patient_dob'])) : '—',
                    'clinic_name'      => $row['clinic_name'] ?: '—',
                    'doctor_name'      => $row['doctor_name'] ?: 'Unassigned',
                    'insurance_name'   => $row['insurance_name'] ?: 'Private Pay / Cash',
                    'creator_name'     => $row['creator_name'] ?: 'System',
                    'approver_name'    => $row['approver_name'] ?: 'Management',
                    'editor_name'      => $row['editor_name'] ?: '—',
                    'appointment_date' => $row['appointment_date'],
                    'notes'            => $row['notes'],
                    'time_ago'         => timeAgo($row['created_at']),
                    'procedures_list'  => []
                ];
            }

            $grouped[$cId]['procedures_list'][] = [
                'pre_auth_id'    => (int)$row['pre_auth_id'],
                'procedure_id'   => (int)$row['procedure_id'],
                'procedure_name' => $row['procedure_name'] ?: 'No procedure assigned',
                'procedure_price' => $row['procedure_price'] ?: '0', // Pulls matched tracking value cleanly
                'tooth_number'   => $row['tooth_number'] ?: '—',
                'status'         => $row['status'] ?: 'Requested',
                'time_ago'       => timeAgo($row['created_at'])
            ];
        }
        
        $records = array_values($grouped);
    }

    // 8. Return Response Envelope
    Api::success([
        'records'       => $records,
        'total_records' => $totalCount,
        'total_pages'   => $totalPages,
        'current_page'  => $page
    ], 'Global matrix tracking records filtered successfully');

} catch (Exception $e) {
    Api::error('Data stream matrix mapping failure: ' . $e->getMessage(), 500);
}

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