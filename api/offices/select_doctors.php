<?php
/**
 * GET api/patients/list.php
 * Query params: limit, page, search
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$doctors = $db->query(
    "SELECT u.*
    FROM users u
    LEFT JOIN office_users ou ON u.user_id = ou.user_id
    WHERE u.user_type = 'doctor' 
    AND ou.user_id IS NULL",
    []
);

Api::success($doctors);
?>