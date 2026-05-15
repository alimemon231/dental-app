<?php
/**
 * GET api/adm-labs/get-details.php
 * Fetches single lab case lifecycle details for the Admin View Modal.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Admin Security Check
$currentUser = $auth->user();
if ($currentUser['role'] !== 'admin') {
    Api::error('Unauthorized access.', 403);
    exit;
}

// 2. Validate ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Api::error('Invalid lab case ID.', 400);
    exit;
}

/**
 * 3. Fetch Comprehensive Data
 * Joins with users, offices, and case_type to get full names
 */
$sql = "SELECT 
    l.*, 
    o.office_name, 
    u.name AS doctor_name,
    ct.name AS type_name,
    u_creator.name AS creator_name,
    
    ls.name AS next_step_name,
    lp.name AS lab_partner_name
FROM labs l
LEFT JOIN offices o ON l.office_id = o.id
LEFT JOIN users u ON l.provider = u.user_id
LEFT JOIN users u_creator ON l.sent_by = u_creator.user_id
LEFT JOIN case_type ct ON l.case_type = ct.id
-- Map next_visit to the lab_steps table
LEFT JOIN lab_steps ls ON l.next_visit = ls.id
-- Map lab_provider to the labs_patner table
LEFT JOIN labs_patner lp ON l.lab_provider = lp.id
WHERE l.id = ?";

$data = $db->queryOne($sql, [$id,]);

if (!$data) {
    Api::error('Lab case not found.', 404);
    exit;
}

/**
 * 4. Data Sanitization & Formatting
 * We format dates here to make the JS logic cleaner.
 */
$formattedData = [
    'id'               => $data['id'],
    'p_name'           => $data['p_name'],
    'status'           => $data['status'],
    'office_name'      => $data['office_name'],
    'doctor_name'      => $data['doctor_name'],
    'type_name'        => $data['type_name'],
    'notes'            => $data['notes'] ?: 'No internal notes provided.',
    
    // Date Formats for the Table & Modal
    'date_sent'        => $data['date_sent'] ? date('m/d/Y', strtotime($data['date_sent'])) : 'N/A',
    'date_received'    => $data['date_received'] ? date('m/d/Y', strtotime($data['date_received'])) : null,
    'date_scheduled'   => $data['date_scheduled'] ? date('m/d/Y', strtotime($data['date_scheduled'])) : null,
    'date_completed'   => $data['date_complete'] ? date('m/d/Y', strtotime($data['date_complete'])) : null,
    
    // Additional Metadata for Modal
    
    'created_by_name'  => $data['sent_by'] ?? 'System',
    'tracking_number'  => $data['id'] ?? 'N/A'
];

// 5. Success Response
Api::success($formattedData, 'Lab details fetched successfully');