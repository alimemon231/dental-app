<?php
/**
 * GET api/emp-pre-auth/patients-list.php
 * Fetches all active patients under the current active session's office scope.
 * Tailored explicitly to provide real-time search payloads to Select2.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force active session verification
$auth->requireAuth();

// 1. Enforce strict active office scope filter parameters
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session.', 400);
    exit;
}

try {
    // 2. Fetch optimized, lean columns for all office entries
    $recordsSql = "SELECT 
                        p.id, 
                        p.name, 
                        p.dob,
                        p.mobile
                   FROM `patient` p
                   WHERE p.office_id = ?
                   ORDER BY p.name ASC";

    $records = $db->query($recordsSql, [$sessionOfficeId]);

    // 3. Format clean structured API response containing all data rows straight
    $responseData = [
        "data" => $records // Aligned directly with custom mapping targets inside your JS script
    ];

    Api::success($responseData, 'Office patient directory lookup executed successfully.');

} catch (Exception $e) {
    Api::error('Failed to retrieve office patient index directory: ' . $e->getMessage(), 500);
}