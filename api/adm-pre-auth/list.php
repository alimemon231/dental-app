<?php
/**
 * GET api/admin/pre-auth/list.php
 * Global list for Admin Monitoring with multi-dimensional filtering and itemized child treatment arrays.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Role Check
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access. Admin privileges required.', 403);
    exit;
}

// 2. Capture Filters & Pagination (Kept intact)
$limit  = min((int) ($_GET['limit'] ?? 15), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null;
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; // Can be a string or an array

// 3. Build Dynamic WHERE Clause
$where = ["1=1"];
$params = [];

// FIXED: Patient Name Filter now correctly queries the joined 'patient' table text schema (pat.name)
if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

if (!empty($clinicId)) {
    $where[] = "pa.office_id = ?";
    $params[] = (int)$clinicId;
}

// Start Date Filter
if (!empty($startDate)) {
    $where[] = "DATE(pa.created_at) >= ?";
    $params[] = $startDate;
}

// End Date Filter
if (!empty($endDate)) {
    $where[] = "DATE(pa.created_at) <= ?";
    $params[] = $endDate;
}

// Handle Multiple Statuses
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
     * 4. Main Query
     * Includes full relational schemas for patients, offices, and audit managers
     */
    $sql = "SELECT 
                pa.*, 
                pat.name AS patient_name,
                pat.dob AS patient_dob,
                o.office_name, 
                u_creator.name AS creator_name,
                u_approver.name AS approver_name,
                u_editor.name AS editor_name,
                ins.name AS insurance_name
            FROM `pre-auth` pa
            LEFT JOIN patient pat ON pa.patient_id = pat.id
            LEFT JOIN offices o ON pa.office_id = o.id
            LEFT JOIN users u_creator ON pa.created_by = u_creator.user_id
            LEFT JOIN users u_approver ON pa.approved_by = u_approver.user_id
            LEFT JOIN users u_editor ON pa.edited_by = u_editor.user_id
            LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
            WHERE $whereSql
            ORDER BY pa.id DESC
            LIMIT ? OFFSET ?";

    // Merge pagination params with filter params
    $queryData = array_merge($params, [$limit, $offset]);
    $records = $db->query($sql, $queryData) ?: [];

    /**
     * 5. Total Count for Pagination
     * FIXED: Added the exact same patient JOIN block to prevent crash states during data lookups
     */
    $countSql = "SELECT COUNT(*) as total 
                 FROM `pre-auth` pa 
                 LEFT JOIN patient pat ON pa.patient_id = pat.id
                 WHERE $whereSql";
    $totalCount = $db->queryOne($countSql, $params)['total'];
    $totalPages = ceil($totalCount / $limit);

    // 6. Single-Batch Dynamic Child Procedures Mapping Block
    if (!empty($records)) {
        $preAuthIds = array_column($records, 'id');
        $procPlaceholders = implode(',', array_fill(0, count($preAuthIds), '?'));

        $procSql = "SELECT 
                        pap.pre_auth_id,
                        pap.procedure_id,
                        pap.tooth_number,
                        proc.name AS procedure_name
                    FROM `pre_auth_procedures` pap
                    INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
                    WHERE pap.pre_auth_id IN ($procPlaceholders)";

        $allProcedures = $db->query($procSql, $preAuthIds) ?: [];

        // Map itemized results to parent key locations
        $proceduresGrouped = [];
        foreach ($allProcedures as $proc) {
            $proceduresGrouped[$proc['pre_auth_id']][] = [
                'procedure_id'   => $proc['procedure_id'],
                'procedure_name' => $proc['procedure_name'],
                'tooth_number'   => $proc['tooth_number']
            ];
        }

        // 7. Data Post-Processing Loop
        foreach ($records as &$r) {
            $r['time_ago'] = timeAgo($r['created_at']);
            $r['created_at_date'] = date('m/d/Y', strtotime($r['created_at']));
            
            if (!empty($r['appointment_date'])) {
                $r['appointment_date_fmt'] = date('m/d/Y g:i A', strtotime($r['appointment_date']));
            } else {
                $r['appointment_date_fmt'] = null;
            }

            // Establish full internal context parameters for management tables UI
            $r['procedures_list'] = $proceduresGrouped[$r['id']] ?? [];

            if (!empty($r['procedures_list'])) {
                $names = [];
                $teeth = [];
                foreach ($r['procedures_list'] as $item) {
                    $names[] = $item['procedure_name'];
                    $teeth[] = $item['tooth_number'];
                }
                $r['procedure_name'] = implode(', ', $names);
                $r['tooth_numbers']  = implode(', ', $teeth);
            } else {
                $r['procedure_name'] = 'No procedures assigned';
                $r['tooth_numbers']  = '—';
            }
        }
        unset($r); // Break loop reference safely
    }

    // 8. Output exact response envelope structure matching JS expectation
    Api::success([
        'records'       => $records,
        'total_records' => (int)$totalCount,
        'total_pages'   => (int)$totalPages,
        'current_page'  => $page
    ], 'Global records fetched successfully');

} catch (Exception $e) {
    Api::error('Data stream mapping failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert datetime to relative string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}