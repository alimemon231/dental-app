<?php
/**
 * ============================================================
 * DENTAL APP — AUTH / SESSION CLASS
 * Handles login, session checks, logout, and password reset.
 * ============================================================
 */

require_once __DIR__ . '/Database.php';

class Auth
{
    private Database $db;
    private string $sessionName = 'dental_app_session';
    private int $sessionLifetime = 7200;    // 2 hours (seconds)
    private string $resetCodeTable = 'otp_tokens';

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->startSession();
    }

    /* ================================================================
       SESSION BOOTSTRAP
    ================================================================ */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,                      // browser-session cookie
                'path' => '/',
                // 'domain'   => '',
                // 'secure'   => isset($_SERVER['HTTPS']),
                // 'httponly' => true,
                // 'samesite' => 'Strict',
            ]);
            session_name($this->sessionName);
            session_start();
        }
    }

    /* ================================================================
       LOGIN
       $auth->login('user@email.com', 'password123')
       Returns [ 'success' => true/false, 'message' => '...' ]
    ================================================================ */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required.'];
        }

        $user = $this->db->selectOne('users', ['email' => $email, 'status' => 'active']);

        if (!$user) {
            // Generic message to prevent user enumeration
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Rehash if needed (PHP upgrades hashing algorithm over time)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], ['user_id' => $user['user_id']]);
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Store minimal safe data in session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['user_type'];


        // // Update last login in DB
        // $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        $safeUser = [
            'id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['user_type'],
        ];

        return ['success' => true, 'message' => 'Login successful.', 'user' => $safeUser];
    }

    /* ================================================================
       CHECK — is the current request authenticated?
    ================================================================ */
    public function check(): bool
    {
        if (empty($_SESSION['user_id']))
            return false;
        return true;
    }

    /* ================================================================
       REQUIRE AUTH — redirect or respond with 401 if not logged in
       Call at the top of every protected PHP file.
    ================================================================ */
    public function requireAuth(): void
    {
        if (!$this->check()) {
            if ($this->isAjaxRequest()) {
                Api::error('Unauthorized. Please log in.', 401);
                exit;
            }
            header('Location: /login.php');
            exit;
        }
    }

    /* ================================================================
       CURRENT USER
    ================================================================ */
    public function user(): ?array
    {
        if (!$this->check())
            return null;
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
        ];
    }

    public function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /* ================================================================
       ROLE CHECK
    ================================================================ */
    public function hasRole(string ...$roles): bool
    {
        return in_array($_SESSION['user_role'] ?? '', $roles);
    }

    public function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        if (!$this->hasRole(...$roles)) {
            if ($this->isAjaxRequest()) {
                Api::error('You do not have permission to perform this action.', 403);
                exit;
            }
            header('Location: /dashboard.php?error=forbidden');
            exit;
        }
    }

    /**
     * Fetches the office name for a single user.
     */
    public function officeName(int $userId): ?string
    {
        $this->requireAuth();

        $sql = "SELECT o.office_name 
            FROM offices o
            INNER JOIN office_users ou ON o.id = ou.office_id
            WHERE ou.user_id = ? ";

        // Using your query method
        $result = $this->db->query($sql, [$userId]);

        

        // Return the name if found, otherwise return null
        return !empty($result) ? $result[0]['office_name'] : "No Office Assigned";
    }

    /* ================================================================
       LOGOUT
    ================================================================ */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /* ================================================================
       FORGOT PASSWORD — Step 1: Send reset code via email
       Saves a 6-digit code + expiry to DB and emails the user.
    ================================================================ */
    public function sendResetCode(string $email): array
    {
        $email = strtolower(trim($email));
        $user = $this->db->selectOne('users', ['email' => $email, 'status' => 'active']);

        // Always return success message to prevent user enumeration
        if (!$user) {
            return ['success' => true, 'message' => 'If that email exists, a reset code has been sent.'];
        }

        // Invalidate any existing tokens for this email
        $this->db->delete($this->resetCodeTable, ['user_id' => $user["user_id"]]);

        // Generate a 6-digit numeric code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        $this->db->insert($this->resetCodeTable, [
            'user_id' => $user["user_id"],
            'email' => $email,
            'otp_token' => password_hash($code, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires,
        ]);

        $sent = $this->sendResetEmail($email, $user['name'], $code);

        if (!$sent) {
            return ['success' => true, 'message' => 'Failed to send email. Please try again.'];
        }

        return ['success' => true, 'message' => 'If that email exists, a reset code has been sent.'];
    }

    /* ================================================================
       FORGOT PASSWORD — Step 2: Verify code
    ================================================================ */
    public function verifyResetCode(string $email, string $code): array
    {
        $email = strtolower(trim($email));
        $token = $this->db->selectOne($this->resetCodeTable, ['email' => $email]);

        if (!$token) {
            return ['success' => false, 'message' => 'Invalid or expired reset code.'];
        }

        if (strtotime($token['expires_at']) < time()) {
            $this->db->delete($this->resetCodeTable, ['email' => $email]);
            return ['success' => false, 'message' => 'Reset code has expired. Please request a new one.'];
        }

        if (!password_verify($code, $token['otp_token'])) {
            return ['success' => false, 'message' => 'The code you entered is incorrect.'];
        }

        // Mark as verified — store a temporary flag in session
        $_SESSION['reset_verified_email'] = $email;
        $_SESSION['reset_verified_time'] = time();

        return ['success' => true, 'message' => 'Code verified. You may now reset your password.'];
    }

    /* ================================================================
       FORGOT PASSWORD — Step 3: Set new password
    ================================================================ */
    public function resetPassword(string $newPassword, string $confirmPassword): array
    {
        $email = $_SESSION['reset_verified_email'] ?? null;
        $time = $_SESSION['reset_verified_time'] ?? 0;

        if (!$email || (time() - $time) > 60) { // 10-min window after verification
            return ['success' => false, 'message' => 'Session expired. Please restart the reset process.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', ['password' => $hashed], ['email' => $email]);
        $this->db->delete($this->resetCodeTable, ['email' => $email]);

        unset($_SESSION['reset_verified_email'], $_SESSION['reset_verified_time']);

        return ['success' => true, 'message' => 'Password reset successfully. You may now log in.'];
    }

    /* ================================================================
       SEND RESET EMAIL (uses PHP mail with basic headers)
    ================================================================ */
    private function sendResetEmail(string $toEmail, string $toName, string $code): bool
    {
        $fromName = 'Dental App';
        $fromEmail = 'noreply@ouraydentalmanagement.com';      // ← change to your domain

        $subject = 'Your Password Reset Code';

        $body = "Hi {$toName},\r\n\r\n"
            . "You requested a password reset for your Dental App account.\r\n\r\n"
            . "Your reset code is:  {$code}\r\n\r\n"
            . "This code will expire in 5 minutes.\r\n\r\n"
            . "If you did not request this, please ignore this email.\r\n\r\n"
            . "Regards,\r\nDental App Team";

        // Basic auth headers to improve deliverability (plain mail)
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 7bit\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "X-Priority: 1\r\n";

        return mail($toEmail, $subject, $body, $headers);
    }

    /* ================================================================
       HELPER
    ================================================================ */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}


/* ================================================================
   API RESPONSE HELPER
   Used in all PHP API endpoints to return consistent JSON.

   Api::success(['users' => [...]], 'Loaded successfully');
   Api::error('Not found', 404);
================================================================ */
class Api
{
    public static function success(mixed $data = null, string $message = 'Success'): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message = 'An error occurred.', int $httpCode = 200): void
    {
        http_response_code($httpCode);
        self::json(['success' => false, 'message' => $message, 'data' => null]);
    }

    private static function json(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        // Works for POST, JSON body
        static $jsonBody = null;
        if ($jsonBody === null) {
            $raw = file_get_contents('php://input');
            $jsonBody = json_decode($raw, true) ?? [];
        }
        return $_POST[$key] ?? $jsonBody[$key] ?? $default;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}
