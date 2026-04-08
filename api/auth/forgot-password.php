<?php
/**
 * POST api/auth/forgot-password.php
 * action = send_code | verify_code | reset_password
 */
require_once __DIR__ . '/../../includes/Auth.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Api::error('Method not allowed.', 405); exit;
}

$action = $_POST['action'] ?? Api::input('action', '');
$db     = new Database();
$auth   = new Auth($db);
$result = ['success' => false, 'message' => 'Invalid action.'];

switch ($action) {
    case 'send_code':
        $email  = trim($_POST['email'] ?? Api::input('email', ''));
        $result = $auth->sendResetCode($email);
        break;

    case 'verify_code':
        $email  = trim($_POST['email'] ?? Api::input('email', ''));
        $code   = trim($_POST['code']  ?? Api::input('code', ''));
        $result = $auth->verifyResetCode($email, $code);
        break;

    case 'reset_password':
        $new     = $_POST['new_password']     ?? Api::input('new_password', '');
        $confirm = $_POST['confirm_password'] ?? Api::input('confirm_password', '');
        $result  = $auth->resetPassword($new, $confirm);
        break;
}

if ($result['success']) {
    Api::success(null, $result['message']);
} else {
    Api::error($result['message']);
}
