<?php
require_once __DIR__ . '/../core/Database.php';

class SlaContract
{
    public static function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM sla_contracts WHERE id = ?", [$id]);
    }

    public static function all(): array
    {
        return Database::fetchAll("SELECT * FROM sla_contracts ORDER BY FIELD(priority,'critical','high','medium','low')");
    }

    public static function create(array $data): int
    {
        return Database::insert('sla_contracts', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('sla_contracts', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('sla_contracts', 'id = ?', [$id]);
    }

    public static function getComplianceReport(): array
    {
        $contracts = self::all();
        $report = [];
        $scope = Auth::customerScope('t');
        foreach ($contracts as $sla) {
            $params = [$sla['priority']];
            $whereClose = 't.priority = ? AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            if ($scope['sql'] !== '1=1') {
                $whereClose .= ' AND ' . $scope['sql'];
                $params = array_merge($params, $scope['params']);
            }
            $total = Database::fetch(
                "SELECT COUNT(*) as count FROM tickets t WHERE {$whereClose}",
                $params
            )['count'];
            $breached = Database::fetch(
                "SELECT COUNT(*) as count FROM tickets t WHERE {$whereClose} AND t.sla_breached = 1",
                $params
            )['count'];
            $compliant = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100;
            $report[] = [
                'priority' => $sla['priority'],
                'total' => (int)$total,
                'breached' => (int)$breached,
                'compliant_percent' => $compliant,
                'response_target' => $sla['response_time_minutes'],
                'resolution_target' => $sla['resolution_time_minutes'],
            ];
        }
        return $report;
    }
}
