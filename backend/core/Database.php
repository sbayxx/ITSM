<?php
require_once __DIR__ . '/../config/database.php';

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(array $config = []): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = self::$config['host'] ?? DB_HOST;
            $dbname = self::$config['dbname'] ?? DB_NAME;
            $user = self::$config['user'] ?? DB_USER;
            $pass = self::$config['pass'] ?? DB_PASS;
            $charset = self::$config['charset'] ?? DB_CHARSET;
            $port = self::$config['port'] ?? DB_PORT;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Internal server error']);
                exit;
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new InvalidArgumentException('Invalid database identifier');
        }
        return "`{$name}`";
    }

    public static function insert(string $table, array $data): int
    {
        $table = self::identifier($table);
        $columns = implode(', ', array_map(self::identifier(...), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));
        return (int)self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $table = self::identifier($table);
        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setClauses[] = self::identifier($key) . ' = ?';
            $params[] = $value;
        }
        $setStr = implode(', ', $setClauses);
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
        $params = array_merge($params, $whereParams);
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        self::query($sql, $params);
        return self::query($sql, $params)->rowCount();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollback(): void
    {
        self::connection()->rollBack();
    }
}
