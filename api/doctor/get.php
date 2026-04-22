<?php
/**
 * GET api/patients/get.php?id=5
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('admin')) {
    Api::error('You are not authorized for this operation', 403); // 403 is the standard "Forbidden" code
    exit;
}


$id = (int)($_GET['id'] ?? 0);
if (!$id) { Api::error('Doctor ID is required.'); exit; }

$patient = $db->queryOne(
    "SELECT *
     FROM users
     WHERE user_id = ? and user_type = 'doctor'",
    [$id]
);

if (!$patient) { Api::error('Patient not found.', 404); exit; }
Api::success($patient);
