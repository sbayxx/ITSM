<?php
require_once __DIR__ . '/../config/cors.php';

class CorsMiddleware
{
    public function handle(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: ' . (CORS_ALLOWED_ORIGINS[0] ?? ''));
        }
        header('Access-Control-Allow-Methods: ' . CORS_ALLOWED_METHODS);
        header('Access-Control-Allow-Headers: ' . CORS_ALLOWED_HEADERS);
        header('Access-Control-Allow-Credentials: ' . (CORS_ALLOW_CREDENTIALS ? 'true' : 'false'));
        header('Access-Control-Max-Age: ' . CORS_MAX_AGE);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        return true;
    }
}
