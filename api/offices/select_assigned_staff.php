<?php
/**
 * GET api/patients/list.php
 * Query params: limit, page, search
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403); // 403 is the standard "Forbidden" code
    exit;
}

if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Office ID is required.');
    exit;
}

$doctors = $db->query(
    "SELECT u.*
    FROM users u
    INNER JOIN office_users ou ON u.user_id = ou.user_id
    INNER JOIN offices o ON ou.office_id = o.id
    WHERE u.user_type = 'staff' 
    AND ou.office_id = ?",
    [$id]
);

Api::success($doctors);
?>