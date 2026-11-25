<?php
require_once __DIR__ . '/../core/Database.php';

class Problem
{
    public static function find(int $id): ?array
    {
        $problem = Database::fetch(
            "SELECT p.*, t.* FROM problems p JOIN tickets t ON p.ticket_id = t.id WHERE p.id = ?",
            [$id]
        );
        if ($problem) {
            $problem['related_incidents'] = Database::fetchAll(
            "SELECT i.id, t.ticket_number, t.title, t.status, t.priority FROM problem_incidents pi
                 JOIN incidents i ON pi.incident_id = i.id
                 JOIN tickets t ON i.ticket_id = t.id",
                [$id]
            );
        }
        return $problem;
    }

    public static function create(int $ticketId, array $data): int
    {
        $data['ticket_id'] = $ticketId;
        return Database::insert('problems', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('problems', $data, 'id = ?', [$id]);
    }

    public static function linkIncidents(int $problemId, array $incidentIds): void
    {
        foreach ($incidentIds as $incidentId) {
            Database::insert('problem_incidents', ['problem_id' => $problemId, 'incident_id' => (int)$incidentId]);
        }
    }

    public static function markKnownError(int $id, string $errorId): void
    {
        Database::update('problems', ['known_error' => 1, 'known_error_id' => $errorId, 'workaround' => ''], 'id = ?', [$id]);
        $problem = Database::fetch("SELECT ticket_id FROM problems WHERE id = ?", [$id]);
        if ($problem) {
            Database::update('tickets', ['status' => 'known_error'], 'id = ?', [$problem['ticket_id']]);
        }
    }
}
