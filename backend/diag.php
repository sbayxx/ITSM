<?php
header('Content-Type: text/plain');
echo "PHP Version: " . phpversion() . "\n\n";

echo "Step 1: Loading Env...\n";
require_once __DIR__ . '/core/Env.php';
echo "  OK\n";

echo "Step 2: Loading app config...\n";
require_once __DIR__ . '/config/app.php';
echo "  OK\n";

echo "Step 3: Loading database config...\n";
require_once __DIR__ . '/config/database.php';
echo "  DB_HOST: " . DB_HOST . "\n";
echo "  DB_NAME: " . DB_NAME . "\n";
echo "  OK\n";

echo "Step 4: Loading Database...\n";
require_once __DIR__ . '/core/Database.php';
echo "  OK\n";

echo "Step 5: Database connection...\n";
try {
    $db = Database::connection();
    echo "  Connected: " . ($db ? 'yes' : 'no') . "\n";
} catch (Exception $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
}

echo "Step 6: Loading Auth...\n";
require_once __DIR__ . '/core/Auth.php';
echo "  OK\n";

echo "Step 7: Loading MailService...\n";
require_once __DIR__ . '/services/MailService.php';
echo "  OK\n";

echo "\nAll steps passed.\n";
