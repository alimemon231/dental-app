<?php
/**
 * GET api/admin/pre-auth/list.php
 * Global list for Admin Monitoring rewritten for the new itemized multi-row schema.
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

// 2. Capture Filters & Pagination
$limit  = min((int) ($_GET['limit'] ?? 15), 100);
$page   = max((int) ($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null; // Admin can explicitly filter by a specific clinic
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; 

// 3. Build Dynamic WHERE Clause based on New Relational Structure
$where = ["1=1"];
$params = [];

// Patient Name Filter (Queries joined patient table text schema)
if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
}

// Clinic/Office Filter (Admins see all by default unless specific ID passed)
if (!empty($clinicId)) {
    $where[] = "pac.office_id = ?";
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
     * 4. Main Row-by-Row Query Execution
     * Tailored to pull itemized records matching the new relational structural schema maps.
     */
    $sql = "SELECT 
                pa.*, 
                pa.id AS pre_auth_id,
                pa.teeth_number AS tooth_number, 
                pac.id AS case_id,
                pac.office_id,
                pac.patient_id,
                pac.doctor_id,
                pat.name AS patient_name,
                pat.dob AS patient_dob,
                o.office_name, 
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
            WHERE $whereSql
            ORDER BY pa.id DESC
            LIMIT ? OFFSET ?";

    // Bind selection filtering arrays together with pagination parameter restrictions
    $queryData = array_merge($params, [$limit, $offset]);
    $records = $db->query($sql, $queryData) ?: [];

    /**
     * 5. Total Count Generation for Pagination Calculations
     */
    $countSql = "SELECT COUNT(*) as total 
                 FROM `pre-auth` pa 
                 INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
                 LEFT JOIN patient pat ON pac.patient_id = pat.id
                 WHERE $whereSql";
                 
    $totalCountResult = $db->queryOne($countSql, $params);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    /**
     * 6. Data Post-Processing & Normalization Loop
     * Translates structure parameters safely so legacy multi-row iteration components continue working.
     */
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        $r['created_at_date'] = date('m/d/Y', strtotime($r['created_at']));
        
        if (!empty($r['appointment_date'])) {
            $r['appointment_date_fmt'] = date('m/d/Y g:i A', strtotime($r['appointment_date']));
        } else {
            $r['appointment_date_fmt'] = null;
        }

        // Context Fallback setups
        $r['procedure_name'] = $r['procedure_name'] ?: 'No procedure assigned'; 
        $r['tooth_numbers']  = $r['tooth_number'] ?: '—';
        $r['approver_name']  = $r['approver_name'] ?: 'Management';

        // Emulate an internal itemized procedures array stack to protect frontend loops built for the old table formats
        $r['procedures_list'] = [
            [
                'pre_auth_id'    => (int)$r['pre_auth_id'],
                'procedure_id'   => (int)$r['procedure_id'],
                'procedure_name' => $r['procedure_name'],
                'tooth_number'   => $r['tooth_number'],
                'status'         => $r['status']
            ]
        ];
    }
    unset($r); // Sever looping link reference pointers cleanly

    // 7. Output standardised response envelope matrix layout
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
 * Helper: Convert standard datetime to context relative duration string
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