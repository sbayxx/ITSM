<?php
require_once __DIR__ . '/../core/Database.php';

class Vendor
{
    public const MODES = ['email', 'api', 'both'];
    private const WRITABLE = ['customer_id', 'name', 'email', 'phone', 'api_base_url', 'integration_mode', 'notes', 'is_active'];

    public static function find(int $id): ?array
    {
        $scope = Auth::customerScope('v');
        $row = Database::fetch(
            "SELECT v.* FROM vendors v WHERE v.id = ? AND {$scope['sql']}",
            array_merge([$id], $scope['params'])
        );
        if (!$row) return null;
        $row['api_key'] = self::activeKeyInfo($id);
        return $row;
    }

    public static function all(array $filters = []): array
    {
        $scope = Auth::customerScope('v');
        $where = [$scope['sql']];
        $bindings = $scope['params'];
        if (!empty($filters['customer_id'])) {
            $where[] = 'v.customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'v.is_active = ?';
            $bindings[] = (int)$filters['is_active'];
        }
        return Database::fetchAll(
            "SELECT v.*, c.name AS customer_name,
                    (SELECT COUNT(*) FROM tickets t WHERE t.vendor_id = v.id AND t.status = 'awaiting_vendor') AS awaiting_count
             FROM vendors v LEFT JOIN customers c ON c.id = v.customer_id
             WHERE " . implode(' AND ', $where) . ' ORDER BY v.name',
            $bindings
        );
    }

    public static function create(array $data): int
    {
        return (int)Database::insert('vendors', self::pick($data));
    }

    public static function update(int $id, array $data): void
    {
        $update = self::pick($data);
        if ($update) Database::update('vendors', $update, 'id = ?', [$id]);
    }

    /** Vendors are never hard-deleted: tickets keep a reference to them. */
    public static function deactivate(int $id): void
    {
        Database::update('vendors', ['is_active' => 0], 'id = ?', [$id]);
    }

    /**
     * Creates a new API key and revokes any previous active key (one active key per vendor).
     * Returns the plain key exactly once; only its SHA-256 hash is stored.
     */
    public static function createApiKey(int $vendorId): string
    {
        $plain = 'vk_' . bin2hex(random_bytes(24));
        Database::update('vendor_api_keys', ['revoked_at' => date('Y-m-d H:i:s')],
            'vendor_id = ? AND revoked_at IS NULL', [$vendorId]);
        Database::insert('vendor_api_keys', [
            'vendor_id' => $vendorId,
            'key_prefix' => substr($plain, 0, 10),
            'key_hash' => hash('sha256', $plain),
        ]);
        return $plain;
    }

    public static function revokeApiKey(int $vendorId): void
    {
        Database::update('vendor_api_keys', ['revoked_at' => date('Y-m-d H:i:s')],
            'vendor_id = ? AND revoked_at IS NULL', [$vendorId]);
    }

    public static function activeKeyInfo(int $vendorId): ?array
    {
        return Database::fetch(
            "SELECT key_prefix, created_at, last_used_at FROM vendor_api_keys
             WHERE vendor_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1",
            [$vendorId]
        ) ?: null;
    }

    /** Resolves a plain bearer token to an active vendor. Used by /api/v1/vendor only (no session). */
    public static function findByApiKey(string $plain): ?array
    {
        if ($plain === '') return null;
        $row = Database::fetch(
            "SELECT v.*, vk.id AS key_id FROM vendor_api_keys vk
             JOIN vendors v ON v.id = vk.vendor_id
             WHERE vk.key_hash = ? AND vk.revoked_at IS NULL AND v.is_active = 1",
            [hash('sha256', $plain)]
        );
        if ($row) {
            Database::update('vendor_api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['key_id']]);
        }
        return $row ?: null;
    }

    private static function pick(array $data): array
    {
        $out = [];
        foreach (self::WRITABLE as $f) {
            if (array_key_exists($f, $data)) $out[$f] = $data[$f];
        }
        if (isset($out['integration_mode']) && !in_array($out['integration_mode'], self::MODES, true)) {
            unset($out['integration_mode']);
        }
        return $out;
    }
}
