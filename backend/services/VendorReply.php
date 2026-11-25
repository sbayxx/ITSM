<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';

class VendorReplyException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}

/**
 * Applies a vendor reply to a ticket. Shared by the vendor API and inbound mail.
 *
 * Rules:
 *  - Only tickets in awaiting_vendor or in_progress accept vendor replies.
 *  - Any reply on an awaiting_vendor ticket moves it to in_progress.
 *  - The vendor may explicitly set in_progress or resolved; resolved is only allowed
 *    from in_progress (a vendor cannot resolve a ticket it has not started).
 *  - Comments are stored with source 'vendor' and are never internal.
 */
class VendorReply
{
    public const ACCEPTING = ['awaiting_vendor', 'in_progress'];
    public const SETTABLE = ['in_progress', 'resolved'];

    public static function apply(
        int $ticketId,
        string $vendorName,
        ?string $comment = null,
        ?string $externalRef = null,
        ?string $requestedStatus = null,
        ?int $userId = null,
        string $channel = 'api'
    ): array {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) throw new VendorReplyException('Ticket not found', 404);
        $current = $ticket['status'];
        if (!in_array($current, self::ACCEPTING, true)) {
            throw new VendorReplyException("Ticket is {$current} and does not accept vendor replies", 409);
        }
        if ($requestedStatus !== null && !in_array($requestedStatus, self::SETTABLE, true)) {
            throw new VendorReplyException("Status '{$requestedStatus}' cannot be set by a vendor", 422);
        }
        if ($requestedStatus === 'resolved' && $current !== 'in_progress') {
            throw new VendorReplyException('Ticket must be in_progress before it can be resolved', 409);
        }

        $update = [];
        if ($current === 'awaiting_vendor') {
            $update['status'] = 'in_progress';
        }
        if ($requestedStatus !== null) {
            $update['status'] = $requestedStatus;
        }
        if (($update['status'] ?? null) === 'resolved') {
            $update['resolution_date'] = date('Y-m-d H:i:s');
        }
        if ($externalRef !== null && $externalRef !== '') {
            $update['external_ref'] = substr($externalRef, 0, 255);
        }
        if ($update) {
            Database::update('tickets', $update, 'id = ?', [$ticketId]);
        }

        $commentId = null;
        if ($comment !== null && trim($comment) !== '') {
            $commentId = Database::insert('ticket_comments', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'body' => "[Vendor: {$vendorName}]\n\n" . trim($comment),
                'is_internal' => 0,
                'source' => 'vendor',
            ]);
        }

        Logger::log('vendor_reply', 'tickets', $ticketId,
            "Vendor reply via {$channel} from {$vendorName}; status {$current} -> " . ($update['status'] ?? $current));

        return [
            'ticket_id' => $ticketId,
            'previous_status' => $current,
            'status' => $update['status'] ?? $current,
            'comment_id' => $commentId,
        ];
    }
}
