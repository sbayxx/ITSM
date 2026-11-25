<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';

class Logger
{
    public static function log(
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?string $summary = null,
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $userId = Auth::id();
        $username = $_SESSION['user_name'] ?? 'Guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $oldStr = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue;
        $newStr = is_array($newValue) || is_object($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue;

        Database::insert('audit_log', [
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'summary' => htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'),
            'old_value' => $oldStr,
            'new_value' => $newStr,
            'ip_address' => $ip,
            'user_agent' => htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8'),
        ]);
    }

    public static function error(string $message, array $context = []): void
    {
        $logFile = STORAGE_PATH . '/logs/error.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
