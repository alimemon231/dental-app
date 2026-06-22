<?php
/**
 * GET admin-load-lab-dropdown.php
 * Combined Single Endpoint Data Aggregator for Lab Case Dropdown Fields
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Fetch Lab Partners (Corresponds to /labs-settings/list.php)
$lab_partners = $db->query("SELECT id, name FROM labs_patner ORDER BY name ASC");

// 2. Fetch Next Visit Steps / Procedures (Corresponds to /lab-steps/list.php)
$lab_steps = $db->query("SELECT id, name FROM lab_steps ORDER BY name ASC");

// 3. Fetch Active Clinic Office Locations
$offices = $db->query("SELECT id, office_name AS name FROM offices ORDER BY office_name ASC");

// 4. Fetch Case Types along with target metadata (Corresponds to /lab-cases/list.php)
// Including the 'target' field is critical so the JS tooth chart vs arch toggle functions properly
$case_types = $db->query("SELECT * FROM case_type ORDER BY name ASC");

// 5. Output structure as a clean unified JSON response array
Api::success([
    'lab_partners' => $lab_partners,
    'lab_steps'    => $lab_steps,
    'offices'      => $offices,
    'case_types'   => $case_types
]);