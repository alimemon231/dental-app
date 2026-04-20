<?php
/**
 * POST api/patients/create.php
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

if (Api::method() !== 'POST') { Api::error('Method not allowed.', 405); exit; }

$data = [
    'office_id'    => trim($_POST['office']    ?? ''),
    'budget_year'     => trim($_POST['budget_year']     ?? ''),
    'budget_month'     => trim($_POST['budget_month']     ?? ''),
    'budget_amount'     => trim($_POST['budget_amount']     ?? ''),
      
];

if (empty($data['office_id']) || empty($data['budget_year']) || empty($data['budget_month']) || empty($data['budget_amount'])) {
    Api::error('All fields required');
    exit;
}

if($db->exists('monthly_budget', ['office_id' => $data['office_id'] , 'budget_year' => $data['budget_year'] , 'budget_month' => $data['budget_month']  ])){
    Api::error('Budget is already assigned plz check month and year');
    exit;
}


$id = $db->insert('monthly_budget', $data);
Api::success(null, 'budget assigned successfully.');
