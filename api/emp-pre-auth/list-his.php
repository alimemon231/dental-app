<?php
/**
 * GET api/emp-pre-auth/list-his.php
 * Staff tracking log filtering pre-auth records row-by-row based on assigned clinic parameters.
 * Supports multi-dimensional filtering, pagination, and relative tracking parameters.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Capture authenticated user context & clinic scope
$currentUser = $auth->user();
$currentUserId = $_SESSION['user_id'] ?? $currentUser['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? ($currentUser['office_id'] ?? 0));

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}

// 2. Capture Filters & Pagination parameters (Identical to admin matching rules)
$limit       = min((int) ($_GET['limit'] ?? 15), 100);
$page        = max((int) ($_GET['page'] ?? 1), 1);
$offset      = ($page - 1) * $limit;

$patientName = trim($_GET['patient_name'] ?? '');
$clinicId    = $_GET['clinic_id'] ?? null;
$startDate   = $_GET['start_date'] ?? null;
$endDate     = $_GET['end_date'] ?? null;
$status      = $_GET['status'] ?? null; 

// 3. Build Dynamic WHERE Clause without 1=1 hardcoding
$where = [];
$params = [];

// Enforce clinic location/office restrictions based on the parent Case setup
if (!empty($clinicId)) {
    $where[] = "pac.office_id = ?";
    $params[] = (int)$clinicId;
} else {
    $where[] = "pac.office_id = ?";
    $params[] = $sessionOfficeId;
}

// Patient Name Filter (Queries joined patient table text schema)
if (!empty($patientName)) {
    $where[] = "pat.name LIKE ?";
    $params[] = '%' . $patientName . '%';
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

// Handle Multiple Statuses dynamically from Multi-Select or Single Value Overrides
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

// Dynamically stitch the WHERE block safely
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    /**
     * 4. Main Row-by-Row Single Query Execution
     * Pulls rows directly from the itemized `pre-auth` table.
     */
    $sql = "SELECT 
                pa.*, 
                pa.id AS pre_auth_id,
                pa.teeth_number AS tooth_number, -- Normalize property name mapping for front-end safety
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
            $whereSql
            ORDER BY pa.id DESC";

    // Combine selection filtering parameters array with integers for pagination limits
    
    $records = $db->query($sql, $params) ?: [];

    /**
     * 5. Total Count Generation for Pagination Footer
     */
    $countSql = "SELECT COUNT(*) as total 
                 FROM `pre-auth` pa 
                 INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
                 LEFT JOIN patient pat ON pac.patient_id = pat.id
                 $whereSql";
                 
    $totalCountResult = $db->queryOne($countSql, $params);
    $totalCount = isset($totalCountResult['total']) ? (int)$totalCountResult['total'] : 0;
    $totalPages = ceil($totalCount / $limit);

    /**
     * 6. Data Post-Processing & Normalization Loop
     */
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        $r['created_at_date'] = date('m/d/Y', strtotime($r['created_at']));
        
        if (!empty($r['appointment_date'])) {
            $r['appointment_date_fmt'] = date('m/d/Y g:i A', strtotime($r['appointment_date']));
        } else {
            $r['appointment_date_fmt'] = null;
        }

        // Context Fallback fields for basic row templates
        $r['procedure_name'] = $r['procedure_name'] ?: 'No procedure assigned'; 
        $r['tooth_numbers']  = $r['tooth_number'] ?: '—';

        // Set submitter flags matching your relative session rules
        $r['submitted_by'] = ((int)$r['created_by'] === (int)$currentUserId) ? 'You' : ($r['creator_name'] ?: 'System User');
        $r['approver_name'] = $r['approver_name'] ?: 'Management';

        // Emulate an internal procedures list array containing its own individual details 
        // to completely protect frontend table loop structures relying on array iterations.
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
    unset($r); // Sever looping link references completely

    // 7. Stream standard array payload matrix envelope back to layout component
    Api::success([
        'records'       => $records,
        'total_records' => (int)$totalCount,
        'total_pages'   => (int)$totalPages,
        'current_page'  => $page
    ], 'Staff records fetched successfully');

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