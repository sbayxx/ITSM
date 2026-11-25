<?php
require_once __DIR__ . '/../core/Database.php';

class ManualDevice
{
    public static function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM manual_devices WHERE id = ?", [$id]) ?: null;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        $customerScope = Auth::customerScope('md');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'md.customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['device_type'])) {
            $where[] = 'device_type = ?';
            $bindings[] = $filters['device_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $bindings[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR manufacturer LIKE ? OR model LIKE ? OR serial_number LIKE ? OR ip_address LIKE ? OR mac_address LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings = array_merge($bindings, [$like, $like, $like, $like, $like, $like]);
        }

        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM manual_devices md WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT md.*, u.name as assigned_to_name, c.name as customer_name
             FROM manual_devices md
             LEFT JOIN users u ON md.assigned_to = u.id
             LEFT JOIN customers c ON md.customer_id = c.id
             WHERE {$whereClause} ORDER BY md.name ASC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        $allowed = ['customer_id', 'name', 'device_type', 'manufacturer', 'model', 'serial_number',
                    'operating_system', 'os_version', 'assigned_to', 'location', 'ip_address',
                    'mac_address', 'notes', 'status', 'intune_device_id'];
        $insert = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        return (int)Database::insert('manual_devices', $insert);
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['customer_id', 'name', 'device_type', 'manufacturer', 'model', 'serial_number',
                    'operating_system', 'os_version', 'assigned_to', 'location', 'ip_address',
                    'mac_address', 'notes', 'status', 'intune_device_id'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (empty($update)) return;
        Database::update('manual_devices', $update, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('manual_devices', 'id = ?', [$id]);
    }

    public static function getDistinctDeviceTypes(): array
    {
        return ['pc', 'mobile', 'printer', 'network', 'other'];
    }

    public static function getDistinctStatuses(): array
    {
        return Database::fetchAll("SELECT DISTINCT status FROM manual_devices WHERE status IS NOT NULL ORDER BY status");
    }

    public static function linkToIntune(int $manualId, ?int $intuneId): void
    {
        Database::update('manual_devices', ['intune_device_id' => $intuneId], 'id = ?', [$manualId]);
    }
}
