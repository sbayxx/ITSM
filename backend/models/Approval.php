<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';

class Approval
{
    public static function find(int $id): ?array
    {
        $scope = Auth::customerScope('a');
        $params = array_merge([$id], $scope['params']);
        return Database::fetch("SELECT a.*, u.name as approver_name, d.name as decided_by_name,
                t.reported_by, t.requester_id, t.type as ticket_type, t.ticket_number
            FROM approvals a
            LEFT JOIN users u ON a.approver_id = u.id
            LEFT JOIN users d ON a.decided_by = d.id
            JOIN tickets t ON t.id = a.ticket_id
            WHERE a.id = ? AND {$scope['sql']}", $params);
    }

    public static function findByTicket(int $ticketId): array
    {
        $scope = Auth::customerScope('a');
        $params = array_merge([$ticketId], $scope['params']);
        return Database::fetchAll(
            "SELECT a.*, u.name as approver_name, d.name as decided_by_name
             FROM approvals a
             LEFT JOIN users u ON a.approver_id = u.id
             LEFT JOIN users d ON a.decided_by = d.id
             WHERE a.ticket_id = ? AND {$scope['sql']}
             ORDER BY a.step_order ASC",
            $params
        );
    }

    public static function create(array $data): int
    {
        if (empty($data['customer_id']) && !empty($data['ticket_id'])) {
            $ticket = Database::fetch("SELECT customer_id FROM tickets WHERE id = ?", [$data['ticket_id']]);
            $data['customer_id'] = $ticket['customer_id'] ?? null;
        }
        return Database::insert('approvals', $data);
    }

    /**
     * A step may only be decided when it is the lowest still-pending step of the
     * chain. This turns the stored step_order from a cosmetic display attribute
     * into a real sequential gate (ITIL 4: ordered approval workflow).
     */
    public static function isCurrentStep(int $ticketId, int $stepOrder): bool
    {
        $row = Database::fetch(
            "SELECT MIN(step_order) as min_step FROM approvals
             WHERE ticket_id = ? AND status = 'pending'",
            [$ticketId]
        );
        return (int)($row['min_step'] ?? 0) === $stepOrder;
    }

    /**
     * Centralised guard for deciding an approval step.
     * Returns null when the step may be decided, otherwise [message, httpStatus].
     */
    public static function guardDecision(array $approval): ?array
    {
        if ($approval['status'] !== 'pending') {
            return ['Approval already ' . $approval['status'], 422];
        }
        $user = Auth::user();
        if (!$user) {
            return ['Not authenticated', 401];
        }
        $userId = (int)$user['id'];

        // ITIL separation of duties: requester/reporter may never decide, not even as admin.
        if ($userId === (int)($approval['requester_id'] ?? 0) || $userId === (int)($approval['reported_by'] ?? 0)) {
            return ['You cannot decide on your own request - separation of duties', 403];
        }
        if (!self::isCurrentStep((int)$approval['ticket_id'], (int)$approval['step_order'])) {
            return ['This step is not active yet - earlier approval steps must be completed first', 409];
        }
        if (!self::hasAuthority($approval, $user)) {
            return ['You are not authorized to decide on this approval', 403];
        }
        return null;
    }

    public static function hasAuthority(array $approval, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user) return false;
        if (in_array($user['role'], ['admin'], true)) return true;
        if ($approval['approver_type'] === 'user' && (int)$approval['approver_id'] === (int)$user['id']) return true;
        if ($approval['approver_type'] === 'role' && $approval['approver_role'] === $user['role']) return true;
        return false;
    }

    public static function approve(int $id, int $userId, string $comments = ''): void
    {
        Database::update('approvals', [
            'status' => 'approved',
            'decided_by' => $userId,
            'comments' => $comments,
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $approval = self::find($id);
        if ($approval) {
            self::checkTicketCompletion($approval['ticket_id']);
        }
    }

    public static function reject(int $id, int $userId, string $comments = ''): void
    {
        $approval = self::find($id);
        Database::update('approvals', [
            'status' => 'rejected',
            'decided_by' => $userId,
            'comments' => $comments,
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // A rejection is terminal for the chain - close out the remaining steps
        // instead of leaving them pending forever with no path to completion.
        if ($approval) {
            Database::update('approvals', ['status' => 'skipped'], 'ticket_id = ? AND status = \'pending\' AND step_order > ?', [$approval['ticket_id'], (int)$approval['step_order']]);
            self::checkTicketCompletion($approval['ticket_id']);
        }
    }

    public static function generateForServiceRequest(int $ticketId, ?int $catalogItemId): void
    {
        if ($catalogItemId) {
            $item = Database::fetch("SELECT * FROM service_catalog WHERE id = ?", [$catalogItemId]);
            if ($item && $item['requires_approval']) {
                $chain = $item['approval_chain'] ? json_decode($item['approval_chain'], true) : null;
                if (!empty($chain)) {
                    foreach ($chain as $i => $step) {
                        self::create([
                            'ticket_id' => $ticketId,
                            'step_order' => $i + 1,
                            'approver_type' => $step['type'] ?? 'role',
                            'approver_id' => ($step['type'] ?? 'role') === 'user' ? ($step['user_id'] ?? null) : null,
                            'approver_role' => ($step['type'] ?? 'role') === 'role' ? ($step['role'] ?? 'manager') : null,
                        ]);
                    }
                } else {
                    self::create([
                        'ticket_id' => $ticketId,
                        'step_order' => 1,
                        'approver_type' => 'role',
                        'approver_role' => 'manager',
                    ]);
                }
                Database::update('tickets', ['status' => 'pending_approval'], 'id = ?', [$ticketId]);
                Database::update('service_requests', ['approval_status' => 'pending'], 'ticket_id = ?', [$ticketId]);
                return;
            }
        }
        $sr = Database::fetch("SELECT id FROM service_requests WHERE ticket_id = ?", [$ticketId]);
        if ($sr) {
            Database::update('service_requests', ['approval_status' => 'approved'], 'id = ?', [$sr['id']]);
        }
    }

    public static function generateForChange(int $ticketId, array $changeData): void
    {
        $type = $changeData['change_type'] ?? 'normal';
        if ($type === 'standard') {
            Database::update('tickets', ['status' => 'approved'], 'id = ?', [$ticketId]);
            return;
        }
        $chain = $changeData['approval_chain'] ?? null;
        if (is_string($chain)) $chain = json_decode($chain, true);
        if (!empty($chain)) {
            foreach ($chain as $i => $step) {
                self::create([
                    'ticket_id' => $ticketId,
                    'step_order' => $i + 1,
                    'approver_type' => $step['type'] ?? 'role',
                    'approver_id' => ($step['type'] ?? 'role') === 'user' ? ($step['user_id'] ?? null) : null,
                    'approver_role' => ($step['type'] ?? 'role') === 'role' ? ($step['role'] ?? 'manager') : null,
                ]);
            }
        } else {
            self::create([
                'ticket_id' => $ticketId,
                'step_order' => 1,
                'approver_type' => 'role',
                'approver_role' => 'manager',
            ]);
        }
        Database::update('tickets', ['status' => 'pending_approval'], 'id = ?', [$ticketId]);
    }

    public static function isFullyApproved(int $ticketId): bool
    {
        $pending = Database::fetch(
            "SELECT COUNT(*) as count FROM approvals WHERE ticket_id = ? AND status = 'pending'",
            [$ticketId]
        )['count'];
        return $pending === 0;
    }

    public static function hasRejection(int $ticketId): bool
    {
        $rejected = Database::fetch(
            "SELECT COUNT(*) as count FROM approvals WHERE ticket_id = ? AND status = 'rejected'",
            [$ticketId]
        )['count'];
        return $rejected > 0;
    }

    private static function checkTicketCompletion(int $ticketId): void
    {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) return;

        if (self::hasRejection($ticketId)) {
            if ($ticket['type'] === 'service_request') {
                Database::update('service_requests', [
                    'approval_status' => 'rejected',
                    'fulfillment_notes' => 'Approval rejected',
                ], 'ticket_id = ?', [$ticketId]);
                Database::update('tickets', ['status' => 'rejected', 'closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            } elseif ($ticket['type'] === 'change') {
                Database::update('tickets', ['status' => 'rejected', 'closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            }
            return;
        }

        if (self::isFullyApproved($ticketId)) {
            if ($ticket['type'] === 'service_request') {
                $sr = Database::fetch("SELECT id FROM service_requests WHERE ticket_id = ?", [$ticketId]);
                if ($sr) {
                    Database::update('service_requests', ['approval_status' => 'approved', 'approved_at' => date('Y-m-d H:i:s')], 'id = ?', [$sr['id']]);
                }
                Database::update('tickets', ['status' => 'in_fulfillment'], 'id = ?', [$ticketId]);
            } elseif ($ticket['type'] === 'change') {
                $change = Database::fetch("SELECT * FROM changes WHERE ticket_id = ?", [$ticketId]);
                if ($change && !$change['cab_required']) {
                    Database::update('tickets', ['status' => 'approved'], 'id = ?', [$ticketId]);
                }
            }
        }
    }
}
