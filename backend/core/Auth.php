<?php // v2 - opcache bust
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/session.php';

class Auth
{
    private static ?array $user = null;

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => SESSION_DOMAIN,
                'secure' => SESSION_SECURE,
                'httponly' => SESSION_HTTP_ONLY,
                'samesite' => SESSION_SAME_SITE,
            ]);
            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::fetch("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL AND is_active = 1", [$email]);

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return false;
        }

        if (!$user || !password_verify($password, $user['password'])) {
            if ($user) {
                $attempts = (int)$user['failed_attempts'] + 1;
                $update = ['failed_attempts' => $attempts];
                if ($attempts >= 5) {
                    $update['locked_until'] = date('Y-m-d H:i:s', time() + 900);
                }
                Database::update('users', $update, 'id = ?', [$user['id']]);
            }
            return false;
        }

        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'failed_attempts' => 0,
            'locked_until' => null,
        ], 'id = ?', [$user['id']]);

        unset($user['password']);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        self::$user = $user;
        return true;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;
        $id = $_SESSION['impersonating_id'] ?? $_SESSION['user_id'] ?? null;
        if (!$id) return null;
        self::$user = Database::fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (self::$user) {
            unset(self::$user['password']);
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']) || isset($_SESSION['impersonating_id']);
    }

    public static function isImpersonating(): bool
    {
        return isset($_SESSION['impersonating_id']);
    }

    public static function getImpersonator(): ?array
    {
        if (!self::isImpersonating()) return null;
        $id = $_SESSION['original_admin_id'] ?? null;
        if (!$id) return null;
        $impersonator = Database::fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$id]);
        if ($impersonator) {
            unset($impersonator['password']);
        }
        return $impersonator;
    }

    public static function clearUser(): void
    {
        self::$user = null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        $userRole = self::role();
        if ($userRole === 'admin') return true;
        return $userRole === $role;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function hasPermission(string $permission): bool
    {
        $user = self::user();
        if (!$user) return false;
        if ($user['role'] === 'admin') return true;
        $result = Database::fetch(
            "SELECT 1 FROM role_permissions rp
             JOIN roles r ON rp.role_id = r.id
             JOIN permissions p ON rp.permission_id = p.id
             WHERE r.slug = ? AND p.slug = ? LIMIT 1",
            [$user['role'], $permission]
        );
        return $result !== null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
        self::$user = null;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function regenerateSession(): void
    {
        $data = $_SESSION;
        session_regenerate_id(true);
        $_SESSION = $data;
        session_write_close();
        // Restart session so subsequent code can access $_SESSION
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = $data;
    }

    public static function refreshUser(): void
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id) {
            self::$user = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
            if (self::$user) {
                unset(self::$user['password']);
            }
        }
    }

    public static function accessibleCustomerIds(): array
    {
        $user = self::user();
        if (!$user) return [];
        if ($user['role'] === 'admin') return [];

        $ids = [];

        if ($user['customer_id']) {
            $ids[] = (int)$user['customer_id'];
        }

        $rows = Database::fetchAll(
            "SELECT customer_id FROM customer_user WHERE user_id = ?",
            [$user['id']]
        );
        foreach ($rows as $row) {
            $cid = (int)$row['customer_id'];
            if (!in_array($cid, $ids)) {
                $ids[] = $cid;
            }
        }

        return $ids;
    }

    public static function customerScope(string $alias = ''): array
    {
        $user = self::user();
        if (!$user || $user['role'] === 'admin') {
            return ['sql' => '1=1', 'params' => []];
        }

        $ids = self::accessibleCustomerIds();
        if (empty($ids)) {
            return ['sql' => '1=0', 'params' => []];
        }

        $prefix = $alias ? "{$alias}." : '';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ['sql' => "{$prefix}customer_id IN ({$placeholders})", 'params' => $ids];
    }

    public static function isCustomerAdmin(): bool
    {
        $user = self::user();
        return $user && $user['role'] === 'customer_admin';
    }

    public static function canAccessCustomer(int $customerId): bool
    {
        $user = self::user();
        if (!$user) return false;
        if ($user['role'] === 'admin') return true;
        $ids = self::accessibleCustomerIds();
        return in_array($customerId, $ids);
    }
}
