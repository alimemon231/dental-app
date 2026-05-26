<?php
/**
 * GET api/emp-pre-auth/list-approved.php
 * List only approved pre-auths for the logged-in office staff, including itemized treatments.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$currentUserId = $_SESSION['user_id'];
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.');
    exit;
}

try {
    // 1. Fetch parent pre-auth entries joined with patient, insurance, creator, and management approver data
    $sql = "SELECT 
                pa.id,
                pa.patient_id,
                pa.p_insurance_plan,
                pa.office_id,
                pa.created_at,
                pa.created_by,
                pa.status,
                pa.approved_by,
                pa.approval_expire_date,
                pat.name AS patient_name,
                pat.dob AS patient_dob,
                ins.name AS insurance_name,
                u_creator.name AS creator_name,
                u_approver.name AS approver_name
            FROM `pre-auth` pa
            INNER JOIN `patient` pat ON pa.patient_id = pat.id
            LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
            LEFT JOIN `users` u_creator ON pa.created_by = u_creator.user_id
            LEFT JOIN `users` u_approver ON pa.approved_by = u_approver.user_id
            WHERE pa.office_id = ? 
              AND pa.status = 'Approved'
              AND pa.appointment_date IS NULL
            ORDER BY pa.id DESC";

    $records = $db->query($sql, [$sessionOfficeId]);

    if (empty($records)) {
        Api::success([], 'Success');
        exit;
    }

    // Extract all parent record IDs to fetch child procedures efficiently in a single batch query
    $preAuthIds = array_column($records, 'id');
    $placeholders = implode(',', array_fill(0, count($preAuthIds), '?'));

    // 2. Query all linked procedures and tooth numbers for these approved records
    $procSql = "SELECT 
                    pap.pre_auth_id,
                    pap.procedure_id,
                    pap.tooth_number,
                    proc.name AS procedure_name
                FROM `pre_auth_procedures` pap
                INNER JOIN `procedures` proc ON pap.procedure_id = proc.id
                WHERE pap.pre_auth_id IN ($placeholders)";

    $allProcedures = $db->query($procSql, $preAuthIds);

    // Map child procedures arrays onto their respective parent IDs
    $proceduresGrouped = [];
    foreach ($allProcedures as $proc) {
        $proceduresGrouped[$proc['pre_auth_id']][] = [
            'procedure_id'   => $proc['procedure_id'],
            'procedure_name' => $proc['procedure_name'],
            'tooth_number'   => $proc['tooth_number']
        ];
    }

    // 3. Process, combine, and construct output data objects payload
    foreach ($records as &$r) {
        $r['time_ago'] = timeAgo($r['created_at']);
        $r['formatted_date'] = date('M d, Y', strtotime($r['created_at']));
        
        // Dynamic submission creator ownership string conversion
        if ((int)$r['created_by'] === (int)$currentUserId) {
            $r['submitted_by'] = 'You';
        } else {
            $r['submitted_by'] = $r['creator_name'] ?: 'System User';
        }

        // Attach child procedure objects list array segment
        $r['procedures_list'] = $proceduresGrouped[$r['id']] ?? [];

        // Build dynamic fallback strings to remain backwards-compatible with standard columns layout logic
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
        
        // Clean up unneeded relational cache references from final object representation
        unset($r['creator_name']);
    }
    unset($r); // Loop references safeguard breakdown reset

    Api::success($records, 'Success');

} catch (Exception $e) {
    Api::error('Relational approved-list data mapping failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert datetime to relative string representation
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    if (!$time) return '—';
    
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}