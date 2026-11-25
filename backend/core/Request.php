<?php
class Request
{
    private array $routeParams = [];
    private array $body;

    public function __construct()
    {
        $raw = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $isJson = str_contains($contentType, 'application/json');
        // Some clients post JSON without a Content-Type header. Only sniff when the
        // body actually looks like JSON so form/multipart payloads keep using $_POST.
        if (!$isJson && $raw !== '' && $raw !== false && ($raw[0] === '{' || $raw[0] === '[')) {
            $isJson = true;
        }
        $this->body = ($isJson && $raw !== '' && $raw !== false)
            ? (json_decode($raw, true) ?? [])
            : [];
    }

    public function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        }
        return $method;
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_POST, $this->body);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$headerKey] ?? $default;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
