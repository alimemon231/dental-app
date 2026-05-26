<?php
/**
 * GET admin-load-pre-auth-dropdown.php
 * Combined Single Endpoint Data Aggregator for Pre-Auth Dropdown Fields
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();


    

    // 2. Fetch Insurance Carriers
    $insurances = $db->query("SELECT id, name FROM insurance ORDER BY name ASC");

    // 3. Fetch Treatment Procedures List
    $procedures = $db->query("SELECT id, name FROM procedures ORDER BY name ASC");

    // 4. Fetch Active Clinic Office Locations
    $offices = $db->query("SELECT id, office_name AS name FROM offices ORDER BY office_name ASC");

    // 5. Output structure as a clean unified array
    Api::success([
        'insurances' => $insurances,
        'procedures' => $procedures,
        'offices'    => $offices
    ]);

