<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

echo "APP_DEBUG: " . (APP_DEBUG ? 'true' : 'false') . "\n";
echo "DB_HOST: " . DB_HOST . "\n";

try {
    $db = Database::connection();
    echo "DB connected\n";

    $result = Database::fetch("SELECT * FROM users WHERE email = ?", ['admin@itsm.local']);
    if ($result) {
        echo "User found: " . $result['name'] . "\n";
        echo "Password hash: " . substr($result['password'], 0, 20) . "...\n";
        $test = password_verify('admin123', $result['password']);
        echo "Password verify: " . ($test ? 'PASS' : 'FAIL') . "\n";
    } else {
        echo "User NOT found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
