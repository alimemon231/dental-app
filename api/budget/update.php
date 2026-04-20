<?php
/**
 * POST api/patients/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { Api::error('Budget ID is required.'); exit; }

if (!$db->exists('monthly_budget', ['id' => $id])) {
    Api::error('Budget not found.', 404); exit;
}

// 1. Collect and Validate Data
$data = [
    'office_id'     => trim($_POST['office'] ?? ''),
    'budget_year'    => trim($_POST['budget_year'] ?? ''),
    'budget_month'   => trim($_POST['budget_month'] ?? ''),
    'budget_amount'  => trim($_POST['budget_amount'] ?? ''),
];

if (empty($data['office_id']) || empty($data['budget_year']) || empty($data['budget_month']) || empty($data['budget_amount'])) {
    Api::error('All fields required');
    exit;
}

// 2. The Duplicate Check (Refined for Update)
// We use a custom SQL query because we need to use "id != ?" 
$duplicateSql = "SELECT COUNT(*) as cnt FROM monthly_budget 
                 WHERE office_id = ? 
                 AND budget_year = ? 
                 AND budget_month = ? 
                 AND id != ?";

$check = $db->queryOne($duplicateSql, [
    $data['office_id'], 
    $data['budget_year'], 
    $data['budget_month'], 
    $id // Exclude the record we are currently updating
]);

if ((int)$check['cnt'] > 0) {
    Api::error('This office already has a budget assigned for the selected month and year.');
    exit;
}

// 3. Perform Update
$db->update('monthly_budget', $data, ['id' => $id]);
Api::success(null, 'Budget updated successfully.');