<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../services/MailService.php';

/**
 * POST /api/tickets/{id}/vendor-request
 * Body: { vendor_id? | email?, message }
 *
 * Routing rule:
 *  - vendor_id given  -> vendor.integration_mode decides:
 *      api  : push to vendor API (api_base_url), fail = error
 *      email: mail to vendor.email
 *      both : try API first, fall back to mail if the API call fails
 *  - email given      -> always mail (free-text recipient, no vendor record)
 * On success the ticket moves to awaiting_vendor; replies move it back to in_progress.
 */
class VendorRequestController
{
    private const ALLOWED_ROLES = ['admin', 'agent', 'manager', 'customer_admin'];
    private const BLOCKED_STATUSES = ['resolved', 'closed', 'cancelled', 'rejected'];

    public function send(Request $request): void
    {
        $role = Auth::user()['role'] ?? '';
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            Response::error('Forbidden: insufficient permissions', 403);
        }

        $ticketId = (int)$request->param('id');
        $ticket = Ticket::find($ticketId);
        if (!$ticket) Response::error('Ticket not found', 404);
        if (in_array($ticket['status'], self::BLOCKED_STATUSES, true)) {
            Response::error('Ticket is ' . $ticket['status'] . ' and cannot be sent to a vendor', 422);
        }

        $data = $request->all();
        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') Response::error('message is required', 422);
        if (mb_strlen($message) > 5000) Response::error('message is too long (max 5000 characters)', 422);

        $vendor = null;
        $email = null;
        if (!empty($data['vendor_id'])) {
            $vendor = Vendor::find((int)$data['vendor_id']);
            if (!$vendor || !$vendor['is_active']) Response::error('Vendor not found or inactive', 422);
            if ((int)$vendor['customer_id'] !== (int)$ticket['customer_id']) {
                Response::error('Vendor belongs to another customer', 422);
            }
        } elseif (!empty($data['email'])) {
            $email = strtolower(trim($data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('Invalid email address', 422);
        } else {
            Response::error('Provide either vendor_id or email', 422);
        }

        $mode = $vendor['integration_mode'] ?? 'email';
        $externalRef = null;
        $channel = null;

        // 1) API push
        if ($vendor && in_array($mode, ['api', 'both'], true)) {
            $result = $this->pushToApi($vendor, $ticket, $message);
            if ($result['ok']) {
                $channel = 'api';
                $externalRef = $result['external_ref'];
            } elseif ($mode === 'api') {
                Response::error('Vendor API did not accept the ticket: ' . $result['error'], 502);
            }
            // mode 'both' falls through to mail
        }

        // 2) Mail
        if ($channel === null) {
            $to = $vendor ? $vendor['email'] : $email;
            if (!$to) Response::error('Vendor has no email address', 422);
            if (!MailService::sendVendorRequest($ticketId, $to, $message)) {
                Response::error('The email could not be sent. The ticket was not changed.', 502);
            }
            $channel = 'email';
            $email = $to;
        }

        $recipient = $vendor ? $vendor['name'] . ' <' . ($vendor['email'] ?? $vendor['api_base_url']) . '>' : $email;
        $update = [
            'status' => 'awaiting_vendor',
            'vendor_id' => $vendor ? (int)$vendor['id'] : null,
            'vendor_requested_at' => date('Y-m-d H:i:s'),
        ];
        if ($externalRef) $update['external_ref'] = $externalRef;
        Database::update('tickets', $update, 'id = ?', [$ticketId]);

        Database::insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'user_id' => Auth::user()['id'] ?? null,
            'body' => "Sent to vendor via {$channel}: {$recipient}\n\n{$message}",
            'is_internal' => 1,
            'source' => 'system',
        ]);
        Logger::log('vendor_request', 'tickets', $ticketId, "Sent to vendor via {$channel}: {$recipient}");

        Response::success(Ticket::find($ticketId), 'Ticket sent to vendor', 200);
    }

    /** @return array{ok:bool, external_ref:?string, error:?string} */
    private function pushToApi(array $vendor, array $ticket, string $message): array
    {
        $url = rtrim((string)$vendor['api_base_url'], '/') . '/tickets';
        $payload = [
            'ticket_number' => $ticket['ticket_number'],
            'type' => $ticket['type'],
            'title' => $ticket['title'],
            'description' => $ticket['description'],
            'priority' => $ticket['priority'],
            'message' => $message,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) return ['ok' => false, 'external_ref' => null, 'error' => $err ?: 'connection failed'];
        if ($status < 200 || $status >= 300) return ['ok' => false, 'external_ref' => null, 'error' => "HTTP {$status}"];

        $json = json_decode($body, true);
        $ref = is_array($json) ? ($json['external_ref'] ?? $json['reference'] ?? null) : null;
        return ['ok' => true, 'external_ref' => $ref ? substr((string)$ref, 0, 255) : null, 'error' => null];
    }
}
