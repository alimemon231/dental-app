<?php
/**
 * includes/Response.php
 * Lightweight JSON response helper.
 * Include this in every API endpoint.
 *
 * Usage:
 *   Response::success($data, 'Done');
 *   Response::error('Something went wrong');
 *   Response::error('Not found', 404);
 */

class Response
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
        exit;
    }

    /** Read a value from POST or raw JSON body */
    public static function input(string $key, mixed $default = null): mixed
    {
        static $jsonBody = null;
        if ($jsonBody === null) {
            $raw      = file_get_contents('php://input');
            $jsonBody = json_decode($raw, true) ?? [];
        }
        return $_POST[$key] ?? $jsonBody[$key] ?? $default;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}