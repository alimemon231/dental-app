<?php
/**
 * GET api/admin-pre-auth/get.php?id=XX
 * Global fetch for Admin lifecycle monitoring.
 */
require_once __DIR__ . '/../../includes/Auth.php';


$db = new Database();
$auth = new Auth($db);

// 1. Security Check: Admin only
$auth->requireAuth();
if ($auth->userRole() !== 'admin' && $auth->userRole() !== 'staff') {
    Api::error('Unauthorized. Admin access required.', 403);
    exit;
}

// 2. Validate Input
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Api::error('A valid Pre-Auth ID is required.');
    exit;
}

/**
 * 3. Detailed Query
 * We join:
 * - offices: To get 'office_name'
 * - users (as staff): To get the name of the creator ('staff_name')
 * - users (as admin): To get the name of the approver ('approver_name')
 * - insurance: To get the plan name
 * - procedures: To get the treatment name
 */
$sql = "
    SELECT 
        pa.*,
        o.office_name,
        u_staff.name AS staff_name,
        u_admin.name AS approver_name,
        ins.name AS insurance_name,
        proc.name AS procedure_name
    FROM `pre-auth` pa
    LEFT JOIN offices o ON pa.office_id = o.id
    LEFT JOIN users u_staff ON pa.created_by = u_staff.user_id
    LEFT JOIN users u_admin ON pa.approved_by = u_admin.user_id
    LEFT JOIN insurance ins ON pa.p_insurance_plan = ins.id
    LEFT JOIN procedures proc ON pa.treatment_type = proc.id
    WHERE pa.id = ?
";

$record = $db->queryOne($sql, [$id]);

if (!$record) {
    Api::error('Record not found.', 404);
    exit;
}

/**
 * 4. Data Preparation for JS
 * We ensure fields like 'notes' exist (even if NULL) and format dates.
 * Your JS expects 'p_insurance_id', which often refers to the member ID 
 * or the plan name in these summaries.
 */
$record['notes'] = $record['notes'] ?? 'No additional notes provided.';
$record['p_dob'] = date('M d, Y', strtotime($record['p_dob']));
$record['created_at_fmt'] = date('m/d/Y h:i A', strtotime($record['created_at']));

// Alias if your JS specifically looks for p_insurance_id (e.g. Member ID)
// If you don't have a specific column for Member ID, we send the plan name.
$record['p_insurance_id'] = $record['insurance_name']; 

// 5. Response
// Sent to App.ajax which maps response to 'data' in your JS onSuccess function
Api::success($record);