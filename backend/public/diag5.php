<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

echo "Testing Auth.php...\n";

try {
    require_once __DIR__ . '/../config/session.php';
    echo "session.php loaded: SESSION_HTTP_ONLY=" . (defined('SESSION_HTTP_ONLY') ? 'YES' : 'NO') . "\n";
    echo "SESSION_LIFETIME=" . SESSION_LIFETIME . "\n";
    echo "SESSION_SECURE=" . (SESSION_SECURE ? 'true' : 'false') . "\n";
    echo "SESSION_HTTP_ONLY=" . (SESSION_HTTP_ONLY ? 'true' : 'false') . "\n";
    echo "SESSION_SAME_SITE=" . SESSION_SAME_SITE . "\n";
} catch (Throwable $e) {
    echo "session.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../core/Database.php';
    echo "Database.php loaded\n";
} catch (Throwable $e) {
    echo "Database.php ERROR: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../core/Auth.php';
    echo "Auth.php loaded\n";
    echo "Auth class exists: " . (class_exists('Auth') ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) {
    echo "Auth.php ERROR: " . $e->getMessage() . "\n";
}

echo "DONE\n";
