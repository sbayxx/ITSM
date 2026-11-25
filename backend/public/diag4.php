<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "1. alive\n";

require_once __DIR__ . '/../core/Env.php';
echo "2. Env loaded\n";

require_once __DIR__ . '/../config/app.php';
echo "3. App config loaded\n";

require_once __DIR__ . '/../config/database.php';
echo "4. DB config loaded\n";
echo "   DB_HOST: " . DB_HOST . "\n";

require_once __DIR__ . '/../core/Database.php';
echo "5. Database class loaded\n";

try {
    $conn = Database::connection();
    echo "6. DB connected\n";
} catch (Exception $e) {
    echo "6. DB FAILED: " . $e->getMessage() . "\n";
}

require_once __DIR__ . '/../core/Auth.php';
echo "7. Auth loaded\n";

Auth::init();
echo "8. Auth init done\n";

echo "DONE\n";
