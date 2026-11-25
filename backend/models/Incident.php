<?php
require_once __DIR__ . '/../core/Database.php';

class Incident
{
    public static function find(int $id): ?array
    {
        $incident = Database::fetch(
            "SELECT i.*, t.* FROM incidents i JOIN tickets t ON i.ticket_id = t.id WHERE i.id = ?",
            [$id]
        );
        if ($incident) {
            $incident['related_problems'] = Database::fetchAll(
                "SELECT p.id, p.ticket_id, t.ticket_number, t.title FROM problem_incidents pi
                 JOIN problems p ON pi.problem_id = p.id
                 JOIN tickets t ON p.ticket_id = t.id
                 WHERE pi.incident_id = ?",
                [$id]
            );
        }
        return $incident;
    }

    public static function create(int $ticketId, array $data): int
    {
        $data['ticket_id'] = $ticketId;
        return Database::insert('incidents', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('incidents', $data, 'id = ?', [$id]);
    }

    public static function escalate(int $id): void
    {
        self::update($id, ['is_major' => 1]);
        $incident = Database::fetch("SELECT ticket_id FROM incidents WHERE id = ?", [$id]);
        if ($incident) {
            Database::update('tickets', ['is_major' => 1, 'priority' => 'critical'], 'id = ?', [$incident['ticket_id']]);
            Logger::log('escalate', 'incidents', $id, 'Incident escalated to major');
        }
    }

    public static function linkToProblem(int $incidentId, int $problemId): void
    {
        Database::insert('problem_incidents', ['incident_id' => $incidentId, 'problem_id' => $problemId]);
    }
}
