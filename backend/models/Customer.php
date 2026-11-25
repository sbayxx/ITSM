<?php
require_once __DIR__ . '/../core/Database.php';

class Customer
{
    public static function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM customers WHERE id = ?", [$id]) ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM customers WHERE slug = ?", [$slug]) ?: null;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR slug LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = ?';
            $bindings[] = (int)$filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);
        [$page, $perPage, $offset] = Pagination::params();
        $total = Database::fetch("SELECT COUNT(*) as count FROM customers WHERE {$whereClause}", $bindings)['count'];
        $rows = Database::fetchAll(
            "SELECT * FROM customers WHERE {$whereClause} ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $data['name']));
        }
        if (!empty($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }
        return Database::insert('customers', $data);
    }

    public static function update(int $id, array $data): void
    {
        if (!empty($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }
        Database::update('customers', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('customers', 'id = ?', [$id]);
    }

    public static function getSettings(int $id): array
    {
        $customer = self::find($id);
        if (!$customer || empty($customer['settings'])) {
            return [
                'azure_ad' => ['sync_users' => false, 'licensed_only' => true, 'user_type_filter' => ''],
                'intune' => ['enabled' => false],
                'sync' => ['schedule' => 'manual'],
                'email' => ['smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_username' => '', 'smtp_password' => '', 'from_email' => 'noreply@itsm.governit.dk', 'from_name' => 'ITSM System', 'inbound_email' => ''],
            ];
        }
        $settings = is_string($customer['settings']) ? json_decode($customer['settings'], true) : $customer['settings'];
        return array_merge([
            'azure_ad' => ['sync_users' => false, 'licensed_only' => true, 'user_type_filter' => ''],
            'intune' => ['enabled' => false],
            'sync' => ['schedule' => 'manual'],
            'email' => ['smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_username' => '', 'smtp_password' => '', 'from_email' => 'noreply@itsm.governit.dk', 'from_name' => 'ITSM System', 'inbound_email' => ''],
        ], $settings ?: []);
    }

    public static function updateSettings(int $id, array $settings): void
    {
        $current = self::getSettings($id);
        $merged = array_merge($current, $settings);
        Database::update('customers', ['settings' => json_encode($merged, JSON_UNESCAPED_UNICODE)], 'id = ?', [$id]);
    }

    public static function getAssignedUserIds(int $customerId): array
    {
        $rows = Database::fetchAll("SELECT user_id FROM customer_user WHERE customer_id = ?", [$customerId]);
        return array_column($rows, 'user_id');
    }

    public static function assignUsers(int $customerId, array $userIds): void
    {
        Database::delete('customer_user', 'customer_id = ?', [$customerId]);
        foreach ($userIds as $uid) {
            Database::insert('customer_user', ['customer_id' => $customerId, 'user_id' => (int)$uid]);
        }
    }

    public static function assignToUser(int $userId, array $customerIds): void
    {
        Database::delete('customer_user', 'user_id = ?', [$userId]);
        foreach ($customerIds as $cid) {
            Database::insert('customer_user', ['customer_id' => (int)$cid, 'user_id' => $userId]);
        }
    }

    public static function getUserCustomers(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT c.* FROM customers c
             JOIN customer_user cu ON c.id = cu.customer_id
             WHERE cu.user_id = ? AND c.is_active = 1
             ORDER BY c.name",
            [$userId]
        );
        return $rows;
    }
}
