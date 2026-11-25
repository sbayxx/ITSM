<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::connection();

$users = [
    ['admin@itsm.local', 'admin123'],
    ['agent@itsm.local', 'agent123'],
    ['manager@itsm.local', 'manager123'],
];

foreach ($users as [$email, $password]) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo "Updated: $email -> $password<br>";
}

echo "<br>Done. Delete this file after use.";
