<?php
/**
 * API: Fetch User Assigned Offices
 * Path: /api/auth/user_offices.php
 * Method: GET
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Ensure the user is fully logged in before executing queries
$auth->requireAuth();

// Take user id from session
$currentUserId = $_SESSION['user_id'] ?? null;

if (!$currentUserId) {
    Api::error('User session context not found.', 401);
    exit;
}

try {
    // Join office_users bridge table to offices using office_id = id
    $sql = "SELECT 
                o.id, 
                o.office_name, 
                o.address 
            FROM `office_users` ou
            INNER JOIN `offices` o ON ou.office_id = o.id
            WHERE ou.user_id = ?
            ORDER BY o.office_name ASC";

    $offices = $db->query($sql, [$currentUserId]);

    // Return the assigned offices array back to your App.ajax wrapper
    Api::success($offices, 'Assigned locations retrieved successfully.');

} catch (Exception $e) {
    Api::error('Database lookup failed: ' . $e->getMessage());
}