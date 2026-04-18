<?php
/**
 * GET api/patients/get.php?id=5
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { Api::error('Patient ID is required.'); exit; }

$patient = $db->queryOne(
    "SELECT *
     FROM offices
     WHERE id = ?",
    [$id]
);

if (!$patient) { Api::error('Patient not found.', 404); exit; }

Api::success($patient);
