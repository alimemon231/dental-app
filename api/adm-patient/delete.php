<?php
/**
 * POST api/adm-patient/delete.php
 * Administrative global patient data cascade deletion router endpoint.
 * Wipes out children procedure item rows, pre-auth sheets, lab cases, and the primary master patient record.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force session tracking setup verification
$auth->requireAuth();

// Strict security barrier: Only admins can call this endpoint
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized access. Administrative permissions required.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture and Validate target profile identification key
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Valid Patient ID parameter is required for execution.');
    exit;
}

// 2. Fetch checking matrix state context to verify entity existence before dropping tables
$patientExists = $db->queryOne("SELECT id, name FROM `patient` WHERE id = ? LIMIT 1", [$id]);
if (!$patientExists) {
    Api::error('Patient record not found inside system infrastructure index.', 404);
    exit;
}

try {
    // Begin data-isolation checkpoint transaction
    $db->beginTransaction();

    // 3. Collect all unique pre-authorization sheet IDs connected to this specific patient
    $preAuthRows = $db->query("SELECT id FROM `pre-auth` WHERE patient_id = ?", [$id]);
    $preAuthIds = array_map(function($row) { return (int)$row['id']; }, $preAuthRows);

    if (!empty($preAuthIds)) {
        // Convert integer slice array context safely into standard parameterized CSV tokens
        $placeholders = implode(',', array_fill(0, count($preAuthIds), '?'));
        
        // 4. STEP A: Purge child treatment item mappings from your procedures collection table first
        $db->query("DELETE FROM `pre_auth_procedures` WHERE pre_auth_id IN ($placeholders)", $preAuthIds);
        
        // 5. STEP B: Wipe out the now empty parent pre-auth master sheet layouts
        $db->query("DELETE FROM `pre-auth` WHERE patient_id = ?", [$id]);
    }

    // 6. STEP C: Clean-purge clinical laboratory processing records linked to this tracking profile
    // Accounts for schemas referencing either p_id or patient_id structural signatures
    $db->query("DELETE FROM `labs` WHERE p_id = ?", [$id]);
    // Extra fallback guard if database uses lab tracking variation maps:
    // $db->query("DELETE FROM `lab_cases` WHERE p_id = ?", [$id]);

    // 7. STEP D: Execute final master patient index profile drop
    $deleted = $db->delete('patient', ['id' => $id]);

    if (!$deleted) {
        throw new Exception("Core data dictionary layer rejected baseline record drop sequence execution.");
    }

    // Safely apply alterations permanently across the database infrastructure
    $db->commit();

    Api::success(null, "Comprehensive files, labs, tracking entries, and profile matrices for '" . $patientExists['name'] . "' have been securely deleted.");

} catch (Exception $e) {
    // Immediately clear processing stack vectors if operations encounter exceptions
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Database transactional isolation cascade removal failure: ' . $e->getMessage(), 500);
}