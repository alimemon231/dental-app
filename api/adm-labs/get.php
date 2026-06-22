<?php
/**
 * GET api/adm-labs/get-details.php
 * Fetches single lab case lifecycle details for the Admin View Modal with pricing parameters.
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
 * Helper function to calculate the multiplier value based on arch text format
 */
function calculateArchMultiplier($archValue) {
    $archValue = trim($archValue ?? '');
    
    // Rule 1: If no data, value is 0
    if ($archValue === '') {
        return 0;
    }
    
    // Rule 2: If value is 'Full', value is 1
    if (strcasecmp($archValue, 'Full') === 0) {
        return 1;
    }
    
    // Rule 3: If it contains comma-separated tooth numbers, count them
    $teethArray = explode(',', $archValue);
    // Filter out any accidental empty strings from split trailing commas
    $cleanTeethArray = array_filter(array_map('trim', $teethArray), function($val) {
        return $val !== '';
    });
    
    return count($cleanTeethArray);
}

/**
 * 3. Fetch Comprehensive Data
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
WHERE l.id = ?";

$data = $db->queryOne($sql, [$id]);

if (!$data) {
    Api::error('Lab case not found.', 404);
    exit;
}

/**
 * Dynamic Financial Math Execution for Singular Component View
 */
$uArchMultiplier = calculateArchMultiplier($data['u_arch']);
$lArchMultiplier = calculateArchMultiplier($data['l_arch']);
$itemBasePrice   = floatval($data['price'] ?? 0.00);

// Compute (u_arch + l_arch) * price Formula Structure
$totalRowValue   = ($uArchMultiplier + $lArchMultiplier) * $itemBasePrice;

/**
 * 4. Data Sanitization & Formatting
 * Explicitly includes all keys required by the frontend layout template.
 */
$formattedData = [
    'id'               => $data['id'],
    'p_name'           => $data['patient_name'] ?: '—',
    'status'           => $data['status'],
    'office_name'      => $data['office_name'] ?: '—',
    'doctor_name'      => $data['doctor_name'] ?: '—',
    'type_name'        => $data['type_name'] ?: '—',
    'notes'            => $data['notes'] ?: 'No internal notes provided.',
    
    // Clinical Parameters passed along to frontend template bindings
    'impression_type'  => $data['impression_type'] ?: '—',
    'u_arch'           => $data['u_arch'] ?: '—',
    'l_arch'           => $data['l_arch'] ?: '—',
    'next_step_name'   => $data['next_step_name'] ?: '—',
    'lab_partner_name' => $data['lab_partner_name'] ?: '—',

    // Financial parameters bundled for template mapping expressions
    'price'            => round($itemBasePrice, 2),
    'total_price'      => round($totalRowValue, 2),

    // Timeline Date Formats
    'date_sent'        => $data['date_sent'] ? date('m/d/Y', strtotime($data['date_sent'])) : 'N/A',
    'date_received'    => $data['date_received'] ? date('m/d/Y', strtotime($data['date_received'])) : null,
    'date_scheduled'   => $data['date_scheduled'] ? date('m/d/Y', strtotime($data['date_scheduled'])) : null,
    'date_completed'   => $data['date_complete'] ? date('m/d/Y', strtotime($data['date_complete'])) : null,
    
    // Additional Metadata and Audit Trail
    'created_by_name'  => $data['creator_name'] ?: ($data['sent_by'] ?? 'System'),
    'edited_by_name'   => $data['editor_name'] ?: '—',
    'edited_at'        => ($data['edited_at'] && $data['edited_at'] !== '0000-00-00 00:00:00') ? date('m/d/Y g:i A', strtotime($data['edited_at'])) : '—',
    'tracking_number'  => $data['id'] ?? 'N/A'
];

// 5. Success Response
Api::success($formattedData, 'Lab details fetched successfully');