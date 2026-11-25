<?php
require_once __DIR__ . '/../core/Database.php';

class GdprModel
{
    public static function recordConsent(int $userId, string $type, string $version, string $status): int
    {
        return Database::insert('gdpr_consent_log', [
            'user_id' => $userId,
            'consent_type' => $type,
            'consent_version' => $version,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    public static function getConsentLog(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM gdpr_consent_log WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    public static function requestErasure(int $userId, int $requestedBy, string $notes = ''): int
    {
        return Database::insert('erasure_requests', [
            'user_id' => $userId,
            'requested_by' => $requestedBy,
            'status' => 'requested',
            'data_scope' => json_encode(['all_user_data' => true]),
            'notes' => $notes,
        ]);
    }

    public static function processErasure(int $requestId, int $adminId): void
    {
        $req = Database::fetch("SELECT * FROM erasure_requests WHERE id = ?", [$requestId]);
        if (!$req) return;
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$req['user_id']]);
        if (!$user) return;
        Database::beginTransaction();
        try {
            Database::update('erasure_requests', [
                'status' => 'in_progress',
                'admin_notes' => 'Processing erasure request',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$requestId]);
            $name = 'Anonymized User';
            $email = 'deleted_' . $req['user_id'] . '_' . time() . '@redacted.local';
            Database::update('users', [
                'name' => $name,
                'email' => $email,
                'phone' => null,
                'job_title' => null,
                'deleted_at' => date('Y-m-d H:i:s'),
                'gdpr_consent_at' => null,
                'gdpr_consent_version' => null,
            ], 'id = ?', [$req['user_id']]);
            Database::update('erasure_requests', [
                'status' => 'completed',
                'anonymized_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$requestId]);
            Logger::log('gdpr_erasure', 'users', $req['user_id'], 'GDPR erasure completed');
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function getErasureRequests(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];
        if (!empty($filters['status'])) {
            $where[] = 'er.status = ?';
            $bindings[] = $filters['status'];
        }
        $whereClause = implode(' AND ', $where);
        $total = Database::fetch(
            "SELECT COUNT(*) as count FROM erasure_requests er WHERE {$whereClause}", $bindings
        )['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT er.*, u.name as user_name, u.email as user_email, r.name as requested_by_name
             FROM erasure_requests er
             LEFT JOIN users u ON er.user_id = u.id
             LEFT JOIN users r ON er.requested_by = r.id
             WHERE {$whereClause}
             ORDER BY er.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}", $bindings);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function exportUserData(int $userId): array
    {
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $tickets = Database::fetchAll("SELECT * FROM tickets WHERE reported_by = ? OR assigned_to = ?", [$userId, $userId]);
        $comments = Database::fetchAll("SELECT * FROM ticket_comments WHERE user_id = ?", [$userId]);
        $consent = self::getConsentLog($userId);
        return [
            'personal_data' => $user,
            'tickets' => $tickets,
            'comments' => $comments,
            'consent_history' => $consent,
        ];
    }

    public static function getDataRetentionPolicies(): array
    {
        return Database::fetchAll("SELECT * FROM data_retention_policies WHERE is_active = 1");
    }

    public static function applyRetentionPolicies(): array
    {
        $policies = self::getDataRetentionPolicies();
        $results = [];
        foreach ($policies as $policy) {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$policy['retention_days']} days"));
            if ($policy['entity_type'] === 'sessions') {
                $count = Database::delete('sessions', 'last_activity < ?', [strtotime($cutoff)]);
            } elseif ($policy['entity_type'] === 'audit_log') {
                $count = Database::delete('audit_log', 'created_at < ?', [$cutoff]);
            }
            $results[] = ['entity' => $policy['entity_type'], 'removed' => $count ?? 0];
        }
        return $results;
    }
}
