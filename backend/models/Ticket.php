<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../helpers/Pagination.php';
require_once __DIR__ . '/../helpers/PriorityMatrix.php';
require_once __DIR__ . '/../helpers/SlaCalculator.php';
require_once __DIR__ . '/Approval.php';

class Ticket
{
    /**
     * Roles allowed to read internal notes. Deliberately narrow: managers and
     * customer_admins work the queue but do not get privileged commentary.
     */
    public const INTERNAL_NOTE_ROLES = ['admin', 'agent'];

    public static function generateNumber(string $type): string
    {
        $prefixes = ['incident' => 'INC', 'service_request' => 'SR', 'problem' => 'PBL', 'change' => 'CHG'];
        $prefix = $prefixes[$type] ?? 'TKT';
        $year = date('y');
        $count = Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE ticket_number LIKE ?", ["{$prefix}-{$year}%"])['c'];
        return sprintf("%s-%s-%04d", $prefix, $year, $count + 1);
    }

    public static function canSeeInternalNotes(): bool
    {
        return in_array(Auth::role(), self::INTERNAL_NOTE_ROLES, true);
    }

    public static function find(int $id): ?array
    {
        $scope = Auth::customerScope('t');
        $params = array_merge([$id], $scope['params']);
        $ticket = Database::fetch("SELECT t.*, r.name as reported_by_name, req.name as requester_name, a.name as assigned_to_name
            FROM tickets t
            LEFT JOIN users r ON t.reported_by = r.id
            LEFT JOIN users req ON t.requester_id = req.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.id = ? AND {$scope['sql']}", $params);
        if ($ticket) {
            $ticket['type_data'] = self::getTypeData($ticket);
            $ticket['approvals'] = Approval::findByTicket($id);
            $ticket['comments'] = Database::fetchAll(
                "SELECT tc.*, u.name as user_name FROM ticket_comments tc
                 LEFT JOIN users u ON tc.user_id = u.id
                 WHERE tc.ticket_id = ? ORDER BY tc.created_at ASC",
                [$id]
            );
            if (!self::canSeeInternalNotes()) {
                $ticket['comments'] = array_values(array_filter($ticket['comments'], fn($c) => !$c['is_internal']));
            }
            $ticket['attachments'] = Database::fetchAll(
                "SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at DESC",
                [$id]
            );
            $ticket['status_history'] = Database::fetchAll(
                "SELECT tsh.*, u.name as user_name FROM ticket_status_history tsh
                 LEFT JOIN users u ON tsh.user_id = u.id
                 WHERE tsh.ticket_id = ? ORDER BY tsh.created_at ASC",
                [$id]
            );
        }
        return $ticket;
    }

    public static function findByNumber(string $number): ?array
    {
        $scope = Auth::customerScope('t');
        $params = array_merge([$number], $scope['params']);
        $ticket = Database::fetch("SELECT * FROM tickets t WHERE t.ticket_number = ? AND {$scope['sql']}", $params);
        if ($ticket) {
            $ticket['type_data'] = self::getTypeData($ticket);
        }
        return $ticket;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        $customerScope = Auth::customerScope('t');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 't.customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['type'])) {
            $where[] = 't.type = ?';
            $bindings[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 't.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = ?';
            $bindings[] = $filters['priority'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = ?';
            $bindings[] = (int)$filters['assigned_to'];
        }
        if (!empty($filters['reported_by'])) {
            $where[] = 't.reported_by = ?';
            $bindings[] = (int)$filters['reported_by'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(t.title LIKE ? OR t.ticket_number LIKE ? OR t.description LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }
        if (!empty($filters['organization_id'])) {
            $where[] = 't.organization_id = ?';
            $bindings[] = (int)$filters['organization_id'];
        }
        if (isset($filters['sla_breached'])) {
            $where[] = 't.sla_breached = ?';
            $bindings[] = (int)$filters['sla_breached'];
        }
        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM tickets t WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT t.*, r.name as reported_by_name, req.name as requester_name,
                    a.name as assigned_to_name, c.name as customer_name
             FROM tickets t
             LEFT JOIN users r ON t.reported_by = r.id
             LEFT JOIN users req ON t.requester_id = req.id
             LEFT JOIN users a ON t.assigned_to = a.id
             LEFT JOIN customers c ON t.customer_id = c.id
             WHERE {$whereClause}
             ORDER BY t.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
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
        if (empty($data['requester_id'])) {
            $data['requester_id'] = $data['reported_by'] ?? Auth::id();
        }
        $data['ticket_number'] = self::generateNumber($data['type']);
        $priority = PriorityMatrix::calculate($data['impact'] ?? 'medium', $data['urgency'] ?? 'medium');
        $data['priority'] = $data['priority'] ?? $priority;
        $ticketId = Database::insert('tickets', $data);
        $sla = SlaCalculator::getSlaForPriority($data['priority']);
        if ($sla) {
            Database::update('tickets', [
                'sla_response_deadline' => SlaCalculator::calculateResponseDeadline($data['priority']),
                'sla_resolution_deadline' => SlaCalculator::calculateResolutionDeadline($data['priority']),
            ], 'id = ?', [$ticketId]);
        }
        self::logStatusChange($ticketId, null, $data['status'] ?? 'new');
        Logger::log('create', 'tickets', $ticketId, 'Ticket created: ' . $data['ticket_number']);
        return $ticketId;
    }

    public static function update(int $id, array $data): void
    {
        $old = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
        if (isset($data['status']) && $data['status'] !== $old['status']) {
            self::logStatusChange($id, $old['status'], $data['status']);
            if (in_array($data['status'], ['resolved', 'closed'])) {
                $data['resolution_date'] = date('Y-m-d H:i:s');
                if ($data['status'] === 'closed') {
                    $data['closed_at'] = date('Y-m-d H:i:s');
                }
            }
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('tickets', $data, 'id = ?', [$id]);
        Logger::log('update', 'tickets', $id, 'Ticket updated', $old, $data);
    }

    public static function delete(int $id): void
    {
        $old = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
        Database::delete('tickets', 'id = ?', [$id]);
        Logger::log('delete', 'tickets', $id, 'Ticket deleted: ' . ($old['ticket_number'] ?? ''), $old);
    }

    private static function logStatusChange(int $ticketId, ?string $from, string $to): void
    {
        Database::insert('ticket_status_history', [
            'ticket_id' => $ticketId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => Auth::id(),
        ]);
    }

    private static function getTypeData(array $ticket): ?array
    {
        $map = [
            'incident' => ['table' => 'incidents', 'fk' => 'ticket_id'],
            'service_request' => ['table' => 'service_requests', 'fk' => 'ticket_id'],
            'problem' => ['table' => 'problems', 'fk' => 'ticket_id'],
            'change' => ['table' => 'changes', 'fk' => 'ticket_id'],
        ];
        if (!isset($map[$ticket['type']])) return null;
        $m = $map[$ticket['type']];
        return Database::fetch("SELECT * FROM {$m['table']} WHERE {$m['fk']} = ?", [$ticket['id']]);
    }

    public static function addComment(int $ticketId, string $body, bool $isInternal = false): int
    {
        $id = Database::insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'user_id' => Auth::id(),
            'body' => $body,
            'is_internal' => $isInternal ? 1 : 0,
        ]);
        Logger::log('comment', 'tickets', $ticketId, 'Comment added');
        return $id;
    }

    public static function getDashboardStats(): array
    {
        $user = Auth::user();
        $userId = Auth::id();
        $role = Auth::role();

        $conditions = [];
        $params = [];

        if ($role !== 'admin') {
            $conditions[] = '(assigned_to = ? OR reported_by = ?)';
            $params = [$userId, $userId];
        }

        $customerScope = Auth::customerScope('tickets');
        if ($customerScope['sql'] !== '1=1') {
            $conditions[] = $customerScope['sql'];
            $params = array_merge($params, $customerScope['params']);
        }

        $baseWhere = $conditions ? implode(' AND ', $conditions) : '1=1';
        $baseParams = $params;

        $openTickets = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets WHERE {$baseWhere} AND status NOT IN ('closed','resolved')",
            $baseParams
        )['count'];

        $slaBreached = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets WHERE {$baseWhere} AND sla_breached = 1 AND status NOT IN ('closed','resolved')",
            $baseParams
        )['count'];

        $byPriority = Database::fetchAll(
            "SELECT priority, COUNT(*) as count FROM tickets WHERE {$baseWhere} AND status NOT IN ('closed','resolved') GROUP BY priority",
            $baseParams
        );

        $byType = Database::fetchAll(
            "SELECT type, COUNT(*) as count FROM tickets WHERE {$baseWhere} AND status NOT IN ('closed','resolved') GROUP BY type",
            $baseParams
        );

        $resolvedToday = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets WHERE {$baseWhere} AND DATE(resolution_date) = CURDATE()",
            $baseParams
        )['count'];

        return [
            'open_tickets' => (int)$openTickets,
            'sla_breached' => (int)$slaBreached,
            'by_priority' => $byPriority,
            'by_type' => $byType,
            'resolved_today' => (int)$resolvedToday,
            'open_by_status' => self::getOpenByStatus($baseWhere, $baseParams),
        ];
    }

    private static function getOpenByStatus(string $where, array $params): array
    {
        return Database::fetchAll(
            "SELECT status, COUNT(*) as count FROM tickets WHERE {$where} AND status NOT IN ('closed','resolved') GROUP BY status ORDER BY count DESC",
            $params
        );
    }
}
