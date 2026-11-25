<?php
require_once __DIR__ . '/../core/Database.php';

class User
{
    public static function find(int $id): ?array
    {
        $user = Database::fetch(
            "SELECT u.*, c.name as customer_name
             FROM users u
             LEFT JOIN customers c ON u.customer_id = c.id
             WHERE u.id = ? AND u.deleted_at IS NULL",
            [$id]
        );
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
    }

    public static function all(array $params = []): array
    {
        $where = 'WHERE u.deleted_at IS NULL';
        $bindings = [];

        $user = Auth::user();
        if ($user && $user['role'] !== 'admin') {
            $ids = Auth::accessibleCustomerIds();
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND (u.customer_id IN ({$placeholders})";
                $bindings = array_merge($bindings, $ids);

                $rows = Database::fetchAll(
                    "SELECT user_id FROM customer_user WHERE customer_id IN ({$placeholders})",
                    $ids
                );
                $pivotUserIds = array_column($rows, 'user_id');
                if (!empty($pivotUserIds)) {
                    $ph2 = implode(',', array_fill(0, count($pivotUserIds), '?'));
                    $where .= " OR u.id IN ({$ph2})";
                    $bindings = array_merge($bindings, $pivotUserIds);
                }
                $where .= ')';
            }
        }
        if (!empty($params['search'])) {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $params['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }
        if (!empty($params['role'])) {
            $where .= ' AND role = ?';
            $bindings[] = $params['role'];
        }
        if (isset($params['is_active'])) {
            $where .= ' AND is_active = ?';
            $bindings[] = $params['is_active'];
        }
        $total = Database::fetch("SELECT COUNT(*) as count FROM users u {$where}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT u.*, c.name as customer_name FROM users u
             LEFT JOIN customers c ON u.customer_id = c.id
             {$where} ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        $rows = array_map(function ($row) {
            unset($row['password']);
            return $row;
        }, $rows);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        $data['password'] = Auth::hashPassword($data['password']);
        $data['gdpr_consent_at'] = date('Y-m-d H:i:s');
        $data['gdpr_consent_version'] = '1.0';
        return Database::insert('users', $data);
    }

    public static function update(int $id, array $data): void
    {
        if (isset($data['password'])) {
            $data['password'] = Auth::hashPassword($data['password']);
        }
        Database::update('users', $data, 'id = ?', [$id]);
    }

    public static function softDelete(int $id): void
    {
        Database::update('users', ['deleted_at' => date('Y-m-d H:i:s'), 'email' => 'deleted_' . $id . '@redacted.local'], 'id = ?', [$id]);
    }

    public static function hasRole(int $userId, string $role): bool
    {
        $user = self::find($userId);
        return $user && $user['role'] === $role;
    }

    public static function getCustomerUsers(int $customerId): array
    {
        $rows = Database::fetchAll(
            "SELECT u.* FROM users u
             LEFT JOIN customer_user cu ON u.id = cu.user_id AND cu.customer_id = ?
             WHERE (u.customer_id = ? OR cu.user_id IS NOT NULL)
             AND u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.name",
            [$customerId, $customerId]
        );
        return array_map(function ($row) {
            unset($row['password']);
            return $row;
        }, $rows);
    }

    public static function updateCustomerAssignment(int $userId, ?int $customerId): void
    {
        Database::update('users', ['customer_id' => $customerId], 'id = ?', [$userId]);
    }

    public static function getPermissions(int $userId): array
    {
        return Database::fetchAll(
            "SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             JOIN roles r ON rp.role_id = r.id
             JOIN users u ON u.role = r.slug
             WHERE u.id = ?",
            [$userId]
        );
    }
}
