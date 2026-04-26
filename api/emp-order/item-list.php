<?php
/**
 * GET api/patients/list.php
 * Query params: limit, page, search
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();




    $patients = $db->query(
        "SELECT *
         FROM items
         ORDER BY id",
        []
    );

Api::success($patients);
