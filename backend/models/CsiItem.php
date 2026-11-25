<?php
require_once __DIR__ . '/../core/Database.php';

class CsiItem
{
    public static function find(int $id): ?array
    {
        $where = ['c.id = ?'];
        $bindings = [$id];
        $customerScope = Auth::customerScope('c');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }
        return Database::fetch("SELECT c.*, a.name as assigned_to_name, cr.name as created_by_name,
            cust.name as customer_name
            FROM csi_register c
            LEFT JOIN users a ON c.assigned_to = a.id
            LEFT JOIN users cr ON c.created_by = cr.id
            LEFT JOIN customers cust ON c.customer_id = cust.id
            WHERE " . implode(' AND ', $where), $bindings);
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        $customerScope = Auth::customerScope('c');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'c.customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.category = ?';
            $bindings[] = $filters['category'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 'c.assigned_to = ?';
            $bindings[] = (int)$filters['assigned_to'];
        }
        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM csi_register c WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT c.*, a.name as assigned_to_name, cr.name as created_by_name,
                    cust.name as customer_name
             FROM csi_register c
             LEFT JOIN users a ON c.assigned_to = a.id
             LEFT JOIN users cr ON c.created_by = cr.id
             LEFT JOIN customers cust ON c.customer_id = cust.id
             WHERE {$whereClause}
             ORDER BY c.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}", $bindings);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        if (empty($data['customer_id'])) {
            $user = Auth::user();
            if ($user && $user['customer_id']) {
                $data['customer_id'] = $user['customer_id'];
            }
        }
        if (isset($data['action_items']) && is_array($data['action_items'])) {
            $data['action_items'] = json_encode($data['action_items']);
        }
        return Database::insert('csi_register', $data);
    }

    public static function update(int $id, array $data): void
    {
        if (isset($data['action_items']) && is_array($data['action_items'])) {
            $data['action_items'] = json_encode($data['action_items']);
        }
        Database::update('csi_register', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('csi_register', 'id = ?', [$id]);
    }

    public static function getKpiSummary(): array
    {
        $scope = Auth::customerScope();
        $where = $scope['sql'];
        $params = $scope['params'];

        if ($where === '1=1') {
            return Database::fetchAll(
                "SELECT category, COUNT(*) as total,
                 SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented,
                 SUM(CASE WHEN status IN ('planned','in_progress') THEN 1 ELSE 0 END) as in_progress
                 FROM csi_register GROUP BY category"
            );
        }
        return Database::fetchAll(
            "SELECT category, COUNT(*) as total,
             SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented,
             SUM(CASE WHEN status IN ('planned','in_progress') THEN 1 ELSE 0 END) as in_progress
             FROM csi_register WHERE {$where} GROUP BY category",
            $params
        );
    }
}
