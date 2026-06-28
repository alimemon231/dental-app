<?php
/**
 * API: Update Pre-Auth Administrative Client Notes
 * Path: /adm-pre-auth/update--client-notes.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Enforce role permission protection layers
if (!$auth->hasRole('staff') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access permissions.', 403);
    exit;
}

if (Api::method() !== 'POST') {
    Api::error('Method not allowed.', 405);
    exit;
}

// 1. Parse and sanitize incoming payload parameters
$id = (int)($_POST['id'] ?? 0);
$clientNotes = trim($_POST['client_notes'] ?? '');

if ($id <= 0) {
    Api::error('A valid Pre-Auth record ID identifier is required.');
    exit;
}

try {
    // 2. Direct single column update via template layer wrapper function
    // Assuming your wrapper class maps: update($tableName, $dataArray, $whereClause) or query equivalent
    $sql = "UPDATE `pre-auth` SET `admin_notes` = ? WHERE `id` = ?";
    $db->query($sql, [$clientNotes, $id]);

    // 3. Return clean operational layout success packet
    Api::success([], 'Client administrative notes updated successfully.');

} catch (Exception $e) {
    Api::error('Database runtime execution exception: ' . $e->getMessage(), 500);
}