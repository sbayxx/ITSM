<?php
require_once __DIR__ . '/../core/Database.php';

class MigrationRunner
{
    private string $migrationsDir;

    public function __construct(string $migrationsDir)
    {
        $this->migrationsDir = $migrationsDir;
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        $executed = $this->getExecutedMigrations();

        foreach ($files as $file) {
            $basename = basename($file);
            if (in_array($basename, $executed)) continue;
            echo "Running migration: {$basename}\n";
            $sql = file_get_contents($file);
            $statements = explode(';', $sql);
            Database::beginTransaction();
            try {
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        Database::query($statement);
                    }
                }
                Database::insert('migrations', ['migration' => $basename, 'executed_at' => date('Y-m-d H:i:s')]);
                Database::commit();
                echo "  Completed: {$basename}\n";
            } catch (Exception $e) {
                Database::rollback();
                echo "  ERROR: {$e->getMessage()}\n";
            }
        }
        echo "All migrations completed.\n";
    }

    private function ensureMigrationsTable(): void
    {
        Database::query("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at DATETIME NOT NULL,
                UNIQUE KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function getExecutedMigrations(): array
    {
        $rows = Database::fetchAll("SELECT migration FROM migrations ORDER BY id");
        return array_column($rows, 'migration');
    }
}
