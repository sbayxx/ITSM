<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Database.php';

class ReportController
{
    private function customerCondition(): array
    {
        $scope = Auth::customerScope('t');
        // Callers interpolate this directly after an existing WHERE term, so the
        // unrestricted case must be an empty fragment - returning '1=1' produced
        // "WHERE ... 1=1 GROUP BY" and a syntax error for every admin request.
        if ($scope['sql'] === '1=1') {
            return ['sql' => '', 'params' => []];
        }
        if ($scope['sql'] === '1=0') {
            return ['sql' => 'AND 1=0', 'params' => []];
        }
        return ['sql' => "AND {$scope['sql']}", 'params' => $scope['params']];
    }

    public function ticketVolume(Request $request): void
    {
        $period = $request->query('period', '30');
        $cc = $this->customerCondition();

        $rows = Database::fetchAll(
            "SELECT DATE(created_at) as date, type, COUNT(*) as count
             FROM tickets t
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             {$cc['sql']}
             GROUP BY DATE(created_at), type
             ORDER BY date ASC",
            array_merge([(int)$period], $cc['params'])
        );
        $labels = [];
        $datasets = ['incident' => [], 'service_request' => [], 'problem' => [], 'change' => []];
        $dateRange = [];
        $start = strtotime("-{$period} days");
        for ($i = $start; $i <= time(); $i += 86400) {
            $dateRange[date('Y-m-d', $i)] = true;
        }
        foreach ($dateRange as $date => $_) {
            $labels[] = $date;
            foreach (array_keys($datasets) as $type) {
                $datasets[$type][$date] = 0;
            }
        }
        foreach ($rows as $row) {
            if (isset($datasets[$row['type']])) {
                $datasets[$row['type']][$row['date']] = (int)$row['count'];
            }
        }
        $result = [];
        foreach ($datasets as $type => $data) {
            $result[] = ['label' => $type, 'data' => array_values($data)];
        }
        Response::success(['labels' => $labels, 'datasets' => $result]);
    }

    public function statusDistribution(): void
    {
        $cc = $this->customerCondition();
        $rows = Database::fetchAll(
            "SELECT type, status, COUNT(*) as count
             FROM tickets t
             WHERE 1=1 {$cc['sql']}
             GROUP BY type, status
             ORDER BY type, status",
            $cc['params']
        );
        Response::success($rows);
    }

    public function slaCompliance(Request $request): void
    {
        $result = SlaContract::getComplianceReport();
        Response::success($result);
    }

    public function csiProgress(): void
    {
        $categories = Database::fetchAll(
            "SELECT category,
             COUNT(*) as total,
             SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as completed,
             SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
             FROM csi_register
             GROUP BY category"
        );
        Response::success($categories);
    }

    public function agentPerformance(Request $request): void
    {
        $period = (int)$request->query('period', 30);
        $cc = $this->customerCondition();

        $rows = Database::fetchAll(
            "SELECT u.id, u.name,
             COUNT(t.id) as assigned_count,
             SUM(CASE WHEN t.status IN ('closed','resolved') THEN 1 ELSE 0 END) as resolved_count,
             AVG(CASE WHEN t.status IN ('closed','resolved') AND t.resolution_date IS NOT NULL
                 THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolution_date) END) as avg_resolution_hours
             FROM tickets t
             RIGHT JOIN users u ON t.assigned_to = u.id AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             WHERE u.role IN ('agent', 'admin', 'manager')
             {$cc['sql']}
             GROUP BY u.id, u.name
             ORDER BY resolved_count DESC",
            array_merge([$period], $cc['params'])
        );
        Response::success($rows);
    }

    // ------------------------------------------------------------------
    // Compliance reports (GDPR + ISO/IEC 27001:2022)
    // ------------------------------------------------------------------

    /**
     * GET /api/reports/compliance?period=30&customer_id=1
     * Governance view: admin sees everything (optionally filtered by customer),
     * customer_admin sees their tenants only. Agents/users get 403.
     */
    /**
     * GET /api/reports/checklists?customer_id=1
     * Framework checklists (GDPR, ISO 27001, D-mærket) evaluated against
     * the actual ITSM content for the given customer.
     */
    public function checklists(Request $request): void
    {
        if (!Auth::isAdmin() && !Auth::isCustomerAdmin()) {
            Response::error('Forbidden: insufficient permissions', 403);
            return;
        }
        $customerFilter = (int)$request->query('customer_id', 0);
        $ids = $this->scopeIds($customerFilter ?: null);

        // For a single customer, pass that id; for global, pass null
        $customerId = null;
        if ($customerFilter && in_array($customerFilter, $ids, true)) {
            $customerId = $customerFilter;
        }

        require_once __DIR__ . '/../services/ComplianceChecklist.php';
        $frameworks = ComplianceChecklist::evaluate($customerId);

        Response::success([
            'frameworks' => $frameworks,
            'generated_at' => date('c'),
        ]);
    }

    public function compliance(Request $request): void
    {
        if (!Auth::isAdmin() && !Auth::isCustomerAdmin()) {
            Response::error('Forbidden: insufficient permissions', 403);
            return;
        }
        $period = max(1, (int)$request->query('period', 30));
        $customerFilter = (int)$request->query('customer_id', 0);
        $ids = $this->scopeIds($customerFilter ?: null);
        $cutoff = date('Y-m-d 00:00:00', strtotime("-{$period} days"));

        $gdpr = [
            $this->cardDsr($cutoff, $ids),
            $this->cardConsent($cutoff, $ids),
            $this->cardRetention($cutoff, $ids),
            $this->cardAccountability($cutoff, $ids),
        ];
        $iso = [
            $this->cardAccess($cutoff, $ids),
            $this->cardIncident($cutoff, $ids),
            $this->cardChange($cutoff, $ids),
            $this->cardLogging($cutoff, $ids),
            $this->cardCmdb($cutoff, $ids),
        ];

        Response::success([
            'generated_at' => date('c'),
            'period_days' => $period,
            'customer_id' => $customerFilter ?: null,
            'frameworks' => [
                $this->framework('gdpr', 'GDPR', 'Regulation (EU) 2016/679', $gdpr),
                $this->framework('iso27001', 'ISO/IEC 27001:2022', 'Annex A control monitoring', $iso),
            ],
        ]);
    }

    /** null = unrestricted (admin), [] = no access, non-empty = allowed customer ids. */
    private function scopeIds(?int $customerId): ?array
    {
        if (Auth::isAdmin()) {
            return $customerId ? [$customerId] : null;
        }
        $ids = Auth::accessibleCustomerIds();
        if ($customerId) {
            $ids = array_values(array_intersect($ids, [$customerId]));
        }
        return $ids;
    }

    /** Fragment restricting a customer_id column of a row. */
    private function tenantCol(?array $ids, string $col): array
    {
        if ($ids === null) {
            return ['', []];
        }
        if (!$ids) {
            return ['AND 1=0', []];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ["AND {$col} IN ({$ph})", $ids];
    }

    /** Fragment restricting a column holding users.id through the owning user's tenants. */
    private function userCol(?array $ids, string $col): array
    {
        if ($ids === null) {
            return ['', []];
        }
        if (!$ids) {
            return ['AND 1=0', []];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return [
            "AND {$col} IN (SELECT u2.id FROM users u2 WHERE u2.customer_id IN ({$ph})"
            . " OR EXISTS(SELECT 1 FROM customer_user cu WHERE cu.user_id = u2.id AND cu.customer_id IN ({$ph})))",
            array_merge($ids, $ids),
        ];
    }

    /** Fragment restricting rows of the users table itself ($alias, e.g. 'u'). */
    private function usersCol(?array $ids, string $alias): array
    {
        if ($ids === null) {
            return ['', []];
        }
        if (!$ids) {
            return ['AND 1=0', []];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return [
            "AND ({$alias}.customer_id IN ({$ph})"
            . " OR EXISTS(SELECT 1 FROM customer_user cu WHERE cu.user_id = {$alias}.id AND cu.customer_id IN ({$ph})))",
            array_merge($ids, $ids),
        ];
    }

    private function pctStr(?float $v): string
    {
        return $v === null ? '—' : round($v) . '%';
    }

    private function framework(string $id, string $name, string $reference, array $cards): array
    {
        $assessed = array_values(array_filter($cards, static fn($c) => $c['status'] !== 'not_assessed'));
        if (!$assessed) {
            return [
                'id' => $id, 'name' => $name, 'reference' => $reference,
                'score' => 0, 'status' => 'not_assessed',
                'assessed' => 0, 'total' => count($cards), 'cards' => $cards,
            ];
        }
        $score = (int) round(array_sum(array_column($assessed, 'score')) / count($assessed));
        $statuses = array_column($assessed, 'status');
        $status = !in_array('non_compliant', $statuses, true)
            ? (in_array('partial', $statuses, true) ? 'partial' : 'compliant')
            : 'non_compliant';
        return [
            'id' => $id, 'name' => $name, 'reference' => $reference,
            'score' => $score, 'status' => $status,
            'assessed' => count($assessed), 'total' => count($cards), 'cards' => $cards,
        ];
    }

    private function card(
        string $id, string $title, string $reference, string $icon,
        string $status, int $score, array $metrics, array $detail = []
    ): array {
        $c = [
            'id' => $id, 'title' => $title, 'reference' => $reference, 'icon' => $icon,
            'status' => $status, 'score' => max(0, min(100, $score)), 'metrics' => $metrics,
        ];
        if ($detail) {
            $c['detail'] = $detail;
        }
        return $c;
    }

    // ---- GDPR cards (Art. 12/17, Art. 7, Art. 5(1)(e), Art. 5(2)) ----

    private function cardDsr(string $cutoff, ?array $ids): array
    {
        [$us, $up] = $this->userCol($ids, 'er.user_id');
        $row = Database::fetch(
            "SELECT COUNT(*) total,
                    COALESCE(SUM(er.status IN ('requested','verified','in_progress')),0) open_count,
                    COALESCE(SUM(er.status NOT IN ('completed','rejected') AND er.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)),0) overdue,
                    COALESCE(SUM(er.completed_at >= ?),0) closed_period,
                    COALESCE(SUM(er.completed_at IS NOT NULL OR er.status = 'rejected'),0) closed_total,
                    COALESCE(SUM((er.completed_at IS NOT NULL AND DATEDIFF(er.completed_at, er.created_at) <= 31)
                              OR (er.status = 'rejected' AND DATEDIFF(er.updated_at, er.created_at) <= 31)),0) on_time,
                    AVG(CASE WHEN er.completed_at IS NOT NULL THEN DATEDIFF(er.completed_at, er.created_at) END) avg_days
             FROM erasure_requests er
             WHERE 1=1 {$us}",
            array_merge([$cutoff], $up)
        ) ?: [];
        $total = (int)($row['total'] ?? 0);
        $open = (int)($row['open_count'] ?? 0);
        $overdue = (int)($row['overdue'] ?? 0);
        $closedPeriod = (int)($row['closed_period'] ?? 0);
        $closedTotal = (int)($row['closed_total'] ?? 0);
        $onTime = (int)($row['on_time'] ?? 0);
        $avgDays = $row['avg_days'] ?? null;

        $onTimePct = $closedTotal > 0 ? round($onTime / $closedTotal * 100) : null;
        if ($total === 0) {
            $status = 'compliant';
            $score = 100;
        } else {
            $score = max(0, min(100, (int)round($onTimePct ?? 100) - $overdue * 10));
            $status = $overdue > 0 ? 'non_compliant' : ($score >= 95 ? 'compliant' : ($score >= 70 ? 'partial' : 'non_compliant'));
        }

        $recent = Database::fetchAll(
            "SELECT er.id, er.status, er.created_at, u.name user_name
             FROM erasure_requests er
             LEFT JOIN users u ON u.id = er.user_id
             WHERE 1=1 {$us}
             ORDER BY er.created_at DESC LIMIT 5",
            $up
        );
        $detail = ['title' => 'Recent requests', 'rows' => array_map(static fn($r) => [
            'left' => '#' . $r['id'] . ' · ' . ($r['user_name'] ?? 'Unknown') . ' · ' . date('Y-m-d', strtotime($r['created_at'])),
            'right' => $r['status'],
        ], $recent)];

        return $this->card(
            'dsr', 'Data Subject Rights', 'GDPR Art. 12(3), 17', 'timer',
            $status, $score,
            [
                ['label' => 'Requests (total)', 'value' => (string)$total],
                ['label' => 'Open', 'value' => (string)$open],
                ['label' => 'Overdue (>1 month)', 'value' => (string)$overdue, 'tone' => $overdue > 0 ? 'bad' : 'good'],
                ['label' => 'Completed in period', 'value' => (string)$closedPeriod],
                ['label' => 'Avg cycle time', 'value' => $avgDays !== null ? round((float)$avgDays, 1) . ' d' : '—'],
                ['label' => 'Closed within deadline', 'value' => $this->pctStr($onTimePct !== null ? (float)$onTimePct : null)],
            ],
            $detail
        );
    }

    private function cardConsent(string $cutoff, ?array $ids): array
    {
        [$uu, $uup] = $this->usersCol($ids, 'u');
        $users = Database::fetch(
            "SELECT COUNT(*) active_users, COALESCE(SUM(u.gdpr_consent_at IS NOT NULL),0) consented
             FROM users u
             WHERE u.is_active = 1 AND u.deleted_at IS NULL {$uu}",
            $uup
        ) ?: [];
        $active = (int)($users['active_users'] ?? 0);
        $consented = (int)($users['consented'] ?? 0);

        [$gu, $gup] = $this->userCol($ids, 'g.user_id');
        $events = Database::fetch(
            "SELECT COUNT(*) events, COALESCE(SUM(g.status = 'withdrawn'),0) withdrawals
             FROM gdpr_consent_log g
             WHERE g.created_at >= ? {$gu}",
            array_merge([$cutoff], $gup)
        ) ?: [];
        $eventCount = (int)($events['events'] ?? 0);
        $withdrawals = (int)($events['withdrawals'] ?? 0);

        if ($active === 0) {
            $status = 'not_assessed';
            $score = 0;
            $coverage = null;
        } else {
            $coverage = round($consented / $active * 100);
            $status = $coverage >= 95 ? 'compliant' : ($coverage >= 70 ? 'partial' : 'non_compliant');
            $score = (int)$coverage;
        }

        return $this->card(
            'consent', 'Consent Management', 'GDPR Art. 7, 5(2)', 'check',
            $status, $score,
            [
                ['label' => 'Active users', 'value' => (string)$active],
                ['label' => 'With consent recorded', 'value' => (string)$consented],
                ['label' => 'Consent coverage', 'value' => $this->pctStr($coverage !== null ? (float)$coverage : null)],
                ['label' => 'Consent events (period)', 'value' => (string)$eventCount],
                ['label' => 'Withdrawals (period)', 'value' => (string)$withdrawals, 'tone' => $withdrawals > 0 ? 'bad' : 'good'],
            ]
        );
    }

    private function cardRetention(string $cutoff, ?array $ids): array
    {
        $policies = Database::fetchAll('SELECT * FROM data_retention_policies ORDER BY entity_type');
        $entityMap = [
            'audit_log'        => ['table' => 'audit_log', 'col' => 'created_at', 'scope' => 'none'],
            'tickets'          => ['table' => 'tickets', 'col' => 'created_at', 'scope' => 'tenant'],
            'sessions'         => ['table' => 'sessions', 'col' => 'last_activity', 'scope' => 'epoch'],
            'gdpr_consent_log' => ['table' => 'gdpr_consent_log', 'col' => 'created_at', 'scope' => 'user'],
            'erasure_requests' => ['table' => 'erasure_requests', 'col' => 'created_at', 'scope' => 'user'],
            'email_log'        => ['table' => 'email_log', 'col' => 'created_at', 'scope' => 'tenant'],
        ];

        $activePolicies = array_filter($policies, static fn($p) => (int)$p['is_active'] === 1);
        $covered = 0;
        $awaiting = 0;
        $detailRows = [];

        foreach ($activePolicies as $p) {
            $type = $p['entity_type'];
            if (!isset($entityMap[$type])) {
                continue;
            }
            $covered++;
            $conf = $entityMap[$type];
            $pCut = date('Y-m-d H:i:s', strtotime('-' . (int)$p['retention_days'] . ' days'));

            if ($conf['scope'] === 'epoch') {
                $past = Database::fetch("SELECT COUNT(*) c FROM `{$conf['table']}` WHERE {$conf['col']} < ?", [strtotime($pCut)]);
            } else {
                $where = "WHERE {$conf['col']} < ?";
                $params = [$pCut];
                if ($conf['scope'] === 'tenant') {
                    [$ts, $tp] = $this->tenantCol($ids, 'customer_id');
                    $where .= ' ' . $ts;
                    $params = array_merge($params, $tp);
                } elseif ($conf['scope'] === 'user') {
                    [$us2, $up2] = $this->userCol($ids, 'user_id');
                    $where .= ' ' . $us2;
                    $params = array_merge($params, $up2);
                }
                $past = Database::fetch("SELECT COUNT(*) c FROM `{$conf['table']}` {$where}", $params);
            }
            $pastCount = (int)($past['c'] ?? 0);
            $awaiting += $pastCount;
            $detailRows[] = [
                'left' => ucfirst(str_replace('_', ' ', $type)) . ' — ' . (int)$p['retention_days'] . ' d (' . $p['action'] . ')',
                'right' => (string)$pastCount,
            ];
        }

        $lastRun = Database::fetch("SELECT MAX(created_at) m FROM audit_log WHERE action = 'retention_cleanup'");
        $lastRunAt = $lastRun['m'] ?? null;

        $known = count($entityMap);
        $coveragePct = $known > 0 ? round($covered / $known * 100) : 0;
        if ($coveragePct >= 100) {
            $status = $awaiting > 0 ? 'partial' : 'compliant';
        } else {
            $status = $coveragePct >= 60 ? 'partial' : 'non_compliant';
        }
        $score = max(0, $coveragePct - ($awaiting > 0 ? 10 : 0));

        $disabled = count($policies) - count($activePolicies);

        return $this->card(
            'retention', 'Retention & Storage Limitation', 'GDPR Art. 5(1)(e), 17 · ISO A.5.33', 'database',
            $status, $score,
            [
                ['label' => 'Active policies', 'value' => count($activePolicies) . '/' . count($policies)],
                ['label' => 'Record types covered', 'value' => $this->pctStr((float)$coveragePct)],
                ['label' => 'Records past retention', 'value' => (string)$awaiting, 'tone' => $awaiting > 0 ? 'bad' : 'good'],
                ['label' => 'Last retention run', 'value' => $lastRunAt ? date('Y-m-d', strtotime($lastRunAt)) : 'never'],
                ['label' => 'Disabled policies', 'value' => (string)$disabled],
            ],
            ['title' => 'Awaiting disposal (per policy)', 'rows' => $detailRows]
        );
    }

    private function cardAccountability(string $cutoff, ?array $ids): array
    {
        [$au, $aup] = $this->userCol($ids, 'a.user_id');
        $stats = Database::fetch(
            "SELECT COUNT(*) events,
                    COALESCE(SUM(a.user_id IS NOT NULL),0) attributed,
                    COUNT(DISTINCT a.table_name) sources,
                    COUNT(DISTINCT a.user_id) actors
             FROM audit_log a
             WHERE a.created_at >= ? {$au}",
            array_merge([$cutoff], $aup)
        ) ?: [];
        $events = (int)($stats['events'] ?? 0);
        $attributed = (int)($stats['attributed'] ?? 0);
        $sources = (int)($stats['sources'] ?? 0);
        $actors = (int)($stats['actors'] ?? 0);

        $top = Database::fetchAll(
            "SELECT a.action, COUNT(*) c
             FROM audit_log a
             WHERE a.created_at >= ? {$au}
             GROUP BY a.action ORDER BY c DESC LIMIT 5",
            array_merge([$cutoff], $aup)
        );

        if ($events === 0) {
            $status = 'not_assessed';
            $score = 0;
            $attribution = null;
        } else {
            $attribution = round($attributed / $events * 100);
            $status = $attribution >= 95 ? 'compliant' : ($attribution >= 75 ? 'partial' : 'non_compliant');
            $score = (int)$attribution;
        }

        return $this->card(
            'accountability', 'Accountability & Audit Trail', 'GDPR Art. 5(2) · ISO A.8.15', 'search',
            $status, $score,
            [
                ['label' => 'Audit events (period)', 'value' => (string)$events],
                ['label' => 'Attribution (user recorded)', 'value' => $this->pctStr($attribution !== null ? (float)$attribution : null)],
                ['label' => 'Log sources (tables)', 'value' => (string)$sources],
                ['label' => 'Distinct actors', 'value' => (string)$actors],
            ],
            ['title' => 'Top actions (period)', 'rows' => array_map(
                static fn($r) => ['left' => ucfirst(str_replace('_', ' ', $r['action'])), 'right' => $r['c'] . '×'],
                $top
            )]
        );
    }

    // ---- ISO 27001 cards (A.5.15-18, A.5.24-28, A.8.32, A.8.15-16, A.8.9) ----

    private function cardAccess(string $cutoff, ?array $ids): array
    {
        [$uu, $uup] = $this->usersCol($ids, 'u');
        $users = Database::fetch(
            "SELECT COUNT(*) active_users,
                    COALESCE(SUM(u.role IN ('admin','customer_admin','manager')),0) privileged,
                    COALESCE(SUM(u.last_login_at IS NULL),0) never_logged,
                    COALESCE(SUM(u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)),0) dormant,
                    COALESCE(SUM(u.role IN ('admin','customer_admin','manager')
                        AND (u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY))),0) priv_dormant
             FROM users u
             WHERE u.is_active = 1 AND u.deleted_at IS NULL {$uu}",
            $uup
        ) ?: [];
        $active = (int)($users['active_users'] ?? 0);
        $privileged = (int)($users['privileged'] ?? 0);
        $dormant = (int)($users['dormant'] ?? 0);
        $privDormant = (int)($users['priv_dormant'] ?? 0);

        $disabled = Database::fetch(
            "SELECT COUNT(*) c FROM users u WHERE u.is_active = 0 AND u.deleted_at IS NULL {$uu}",
            $uup
        ) ?: [];

        [$au, $aup] = $this->userCol($ids, 'a.user_id');
        $changes = Database::fetch(
            "SELECT COUNT(*) c FROM audit_log a
             WHERE a.table_name = 'users' AND a.action IN ('create','update','delete') AND a.created_at >= ? {$au}",
            array_merge([$cutoff], $aup)
        ) ?: [];

        if ($active === 0) {
            $status = 'not_assessed';
            $score = 0;
            $dormantRate = 0.0;
        } else {
            $dormantRate = round($dormant / $active * 100, 1);
            if ($privDormant === 0 && $dormantRate <= 20) {
                $status = 'compliant';
            } elseif ($privDormant <= 1 && $dormantRate <= 40) {
                $status = 'partial';
            } else {
                $status = 'non_compliant';
            }
            $score = max(0, min(100, (int)round(100 - $dormantRate * 0.5 - $privDormant * 15)));
        }

        return $this->card(
            'access', 'Access Rights & Account Hygiene', 'ISO A.5.15, A.5.16, A.5.18', 'users',
            $status, $score,
            [
                ['label' => 'Active users', 'value' => (string)$active],
                ['label' => 'Privileged accounts', 'value' => (string)$privileged],
                ['label' => 'Dormant (>90 d / never)', 'value' => (string)$dormant, 'tone' => $dormant > 0 ? 'bad' : 'good'],
                ['label' => 'Privileged dormant', 'value' => (string)$privDormant, 'tone' => $privDormant > 0 ? 'bad' : 'good'],
                ['label' => 'Disabled accounts retained', 'value' => (string)($disabled['c'] ?? 0)],
                ['label' => 'Account changes (period)', 'value' => (string)($changes['c'] ?? 0)],
            ]
        );
    }

    private function cardIncident(string $cutoff, ?array $ids): array
    {
        [$t1, $tp1] = $this->tenantCol($ids, 't.customer_id');
        $created = Database::fetch(
            "SELECT COUNT(*) c, COALESCE(SUM(t.is_major = 1),0) majors
             FROM tickets t
             WHERE t.type = 'incident' AND t.created_at >= ? {$t1}",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $createdCount = (int)($created['c'] ?? 0);
        $majors = (int)($created['majors'] ?? 0);

        $resolved = Database::fetch(
            "SELECT COUNT(*) c,
                    AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.resolution_date)) mttr,
                    COALESCE(SUM(t.sla_breached = 1),0) breached,
                    COALESCE(SUM(t.sla_breached = 0),0) met
             FROM tickets t
             WHERE t.type = 'incident' AND t.status IN ('resolved','closed') AND t.resolution_date >= ? {$t1}",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $resolvedCount = (int)($resolved['c'] ?? 0);
        $breached = (int)($resolved['breached'] ?? 0);
        $mttr = $resolved['mttr'] !== null ? round((float)$resolved['mttr'], 1) : null;

        $linked = Database::fetch(
            "SELECT COUNT(DISTINCT t.id) c
             FROM tickets t
             JOIN incidents i ON i.ticket_id = t.id
             JOIN problem_incidents pi ON pi.incident_id = i.id
             WHERE t.type = 'incident' AND t.created_at >= ? {$t1}",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $linkedCount = (int)($linked['c'] ?? 0);

        $slaMet = $resolvedCount > 0 ? round(($resolvedCount - $breached) / $resolvedCount * 100) : null;
        $recurrence = $createdCount > 0 ? round($linkedCount / $createdCount * 100) : 0;

        if ($createdCount === 0 || $resolvedCount === 0) {
            $status = 'not_assessed';
            $score = 0;
        } else {
            $status = $slaMet >= 95 ? 'compliant' : ($slaMet >= 80 ? 'partial' : 'non_compliant');
            // recurrence above the 5% target drags the score down
            $score = max(0, min(100, $slaMet - max(0, $recurrence - 5)));
        }

        return $this->card(
            'incident', 'Incident Management', 'ISO A.5.24–A.5.28', 'siren',
            $status, $score,
            [
                ['label' => 'Incidents created (period)', 'value' => (string)$createdCount],
                ['label' => 'Resolved (period)', 'value' => (string)$resolvedCount],
                ['label' => 'MTTR (hours)', 'value' => $mttr !== null ? (string)$mttr : '—'],
                ['label' => 'SLA met', 'value' => $this->pctStr($slaMet !== null ? (float)$slaMet : null)],
                ['label' => 'Recurrence (→ problem)', 'value' => $this->pctStr((float)$recurrence), 'tone' => $recurrence > 5 ? 'bad' : 'good'],
                ['label' => 'Major incidents', 'value' => (string)$majors],
            ]
        );
    }

    private function cardChange(string $cutoff, ?array $ids): array
    {
        [$t1, $tp1] = $this->tenantCol($ids, 't.customer_id');
        $agg = Database::fetch(
            "SELECT COUNT(*) c,
                    COALESCE(SUM(cg.change_type = 'emergency'),0) emergency,
                    COALESCE(SUM(cg.cab_required = 1),0) cab_req,
                    COALESCE(SUM(cg.implemented_at IS NOT NULL),0) implemented,
                    COALESCE(SUM(cg.review_required = 1),0) review_req,
                    COALESCE(SUM(cg.review_required = 1 AND cg.reviewed_at IS NOT NULL),0) reviewed
             FROM tickets t
             JOIN changes cg ON cg.ticket_id = t.id
             WHERE t.type = 'change' AND t.created_at >= ? {$t1}",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $created = (int)($agg['c'] ?? 0);
        $emergency = (int)($agg['emergency'] ?? 0);
        $cabReq = (int)($agg['cab_req'] ?? 0);
        $reviewReq = (int)($agg['review_req'] ?? 0);
        $reviewed = (int)($agg['reviewed'] ?? 0);

        $decided = Database::fetch(
            "SELECT COUNT(DISTINCT t.id) c
             FROM tickets t
             JOIN changes cg ON cg.ticket_id = t.id
             WHERE t.type = 'change' AND t.created_at >= ? {$t1}
               AND (cg.cab_decision IN ('approved','rejected')
                    OR EXISTS(SELECT 1 FROM approvals a WHERE a.ticket_id = t.id AND a.status IN ('approved','rejected')))",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $decidedCount = (int)($decided['c'] ?? 0);

        $unauthorized = Database::fetch(
            "SELECT COUNT(*) c
             FROM tickets t
             JOIN changes cg ON cg.ticket_id = t.id
             WHERE t.type = 'change' AND t.created_at >= ? {$t1}
               AND cg.cab_required = 1 AND cg.implemented_at IS NOT NULL
               AND cg.cab_decision NOT IN ('approved','rejected')
               AND NOT EXISTS(SELECT 1 FROM approvals a WHERE a.ticket_id = t.id AND a.status IN ('approved','rejected'))",
            array_merge([$cutoff], $tp1)
        ) ?: [];
        $unauthorizedCount = (int)($unauthorized['c'] ?? 0);

        $approvalHours = Database::fetch(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, a.decided_at)) h
             FROM tickets t
             JOIN approvals a ON a.ticket_id = t.id
             WHERE t.type = 'change' AND a.decided_at >= ? {$t1}",
            array_merge([$cutoff], $tp1)
        ) ?: [];

        $coverage = $cabReq > 0 ? round($decidedCount / $cabReq * 100) : 100;
        $emergencyShare = $created > 0 ? round($emergency / $created * 100) : 0;
        $reviewedPct = $reviewReq > 0 ? round($reviewed / $reviewReq * 100) : null;

        if ($created === 0) {
            $status = 'not_assessed';
            $score = 0;
        } elseif ($unauthorizedCount > 0) {
            $status = 'non_compliant';
            $score = max(0, $coverage - $unauthorizedCount * 25);
        } else {
            $status = $coverage >= 95 ? 'compliant' : ($coverage >= 80 ? 'partial' : 'non_compliant');
            $score = $coverage;
        }

        return $this->card(
            'change', 'Change Enablement', 'ISO A.8.32', 'settings',
            $status, $score,
            [
                ['label' => 'Changes created (period)', 'value' => (string)$created],
                ['label' => 'With approval decision', 'value' => $created > 0 ? $coverage . '%' : '—'],
                ['label' => 'Unauthorized changes', 'value' => (string)$unauthorizedCount, 'tone' => $unauthorizedCount > 0 ? 'bad' : 'good'],
                ['label' => 'Emergency share', 'value' => $created > 0 ? $emergencyShare . '%' : '—'],
                ['label' => 'Avg approval time', 'value' => isset($approvalHours['h']) && $approvalHours['h'] !== null
                    ? round((float)$approvalHours['h'], 1) . ' h' : '—'],
                ['label' => 'Post-review completed', 'value' => $this->pctStr($reviewedPct !== null ? (float)$reviewedPct : null)],
            ]
        );
    }

    private function cardLogging(string $cutoff, ?array $ids): array
    {
        [$au, $aup] = $this->userCol($ids, 'a.user_id');
        $audit = Database::fetch(
            "SELECT COUNT(*) events,
                    COALESCE(SUM(a.user_id IS NOT NULL),0) attributed,
                    COUNT(DISTINCT a.table_name) sources
             FROM audit_log a
             WHERE a.created_at >= ? {$au}",
            array_merge([$cutoff], $aup)
        ) ?: [];
        $events = (int)($audit['events'] ?? 0);
        $attributed = (int)($audit['attributed'] ?? 0);
        $sources = (int)($audit['sources'] ?? 0);
        $attribution = $events > 0 ? round($attributed / $events * 100) : null;

        [$ec, $ecp] = $this->tenantCol($ids, 'el.customer_id');
        $email = Database::fetch(
            "SELECT COUNT(*) c,
                    COALESCE(SUM(el.status IN ('sent','received')),0) ok,
                    COALESCE(SUM(el.status = 'failed'),0) failed
             FROM email_log el
             WHERE el.created_at >= ? {$ec}",
            array_merge([$cutoff], $ecp)
        ) ?: [];
        $emailCount = (int)($email['c'] ?? 0);
        $emailOk = (int)($email['ok'] ?? 0);
        $emailFailed = (int)($email['failed'] ?? 0);
        $delivery = $emailCount > 0 ? round($emailOk / $emailCount * 100) : null;
        $failRate = $emailCount > 0 ? $emailFailed / $emailCount * 100 : 0.0;

        if ($events === 0) {
            $status = 'not_assessed';
            $score = 0;
        } else {
            $deliveryScore = $delivery !== null ? $delivery : 100.0;
            if ($failRate > 10) {
                $status = 'non_compliant';
            } elseif (($attribution ?? 0) >= 95 && $failRate <= 5) {
                $status = 'compliant';
            } elseif (($attribution ?? 0) >= 75 && $failRate <= 10) {
                $status = 'partial';
            } else {
                $status = 'non_compliant';
            }
            $score = (int)round((($attribution ?? 0) + $deliveryScore) / 2);
        }

        return $this->card(
            'logging', 'Logging & Monitoring', 'ISO A.8.15, A.8.16', 'info',
            $status, $score,
            [
                ['label' => 'Audit events (period)', 'value' => (string)$events],
                ['label' => 'Log sources (tables)', 'value' => (string)$sources],
                ['label' => 'Attribution', 'value' => $this->pctStr($attribution !== null ? (float)$attribution : null)],
                ['label' => 'Emails processed', 'value' => (string)$emailCount],
                ['label' => 'Delivery rate', 'value' => $this->pctStr($delivery !== null ? (float)$delivery : null)],
                ['label' => 'Failed emails', 'value' => (string)$emailFailed, 'tone' => $emailFailed > 0 ? 'bad' : 'good'],
            ]
        );
    }

    private function cardCmdb(string $cutoff, ?array $ids): array
    {
        [$ciS, $ciP] = $this->tenantCol($ids, 'ci.customer_id');
        $ci = Database::fetch(
            "SELECT COUNT(*) c,
                    COALESCE(SUM(ci.owner_id IS NOT NULL),0) with_owner,
                    COALESCE(SUM(ci.location IS NOT NULL AND ci.location <> ''),0) with_location,
                    COALESCE(SUM(ci.updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)),0) stale
             FROM ci_items ci
             WHERE ci.is_active = 1 {$ciS}",
            $ciP
        ) ?: [];
        $total = (int)($ci['c'] ?? 0);
        $withOwner = (int)($ci['with_owner'] ?? 0);
        $withLocation = (int)($ci['with_location'] ?? 0);
        $stale = (int)($ci['stale'] ?? 0);

        $orphan = Database::fetch(
            "SELECT COUNT(*) c
             FROM ci_items ci
             WHERE ci.is_active = 1 {$ciS}
               AND NOT EXISTS(SELECT 1 FROM ci_relationships r WHERE r.parent_ci_id = ci.id OR r.child_ci_id = ci.id)
               AND NOT EXISTS(SELECT 1 FROM tickets t WHERE t.ci_item_id = ci.id)",
            $ciP
        ) ?: [];
        $orphanCount = (int)($orphan['c'] ?? 0);

        [$tS, $tP] = $this->tenantCol($ids, 't.customer_id');
        $link = Database::fetch(
            "SELECT COUNT(*) c, COALESCE(SUM(t.ci_item_id IS NOT NULL),0) linked
             FROM tickets t
             WHERE t.created_at >= ? {$tS}",
            array_merge([$cutoff], $tP)
        ) ?: [];
        $tickets = (int)($link['c'] ?? 0);
        $linked = (int)($link['linked'] ?? 0);

        if ($total === 0) {
            $status = 'not_assessed';
            $score = 0;
            $completeness = null;
            $stalePct = null;
            $orphanPct = null;
            $linkage = null;
        } else {
            $completeness = round(($withOwner + $withLocation) / (2 * $total) * 100);
            $stalePct = round($stale / $total * 100);
            $orphanPct = round($orphanCount / $total * 100);
            $linkage = $tickets > 0 ? round($linked / $tickets * 100) : 0;
            if ($completeness >= 90 && $orphanPct <= 10) {
                $status = 'compliant';
            } elseif ($completeness >= 70 && $orphanPct <= 25) {
                $status = 'partial';
            } else {
                $status = 'non_compliant';
            }
            $score = max(0, min(100, (int)round(
                $completeness * 0.6 + (100 - $orphanPct) * 0.25 + $linkage * 0.15
            )));
        }

        return $this->card(
            'cmdb', 'Configuration Management', 'ISO A.8.9, A.5.9', 'boxes',
            $status, $score,
            [
                ['label' => 'Active CIs', 'value' => (string)$total],
                ['label' => 'Completeness (owner+location)', 'value' => $this->pctStr($completeness !== null ? (float)$completeness : null)],
                ['label' => 'Stale (>60 d)', 'value' => $this->pctStr($stalePct !== null ? (float)$stalePct : null)],
                ['label' => 'Orphaned (no rel/ticket)', 'value' => $this->pctStr($orphanPct !== null ? (float)$orphanPct : null)],
                ['label' => 'Tickets linked to CI', 'value' => $this->pctStr($linkage !== null ? (float)$linkage : null)],
            ]
        );
    }
}
