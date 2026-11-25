<?php
require_once __DIR__ . '/../core/Response.php';

class CsrfMiddleware
{
    public function handle(): bool
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? '';
        $stored = $_SESSION['_csrf_token'] ?? '';
        if ($token === '' || $token !== $stored) {
            Response::json(['error' => 'Invalid CSRF token'], 419);
            return false;
        }
        return true;
    }

    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        return $token;
    }
}
