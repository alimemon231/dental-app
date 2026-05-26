<?php
/**
 * GET api/emp-labs/all-doctors.php
 * Fetches all users with user_type 'doctor' who belong to the current user's office.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();


$currentOfficeId = $_SESSION['office_id'];

/**
 * 3. Fetch all doctors in THIS specific office.
 * We JOIN the users table with office_users to ensure the doctor 
 * belongs to the same office as the current staff member.
 */
$sql = "SELECT 
            u.user_id, 
            u.name, 
            u.email 
        FROM users u
        INNER JOIN office_users ou ON u.user_id = ou.user_id
        WHERE u.user_type = 'doctor' 
          AND ou.office_id = ?
          AND u.status = 'active'
        ORDER BY u.name ASC";

$doctors = $db->query($sql, [$currentOfficeId]);

// 4. Return results
Api::success($doctors);