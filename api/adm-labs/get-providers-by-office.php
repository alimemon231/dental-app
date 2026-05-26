<?php
/**
 * api/adm-labs/get-providers-by-office.php
 * Fetches providers (doctors) filtered by office association via office_users pivot table
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/Database.php'; // Adjust path if needed to reach your includes folder

try {
    // Validate that office_id is provided
    if (!isset($_GET['office_id']) || empty($_GET['office_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: office_id']);
        exit;
    }

    $officeId = intval($_GET['office_id']);
    $db = new Database();

    // Joining users and office_users table, filtering by office_id and user_type
    // Note: 'user_id' as 'id' alias maintains compliance with dropdown mappings like d.user_id in your JS
    $sql = "SELECT u.user_id AS user_id, u.name 
            FROM users u
            INNER JOIN office_users ou ON u.user_id = ou.user_id
            WHERE ou.office_id = ? 
              AND u.user_type = 'doctor'
            ORDER BY u.name ASC";

    $providers = $db->query($sql, [$officeId]);

    echo json_encode([
        'success' => true,
        'data' => $providers
    ]);

} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred while fetching providers.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred.'
    ]);
}