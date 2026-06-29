<?php
/**
 * API: Delete Dynamic Statistics Widget Matrix Record
 * Path: /adm-statics/delete.php
 * Permanently removes a custom-configured dashboard metric widget.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Enforce strict administrative permission
if (!$auth->hasRole('admin')) {
    Api::error('Unauthorized administrative access privileges.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Target Widget ID
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    Api::error('Invalid request parameters. Target widget identification key is required.');
    exit;
}

try {
    // 3. Verify existence before deletion
    $sqlFetch = "SELECT id FROM `statics` WHERE id = ? LIMIT 1";
    $targetRecord = $db->queryOne($sqlFetch, [$id]);

    if (!$targetRecord) {
        Api::error('Target dynamic metric widget record not found.', 404);
        exit;
    }

    // Start Transaction to guarantee database consistency
    $db->beginTransaction();

    // 4. Remove the widget record
    $db->query("DELETE FROM `statics` WHERE id = ? LIMIT 1", [$id]);

    // 5. Commit ledger changes
    $db->commit();

    // 6. Return Operational Success
    Api::success([
        'deleted_static_id' => $id
    ], "Dynamic matrix metrics widget has been permanently removed.");

} catch (Exception $e) {
    // Rollback on failure
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Api::error('Database management tier runtime error: ' . $e->getMessage(), 500);
}