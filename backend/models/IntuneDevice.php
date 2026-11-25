<?php
require_once __DIR__ . '/../core/Database.php';

class IntuneDevice
{
    public static function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM intune_devices WHERE id = ?", [$id]) ?: null;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        $customerScope = Auth::customerScope();
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['os'])) {
            $where[] = 'operating_system = ?';
            $bindings[] = $filters['os'];
        }
        if (!empty($filters['compliance_state'])) {
            $where[] = 'compliance_state = ?';
            $bindings[] = $filters['compliance_state'];
        }
        if (!empty($filters['device_type'])) {
            $where[] = 'device_type = ?';
            $bindings[] = $filters['device_type'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(device_name LIKE ? OR user_display_name LIKE ? OR user_principal_name LIKE ? OR serial_number LIKE ? OR model LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings = array_merge($bindings, [$like, $like, $like, $like, $like]);
        }

        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM intune_devices WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT * FROM intune_devices WHERE {$whereClause} ORDER BY device_name ASC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function upsert(array $data): void
    {
        $existing = Database::fetch("SELECT id FROM intune_devices WHERE device_id = ?", [$data['device_id']]);
        if ($existing) {
            Database::update('intune_devices', $data, 'id = ?', [$existing['id']]);
        } else {
            Database::insert('intune_devices', $data);
        }
    }

    public static function delete(int $id): void
    {
        Database::delete('intune_devices', 'id = ?', [$id]);
    }

    public static function getDistinctOs(): array
    {
        return Database::fetchAll("SELECT DISTINCT operating_system FROM intune_devices WHERE operating_system IS NOT NULL ORDER BY operating_system");
    }

    public static function getDistinctDeviceTypes(): array
    {
        return Database::fetchAll("SELECT DISTINCT device_type FROM intune_devices WHERE device_type IS NOT NULL ORDER BY device_type");
    }

    public static function getComplianceSummary(): array
    {
        return Database::fetchAll(
            "SELECT compliance_state, COUNT(*) as count FROM intune_devices WHERE is_active = 1 GROUP BY compliance_state ORDER BY count DESC"
        );
    }

    public static function getOsBreakdown(): array
    {
        return Database::fetchAll(
            "SELECT CONCAT(COALESCE(operating_system, 'Unknown'), ' ', COALESCE(os_version, '')) as os_label, COUNT(*) as count FROM intune_devices WHERE is_active = 1 GROUP BY operating_system, os_version ORDER BY count DESC"
        );
    }

    public static function getLastSyncTime(): ?string
    {
        $row = Database::fetch("SELECT MAX(last_synced_at) as last_sync FROM intune_devices");
        return $row['last_sync'] ?? null;
    }

    public static function clearAll(): void
    {
        Database::delete('intune_devices', '1=1');
    }
}
