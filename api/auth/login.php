<?php
/**
 * api/auth/login.php
 * POST: email, password, remember
 */


require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Response.php';

// 1. Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

// 2. Capture Inputs
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

// 3. Basic Validation
if (empty($email) || empty($password)) {
    Response::error('Email and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Invalid email format.');
}

// 4. Initialize Database and Auth
$db   = new Database();
$auth = new Auth($db);

/** * 5. Attempt Login via Auth Class
 * The Auth->login() method handles:
 * - Email lookup
 * - Status check (active/inactive)
 * - Password verification
 * - Session data creation
 * - Password rehashing (if needed)
 */
$result = $auth->login($email, $password);

if (!$result['success']) {
    Response::error($result['message']);
}

/**
 * 6. Handle "Remember Me" (Long Session)
 * If checked, we overwrite the session cookie to last 30 days.
 * If NOT checked, the cookie remains a "Session" cookie (expires when browser closes).
 */
if ($remember) {
    $params = session_get_cookie_params();
    $expire = time() + (30 * 24 * 3600); // 30 Days in seconds

    setcookie(
        session_name(),
        session_id(),
        $expire,
        $params['path'],
        $params['domain'],
        $params['secure'] ?? isset($_SERVER['HTTPS']),
        $params['httponly'] ?? true
    );
}

// 7. Success Response
// We pass the safe user data returned by the Auth class
Response::success($result['user'] , 'Login successful.');