<?php
/**
 * GET api/emp-pre-auth/list.php
 * Lists unique pre-auth cases alongside their individual itemized treatments matching status constraints.
 * Filters out individual itemized lines that are not in the targeted actionable status pool.
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
    // 1. Fetch parent cases that contain AT LEAST ONE item matching our active target statuses
    // Using EXISTS eliminates duplicate case entries cleanly without requiring a heavy DISTINCT grouping pass
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
                WHERE pac.office_id = ? 
                  AND EXISTS (
                      SELECT 1 FROM `pre-auth` pa 
                      WHERE pa.case_id = pac.id 
                        AND pa.status IN ('Requested', 'Sent', 'Rejected', 'Appealed')
                  )
                ORDER BY pac.id DESC";

    $cases = $db->query($caseSql, [$sessionOfficeId]);

    if (empty($cases)) {
        Api::success([], 'Success');
        exit;
    }

    // Extract case IDs to process itemized procedures collectively
    $caseIds = array_column($cases, 'case_id');
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));

    // 2. Fetch ONLY individual itemized entries matching the target status constraints
    // Any line item with an unlisted status (e.g., 'Approved') is strictly omitted here
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
                        u.name AS creator_name
                    FROM `pre-auth` pa
                    INNER JOIN `procedures` proc ON pa.procedure_id = proc.id
                    LEFT JOIN `insurance` ins ON pa.p_insurance_plan = ins.id
                    LEFT JOIN `users` u ON pa.created_by = u.user_id
                    WHERE pa.case_id IN ($placeholders)
                      AND pa.status IN ('Requested', 'Sent', 'Rejected', 'Appealed')
                    ORDER BY pa.id ASC";

    $allItems = $db->query($itemizedSql, $caseIds);

    // Group matching rows by their parent case reference container
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
            'created_at'           => $item['created_at'],
            'created_by'           => (int)$item['created_by'],
            'submitted_by'         => ((int)$item['created_by'] === (int)$currentUserId) ? 'You' : ($item['creator_name'] ?: 'System User'),
            'time_ago'             => timeAgo($item['created_at']),
            'notes'                => $item['notes']
        ];
    }

    // 3. Construct response structure to maintain front-end loop structures precisely
    $finalResponseData = [];
    foreach ($cases as $c) {
        $caseId = $c['case_id'];
        
        // Retrieve filtered procedures matching the state list
        $proceduresList = $itemsGroupedByCase[$caseId] ?? [];
        
        // Skip formatting if no lines in this case matched the status rules
        if (empty($proceduresList)) {
            continue;
        }

        // Draw backward-compatible metadata from the first valid active line item
        $primaryItem = $proceduresList[0];

        // Compile comma-delimited fields for list dashboard summary row displays
        $names = array_column($proceduresList, 'procedure_name');
        $teeth = array_column($proceduresList, 'tooth_number');

        $finalResponseData[] = [
            'id'                 => $caseId, // Targets the envelope case identification index on the frontend
            'patient_id'         => (int)$c['patient_id'],
            'patient_name'       => $c['patient_name'],
            'patient_dob'        => $c['patient_dob'],
            'doctor_id'          => (int)$c['doctor_id'],
            'doctor_name'        => $c['doctor_name'] ?: 'Unassigned Doctor',
            'office_id'          => (int)$c['office_id'],
            
            // Legacy mappings drawing properties safely from the primary tracking instance
            'p_insurance_plan'   => $primaryItem['p_insurance_plan'],
            'insurance_name'     => $primaryItem['insurance_name'],
            'created_at'         => $primaryItem['created_at'],
            'time_ago'           => $primaryItem['time_ago'],
            'submitted_by'       => $primaryItem['submitted_by'],
            'status'             => $primaryItem['status'],

            // Joined visualization variables displaying active strings in table layout cells
            'procedure_name'     => implode(', ', $names),
            'tooth_numbers'      => implode(', ', $teeth),
            
            // Filtered array map elements processed by your custom JS loops
            'procedures_list'    => $proceduresList
        ];
    }

    Api::success($finalResponseData, 'Success');

} catch (Exception $e) {
    Api::error('Data stream mapping failure: ' . $e->getMessage(), 500);
}

/**
 * Helper: Convert datetime to relative string
 */
function timeAgo($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '—';
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}