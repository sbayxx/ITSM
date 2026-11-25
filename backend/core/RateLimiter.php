<?php
class RateLimiter
{
    private static string $path = '';

    private static function init(): void
    {
        if (self::$path) return;
        self::$path = rtrim(defined('STORAGE_PATH') ? STORAGE_PATH : (__DIR__ . '/../storage'), '/') . '/rate-limiter';
        if (!is_dir(self::$path)) {
            mkdir(self::$path, 0755, true);
        }
    }

    public static function attempt(string $key, int $maxAttempts = 5, int $decaySeconds = 900): bool
    {
        self::init();
        $file = self::$path . '/' . md5($key) . '.lock';
        $now = time();
        $data = null;

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        if ($data && $data['reset_time'] > $now) {
            if ($data['attempts'] >= $maxAttempts) {
                return false;
            }
            $data['attempts']++;
        } else {
            $data = ['attempts' => 1, 'reset_time' => $now + $decaySeconds];
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    public static function remaining(string $key, int $maxAttempts = 5): int
    {
        self::init();
        $file = self::$path . '/' . md5($key) . '.lock';
        if (!file_exists($file)) return $maxAttempts;
        $data = json_decode(file_get_contents($file), true);
        if (!$data || $data['reset_time'] < time()) return $maxAttempts;
        return max(0, $maxAttempts - $data['attempts']);
    }

    public static function reset(string $key): void
    {
        self::init();
        $file = self::$path . '/' . md5($key) . '.lock';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function headers(string $key, int $maxAttempts = 5): array
    {
        $remaining = self::remaining($key, $maxAttempts);
        $file = self::$path . '/' . md5($key) . '.lock';
        $resetTime = 0;
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) $resetTime = $data['reset_time'] ?? 0;
        }
        return [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $resetTime,
        ];
    }
}
