<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

class ServiceRequest
{
    public static function find(int $id): ?array
    {
        $scope = Auth::customerScope('t');
        $params = array_merge([$id], $scope['params']);
        return Database::fetch(
            "SELECT sr.*, t.* FROM service_requests sr JOIN tickets t ON sr.ticket_id = t.id
             WHERE sr.id = ? AND {$scope['sql']}",
            $params
        );
    }

    public static function create(int $ticketId, array $data): int
    {
        $data['ticket_id'] = $ticketId;
        if (isset($data['fulfillment_data']) && is_array($data['fulfillment_data'])) {
            $data['fulfillment_data'] = json_encode($data['fulfillment_data']);
        }
        return Database::insert('service_requests', $data);
    }

    public static function update(int $id, array $data): void
    {
        if (isset($data['fulfillment_data']) && is_array($data['fulfillment_data'])) {
            $data['fulfillment_data'] = json_encode($data['fulfillment_data']);
        }
        Database::update('service_requests', $data, 'id = ?', [$id]);
    }

    public static function approve(int $id, int $approvedBy): void
    {
        Database::update('service_requests', [
            'approval_status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        $sr = Database::fetch("SELECT ticket_id FROM service_requests WHERE id = ?", [$id]);
        if ($sr) {
            Database::update('tickets', ['status' => 'in_fulfillment'], 'id = ?', [$sr['ticket_id']]);
        }
    }

    public static function reject(int $id, int $approvedBy, string $notes = ''): void
    {
        Database::update('service_requests', [
            'approval_status' => 'rejected',
            'approved_by' => $approvedBy,
            'fulfillment_notes' => $notes,
        ], 'id = ?', [$id]);
        $sr = Database::fetch("SELECT ticket_id FROM service_requests WHERE id = ?", [$id]);
        if ($sr) {
            Database::update('tickets', ['status' => 'rejected', 'closure_notes' => $notes, 'closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$sr['ticket_id']]);
        }
    }
}
