<?php
/**
 * GET api/patients/lab-hist.php?p_id=XX
 * Fetches the entire multi-row Lab Case history timeline for a specific patient.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Role Authorization Gate (Allows Admin, Staff, and general Employee accounts)
$currentUser = $auth->user();
$userRole = $currentUser['role'] ?? '';

if ($userRole !== 'admin' && $userRole !== 'staff' && $userRole !== 'doctor') {
    Api::error('Unauthorized access. Operational privileges required.', 403);
    exit;
}

// 2. Validate input parameter
$patientId = (int)($_GET['p_id'] ?? 0);
if ($patientId <= 0) {
    Api::error('A valid patient ID (p_id) is required to fetch lab history.', 400);
    exit;
}

// Capture current location context for workspace security scoping rules
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

try {
    /**
     * 3. Complete Master Relational Query
     * Filters strictly by the matching patient ID foreign key (`l.p_id`).
     */
    $sql = "SELECT 
                l.*, 
                p.name AS patient_name,
                o.office_name, 
                u.name AS doctor_name,
                ct.name AS type_name,
                u_creator.name AS creator_name,
                u_editor.name AS editor_name,
                ls.name AS next_step_name,
                lp.name AS lab_partner_name
            FROM labs l
            LEFT JOIN patient p ON l.p_id = p.id
            LEFT JOIN offices o ON l.office_id = o.id
            LEFT JOIN users u ON l.provider = u.user_id
            LEFT JOIN users u_creator ON l.sent_by = u_creator.user_id
            LEFT JOIN users u_editor ON l.edited_by = u_editor.user_id
            LEFT JOIN case_type ct ON l.case_type = ct.id
            LEFT JOIN lab_steps ls ON l.next_visit = ls.id
            LEFT JOIN labs_patner lp ON l.lab_provider = lp.id
            WHERE l.p_id = ?
            ORDER BY l.id DESC";

    $rawRecords = $db->query($sql, [$patientId]) ?: [];
    $formattedRecords = [];

    /**
     * 4. Multi-Row Transformation Loop & Workspace Security Isolation
     */
    foreach ($rawRecords as $data) {
        // Workspace Security Restriction: Staff/Employees can only see history records from their active workspace clinic session
        if (($userRole === 'staff' || $userRole === 'employee') && $sessionOfficeId > 0 && (int)$data['office_id'] !== $sessionOfficeId) {
            continue; // Safely omit cross-clinic data footprint profiles
        }

        // Process and map clinical parameters matching your frontend loop structure format definitions
        $formattedRecords[] = [
            'id'               => $data['id'],
            'status'           => $data['status'],
            'office_name'      => $data['office_name'] ?: '—',
            'doctor_name'      => $data['doctor_name'] ?: '—',
            'type_name'        => $data['type_name'] ?: '—',
            'notes'            => $data['notes'] ?: 'No internal notes provided.',
            
            // Clinical template variables
            'impression_type'  => $data['impression_type'] ?: '—',
            'u_arch'           => $data['u_arch'] ?: '—',
            'l_arch'           => $data['l_arch'] ?: '—',
            'next_step_name'   => $data['next_step_name'] ?: '—',
            'lab_partner_name' => $data['lab_partner_name'] ?: '—',

            // Timeline Date Formats utilized by your JS layout variables
            'date_sent'         => $data['date_sent'] ? date('m/d/Y', strtotime($data['date_sent'])) : 'N/A',
            'date_sent_fmt'     => $data['date_sent'] ? date('m/d/Y', strtotime($data['date_sent'])) : '—',
            'date_received'     => $data['date_received'] ? date('m/d/Y', strtotime($data['date_received'])) : null,
            'date_received_fmt' => $data['date_received'] ? date('m/d/Y', strtotime($data['date_received'])) : '—',
            'date_scheduled'    => $data['date_scheduled'] ? date('m/d/Y', strtotime($data['date_scheduled'])) : null,
            'date_scheduled_fmt'=> $data['date_scheduled'] ? date('m/d/Y g:i A', strtotime($data['date_scheduled'])) : '—',
            'date_completed'    => $data['date_complete'] ? date('m/d/Y', strtotime($data['date_complete'])) : null,
            'date_completed_fmt'=> $data['date_complete'] ? date('m/d/Y', strtotime($data['date_complete'])) : '—',
            
            // Metadata Audit Footprint
            'created_by_name'  => $data['creator_name'] ?: ($data['sent_by'] ?? 'System'),
            'edited_by_name'   => $data['editor_name'] ?: '—',
            'edited_at'        => ($data['edited_at'] && $data['edited_at'] !== '0000-00-00 00:00:00') ? date('m/d/Y g:i A', strtotime($data['edited_at'])) : '—',
            'tracking_number'  => $data['id'] ?? 'N/A'
        ];
    }

    // 5. Success Envelope Response payload containing the multi-row record block array
    Api::success([
        'records' => $formattedRecords
    ], 'Patient lab history lifecycle pipeline data fetched successfully.');

} catch (Exception $e) {
    Api::error('Database retrieval or relation timeline mapping failed: ' . $e->getMessage(), 500);
}