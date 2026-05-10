<?php
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed', 405); exit; }

$id = $_POST['id'] ?? null;

if (!$id) {
    Api::error('Lab ID is required.');
    exit;
}

try {
    $updateData = [
        'status' => 'Done',
        'date_complete' => date('Y-m-d') // Adding completion date
    ];

    $db->update('labs', $updateData, ['id' => $id]);

    Api::success(null, 'Lab procedure finalized and marked as Done.');
} catch (Exception $e) {
    Api::error('Database error: ' . $e->getMessage());
}