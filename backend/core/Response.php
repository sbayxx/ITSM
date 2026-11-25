<?php
class Response
{
    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('X-Powered-By');
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string|int $message = 'Success', int $status = 200): void
    {
        // Callers historically invoked success($data, 201) / success($data, 204)
        // expecting the integer to be the HTTP status, but the real 3rd
        // parameter is the status. A lone integer 2nd argument is therefore
        // treated as the status code.
        if (func_num_args() === 2 && is_int($message)) {
            $status = $message;
            $message = 'Success';
        }
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): void
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        self::json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public static function download(string $filePath, string $filename): void
    {
        if (!file_exists($filePath)) {
            self::error('File not found', 404);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}
