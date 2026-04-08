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
    "SELECT id, patient_code, first_name, last_name,
            CONCAT(first_name, ' ', last_name) AS name,
            gender, date_of_birth, blood_group, referred_by,
            phone, email, city, address, allergies, notes, created_at
     FROM patients
     WHERE id = ? AND deleted_at IS NULL",
    [$id]
);

if (!$patient) { Api::error('Patient not found.', 404); exit; }

Api::success($patient);
