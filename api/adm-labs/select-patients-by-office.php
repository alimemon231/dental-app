<?php
/**
 * api/adm-labs/select-patients-by-office.php
 * Fetches patients filtered by office_id for dropdown menus
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

    // Querying the patient table filtered by office_id
    // Adjust column names (like 'id', 'name', 'dob') if they differ in your patients table schema
    $sql = "SELECT id, name, dob 
            FROM patients 
            WHERE office_id = ? 
            ORDER BY name ASC";

    $patients = $db->query($sql, [$officeId])->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $patients
    ]);

} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred while fetching patients.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred.'
    ]);
}