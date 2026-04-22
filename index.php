<?php
/**
 * Root Redirector
 */
require_once 'includes/Auth.php';


$db   = new Database();
$auth = new Auth($db);

// 1. If the user is logged in, send them to the dashboard
if ($auth->check()) {
    header('Location: /dashboard.php');
    exit;
}

// 2. If not logged in, this will trigger the redirect to /login.php 
// based on your Auth.php logic
$auth->requireAuth();