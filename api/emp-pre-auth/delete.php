<?php
/**
 * API: Delete Pre-Auth
 * Path: /emp-pre-auth/delete.php
 * Safely removes master pre-auth records and clean-purges linked itemized child procedures.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 1. Capture and Validate ID
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    Api::error('Record ID is required for deletion.');
    exit;
}

$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session.', 400);
    exit;
}

// 2. Fetch the Record within active office context to verify ownership and status
$record = $db->queryOne(
    "SELECT id, status FROM `pre-auth` WHERE id = ? AND office_id = ? LIMIT 1", 
    [$id, $sessionOfficeId]
);

if (!$record) {
    Api::error('Record not found or workspace access validation denied.', 404);
    exit;
}

// 3. STRICT CHECK: Only allow deletions if status is exactly 'Create'
if (strtolower($record['status']) !== 'create') {
    Api::error('Cannot delete a record that is currently marked as: ' . $record['status'] . '.', 400);
    exit;
}

try {
    // Start Transaction to guarantee both deletions complete smoothly or roll back entirely
    $db->beginTransaction();

    // 4. First purge child item rows mapping data from the procedures relationship table
    $db->query("DELETE FROM `pre_auth_procedures` WHERE pre_auth_id = ?", [$id]);

    // 5. Delete the parent master pre-auth record row entry
    $deleted = $db->delete('pre-auth', ['id' => $id]);

    if (!$deleted) {
        throw new Exception("Failed to delete the primary master entry record row.");
    }

    // Commit changes to database permanently
    $db->commit();

    Api::success(null, 'Pre-Auth entry and associated treatment entries successfully deleted.');

} catch (Exception $e) {
    // Instantly restore transaction changes on errors to keep things clean
   
        $db->rollBack();
    
    Api::error('Database transactional isolation removal failure: ' . $e->getMessage(), 500);
}