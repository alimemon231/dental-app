<?php
/**
 * GET api/patients/list.php
 * Query params: limit, page, search
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$limit  = min((int)($_GET['limit'] ?? 20), 100);
$page   = max((int)($_GET['page']  ?? 1), 1);
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

if ($search) {
    $like = '%' . $search . '%';
    $patients = $db->query(
        "SELECT id,
                CONCAT(first_name, ' ', last_name) AS name,
                phone, email, gender, date_of_birth, created_at
         FROM patients
         WHERE deleted_at IS NULL
           AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
        [$like, $like, $like, $like, $limit, $offset]
    );
} else {
    $patients = $db->query(
        "SELECT id,
                CONCAT(first_name, ' ', last_name) AS name,
                phone, email, gender, date_of_birth, created_at
         FROM patients
         WHERE deleted_at IS NULL
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
}

Api::success($patients);
