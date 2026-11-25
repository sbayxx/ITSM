<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$migrationsDir = __DIR__ . '/../migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

Database::query("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$executed = Database::fetchAll("SELECT migration FROM migrations");
$executed = array_column($executed, 'migration');

function addColumnIfMissing(string $table, string $column, string $definition): void
{
    $exists = Database::fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $column]
    )['cnt'];
    if (!$exists) {
        Database::query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "  + Added column {$table}.{$column}<br>";
    }
}

function addIndexIfMissing(string $table, string $index, string $definition): void
{
    $exists = Database::fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
        [$table, $index]
    )['cnt'];
    if (!$exists) {
        Database::query("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$definition}");
        echo "  + Added index {$table}.{$index}<br>";
    }
}

function addFkIfMissing(string $table, string $constraint, string $definition): void
{
    $exists = Database::fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
        [$table, $constraint]
    )['cnt'];
    if (!$exists) {
        Database::query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` {$definition}");
        echo "  + Added FK {$table}.{$constraint}<br>";
    }
}

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $executed)) {
        echo "&#10003; $name (already executed)<br>";
        continue;
    }
    $sql = file_get_contents($file);
    $statements = explode(';', $sql);
    $allOk = true;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        try {
            Database::query($statement);
        } catch (Exception $e) {
            echo "&#10007; $name - Statement error: " . $e->getMessage() . "<br>";
            $allOk = false;
        }
    }
    if ($allOk) {
        Database::insert('migrations', ['migration' => $name]);
        echo "&#10003; $name<br>";
        $count++;
    } else {
        echo "&#10007; $name completed with errors (some statements may have been skipped)<br>";
    }
}

echo "<br>--- Checking ci_items schema upgrades ---<br>";
try {
    addColumnIfMissing('ci_items', 'fqdn', 'VARCHAR(255) DEFAULT NULL AFTER `serial_number`');
    addColumnIfMissing('ci_items', 'ip_address', 'VARCHAR(45) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'mac_address', 'VARCHAR(17) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'asset_tag', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'vendor', 'VARCHAR(255) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'model', 'VARCHAR(255) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'purchase_date', 'DATE DEFAULT NULL');
    addColumnIfMissing('ci_items', 'warranty_end', 'DATE DEFAULT NULL');
    addColumnIfMissing('ci_items', 'support_group', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'business_service', 'VARCHAR(255) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'environment', "ENUM('production','staging','development','testing','disaster_recovery') DEFAULT 'production'");
    addColumnIfMissing('ci_items', 'os', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'os_version', 'VARCHAR(100) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'cpu', 'VARCHAR(255) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'ram', 'VARCHAR(50) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'disk_space', 'VARCHAR(50) DEFAULT NULL');
    addColumnIfMissing('ci_items', 'ci_type_id', 'INT DEFAULT NULL AFTER `type`');
    addIndexIfMissing('ci_items', 'idx_asset_tag', '(`asset_tag`)');
    addIndexIfMissing('ci_items', 'idx_fqdn', '(`fqdn`)');
    addIndexIfMissing('ci_items', 'idx_environment', '(`environment`)');
    addIndexIfMissing('ci_items', 'idx_business_service', '(`business_service`)');
    addFkIfMissing('ci_items', 'fk_ci_type', 'FOREIGN KEY (`ci_type_id`) REFERENCES `ci_types`(`id`) ON DELETE SET NULL');
    echo "Schema upgrades complete.<br>";
} catch (Exception $e) {
    echo "&#10007; Schema upgrade error: " . $e->getMessage() . "<br>";
}

if ($count === 0) {
    echo "<br>All SQL migrations are up to date.";
}
echo "<br><br>Migration complete.";
