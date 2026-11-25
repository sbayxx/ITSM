<?php
require_once __DIR__ . '/../core/Database.php';

class Change
{
    public static function find(int $id): ?array
    {
        $change = Database::fetch(
            "SELECT c.*, t.* FROM changes c JOIN tickets t ON c.ticket_id = t.id WHERE c.id = ?",
            [$id]
        );
        if ($change) {
            $change['cab_votes'] = Database::fetchAll(
                "SELECT cv.*, u.name as user_name FROM cab_votes cv
                 JOIN users u ON cv.user_id = u.id
                 WHERE cv.change_id = ? ORDER BY cv.voted_at",
                [$id]
            );
        }
        return $change;
    }

    public static function create(int $ticketId, array $data): int
    {
        $data['ticket_id'] = $ticketId;
        return Database::insert('changes', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('changes', $data, 'id = ?', [$id]);
    }

    public static function addCabVote(int $changeId, int $userId, string $vote, ?string $comments = null): void
    {
        $existing = Database::fetch(
            "SELECT id FROM cab_votes WHERE change_id = ? AND user_id = ?",
            [$changeId, $userId]
        );
        if ($existing) {
            Database::update('cab_votes', ['vote' => $vote, 'comments' => $comments, 'voted_at' => date('Y-m-d H:i:s')], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('cab_votes', ['change_id' => $changeId, 'user_id' => $userId, 'vote' => $vote, 'comments' => $comments]);
        }
        self::updateCabDecision($changeId);
    }

    public static function updateCabDecision(int $changeId): void
    {
        $votes = Database::fetchAll(
            "SELECT vote, COUNT(*) as count FROM cab_votes WHERE change_id = ? GROUP BY vote",
            [$changeId]
        );
        $approve = 0; $reject = 0;
        foreach ($votes as $v) {
            if ($v['vote'] === 'approve') $approve = $v['count'];
            if ($v['vote'] === 'reject') $reject = $v['count'];
        }
        $decision = 'pending';
        if ($reject > 0) $decision = 'rejected';
        elseif ($approve >= 2) $decision = 'approved';
        Database::update('changes', ['cab_decision' => $decision], 'id = ?', [$changeId]);
        $change = Database::fetch("SELECT ticket_id FROM changes WHERE id = ?", [$changeId]);
        if ($change) {
            if ($decision === 'approved') {
                Database::update('tickets', ['status' => 'approved'], 'id = ?', [$change['ticket_id']]);
            } elseif ($decision === 'rejected') {
                Database::update('tickets', ['status' => 'rejected', 'closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$change['ticket_id']]);
            }
        }
    }

    public static function implement(int $id): void
    {
        Database::update('changes', ['implemented_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $change = Database::fetch("SELECT ticket_id FROM changes WHERE id = ?", [$id]);
        if ($change) {
            Database::update('tickets', ['status' => 'implemented'], 'id = ?', [$change['ticket_id']]);
        }
    }

    public static function review(int $id, string $notes): void
    {
        Database::update('changes', ['reviewed_at' => date('Y-m-d H:i:s'), 'review_notes' => $notes], 'id = ?', [$id]);
        $change = Database::fetch("SELECT ticket_id, review_required FROM changes WHERE id = ?", [$id]);
        if ($change) {
            Database::update('tickets', ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$change['ticket_id']]);
        }
    }
}
