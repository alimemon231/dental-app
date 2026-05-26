<?php
/**
 * API: Fetch User Assigned Offices
 * Path: /api/auth/user_offices.php
 * Method: GET
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Ensure the user is fully logged in before executing queries
$auth->requireAuth();



$_SESSION['office_id'] = $_POST['office_id'];

Api::success(null, 'Office selected redirecting to dashboard');