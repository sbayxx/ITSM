<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Database.php';

class AuditLogController
{
    public function index(Request $request): void
    {
        $where = ['1=1'];
        $bindings = [];

        if ($request->query('table_name')) {
            $where[] = 'al.table_name = ?';
            $bindings[] = $request->query('table_name');
        }
        if ($request->query('action')) {
            $where[] = 'al.action = ?';
            $bindings[] = $request->query('action');
        }
        if ($request->query('user_id')) {
            $where[] = 'al.user_id = ?';
            $bindings[] = (int)$request->query('user_id');
        }
        if ($request->query('search')) {
            $where[] = '(al.summary LIKE ? OR al.username LIKE ?)';
            $like = '%' . $request->query('search') . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }
        if ($request->query('date_from')) {
            $where[] = 'al.created_at >= ?';
            $bindings[] = $request->query('date_from') . ' 00:00:00';
        }
        if ($request->query('date_to')) {
            $where[] = 'al.created_at <= ?';
            $bindings[] = $request->query('date_to') . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $total = Database::fetch(
            "SELECT COUNT(*) as count FROM audit_log al WHERE {$whereClause}",
            $bindings
        )['count'];

        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(1, (int)$request->query('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $items = Database::fetchAll(
            "SELECT al.*
             FROM audit_log al
             WHERE {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        Response::json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
        ]);
    }

    public function tables(): void
    {
        $tables = Database::fetchAll(
            "SELECT DISTINCT table_name FROM audit_log WHERE table_name IS NOT NULL ORDER BY table_name"
        );
        Response::success(array_column($tables, 'table_name'));
    }

    public function actions(): void
    {
        $actions = Database::fetchAll(
            "SELECT DISTINCT action FROM audit_log WHERE action IS NOT NULL ORDER BY action"
        );
        Response::success(array_column($actions, 'action'));
    }
}
