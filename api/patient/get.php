<?php
/**
 * GET api/patient/get.php?id=X
 * Fetches comprehensive patient demographics, assigned office metadata, and creation/audit logs.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// 1. Force active session verification
$auth->requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { 
    Api::error('Patient ID is required.', 400); 
    exit; 
}

$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    Api::error('User session context not found.', 401);
    exit;
}

try {
    // 2. Fetch the logged-in user's permitted offices to ensure strict data scoping
    

    // 3. Collect comprehensive patient information, office details, and user audit names
    $patientSql = "SELECT 
                        p.id, 
                        p.name, 
                        p.dob, 
                        p.mobile, 
                        p.mobile AS phone,
                        p.email, 
                        p.address, 
                        p.office_id,
                        p.created_by,
                        p.edited_by,
                        p.edited_time,
                        o.office_name,
                        u1.name AS creator_name,
                        u2.name AS editor_name
                   FROM `patient` p
                   INNER JOIN `offices` o ON p.office_id = o.id
                   LEFT JOIN `users` u1 ON p.created_by = u1.user_id
                   LEFT JOIN `users` u2 ON p.edited_by = u2.user_id
                   WHERE p.id = ? 
                   LIMIT 1";

    $patient = $db->queryOne($patientSql, [$id]);

    // 4. Verification Check: Profile missing or deleted entirely
    if (!$patient) { 
        Api::error('Patient profile registry entry not found.', 404); 
        exit; 
    }

   

    // Return sanitized data object safely to frontend handlers
    Api::success($patient, 'Patient profile data retrieved successfully.');

} catch (Exception $e) {
    Api::error('Database lookup execution pipeline failed: ' . $e->getMessage());
}