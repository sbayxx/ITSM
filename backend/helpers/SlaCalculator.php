<?php
require_once __DIR__ . '/../core/Database.php';

class SlaCalculator
{
    public static function getSlaForPriority(string $priority): ?array
    {
        return Database::fetch(
            "SELECT * FROM sla_contracts WHERE priority = ? AND is_active = 1 LIMIT 1",
            [$priority]
        );
    }

    public static function calculateResponseDeadline(string $priority, ?string $createdAt = null): ?string
    {
        $sla = self::getSlaForPriority($priority);
        if (!$sla) return null;
        $createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $minutes = (int)$sla['response_time_minutes'];
        return self::addBusinessMinutes($createdAt, $minutes);
    }

    public static function calculateResolutionDeadline(string $priority, ?string $createdAt = null): ?string
    {
        $sla = self::getSlaForPriority($priority);
        if (!$sla) return null;
        $createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $minutes = (int)$sla['resolution_time_minutes'];
        return self::addBusinessMinutes($createdAt, $minutes);
    }

    public static function isBreached(string $deadline): bool
    {
        return strtotime($deadline) < time();
    }

    private static function addBusinessMinutes(string $from, int $minutes): string
    {
        $businessStart = 8;
        $businessEnd = 17;
        $timestamp = strtotime($from);
        $remaining = $minutes;
        while ($remaining > 0) {
            $hour = (int)date('G', $timestamp);
            $dayOfWeek = (int)date('N', $timestamp);
            if ($dayOfWeek >= 6) {
                $timestamp = strtotime('next Monday 08:00', $timestamp);
                continue;
            }
            if ($hour < $businessStart) {
                $timestamp = strtotime(date('Y-m-d', $timestamp) . ' 08:00');
                continue;
            }
            if ($hour >= $businessEnd) {
                $timestamp = strtotime('tomorrow 08:00', $timestamp);
                continue;
            }
            $availableMinutes = ($businessEnd - $hour) * 60 - (int)date('i', $timestamp);
            $consume = min($remaining, $availableMinutes);
            $timestamp += $consume * 60;
            $remaining -= $consume;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    public static function checkAndLogBreaches(): void
    {
        $slaContracts = Database::fetchAll("SELECT * FROM sla_contracts WHERE is_active = 1");
        foreach ($slaContracts as $sla) {
            $tickets = Database::fetchAll(
                "SELECT t.* FROM tickets t
                 WHERE t.priority = ? AND t.status NOT IN ('closed','resolved')
                 AND t.sla_response_deadline IS NOT NULL
                 AND t.sla_breached = 0
                 AND t.sla_response_deadline < NOW()",
                [$sla['priority']]
            );
            foreach ($tickets as $ticket) {
                Database::update('tickets', ['sla_breached' => 1], 'id = ?', [$ticket['id']]);
                Logger::log('sla_breach', 'tickets', $ticket['id'], 'SLA response time breached');
            }
        }
    }
}
