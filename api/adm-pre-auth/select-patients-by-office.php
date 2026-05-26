<?php
/**
 * GET select-patients-by-office.php
 * Contextually fetches patient lookup parameters isolated by selected clinic office ID
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Extract and sanitize incoming office parameter metric
$officeId = isset($_GET['office_id']) ? trim($_GET['office_id']) : '';

if (empty($officeId)) {
    Api::error("Missing required 'office_id' operational routing parameters.", 400);
}

// 2. Fetch Patients pool tracking specifically to the office location ID
$patients = $db->query(
    "SELECT id, name, dob FROM patient WHERE office_id = :office_id ORDER BY name ASC",
    [':office_id' => $officeId]
);

// 3. Output payload array using standard API architecture
Api::success($patients);