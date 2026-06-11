<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed', 405); 
    exit; 
}

$id = $_POST['id'] ?? null;

if (!$id) {
    Api::error('Record ID is required.');
    exit;
}

// 1. Fetch current record to check status
$lab = $db->selectOne("labs", ['id' => $id]);

if (!$lab) {
    Api::error('Lab case not found.');
    exit;
}

// 2. Security Check: Only allow deletion if status is 'Sent'


try {
    // 3. Perform the deletion
    // If you use soft deletes, change this to: $db->update('labs', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    $db->query("DELETE FROM labs WHERE id = ?", [$id]);

    Api::success(null, 'Lab case deleted successfully.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}