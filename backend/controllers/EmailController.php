<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/VendorReply.php';
require_once __DIR__ . '/../core/Env.php';

class EmailController
{
    public function receive(Request $request): void
    {
        // Fail closed: without a configured token nobody can post inbound mail.
        $expected = (string)Env::get('INBOUND_EMAIL_TOKEN', '');
        $given = (string)($_SERVER['HTTP_X_INBOUND_TOKEN'] ?? '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            Response::error('Unauthorized', 401);
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            Response::error('No email content received', 400);
        }

        $data = $request->all();
        if (!empty($data['email'])) {
            $parsed = $data['email'];
            $parsed['from_email'] = $data['from'] ?? $parsed['from_email'] ?? '';
            $parsed['subject'] = $data['subject'] ?? $parsed['subject'] ?? '';
            $parsed['body_text'] = $data['body'] ?? $parsed['body'] ?? $parsed['body_text'] ?? '';
            $parsed['body_html'] = $data['body_html'] ?? $parsed['body_html'] ?? null;
            $parsed['message_id'] = $data['message_id'] ?? $parsed['message_id'] ?? null;
            $parsed['in_reply_to'] = $data['in_reply_to'] ?? $parsed['in_reply_to'] ?? null;
        } else {
            $parsed = MailService::parseIncomingEmail($raw);
        }

        if (!$parsed || !$parsed['from_email']) {
            Response::error('Could not parse email', 400);
        }

        $fromEmail = $parsed['from_email'];
        $subject = $parsed['subject'];
        $bodyText = $parsed['body_text'];
        $bodyHtml = $parsed['body_html'];
        $messageId = $parsed['message_id'];
        $inReplyTo = $parsed['in_reply_to'];

        $user = Database::fetch(
            "SELECT id, name, customer_id FROM users WHERE LOWER(email) = ? AND is_active = 1",
            [strtolower($fromEmail)]
        );

        $customerId = $parsed['customer_id'];
        if (!$customerId && $user) {
            $customerId = $user['customer_id'];
        }
        if (!$customerId) {
            $customerId = self::resolveCustomerFromRecipient($request->input('to', ''));
        }

        $existingTicket = $parsed['ticket'];
        if (!$existingTicket && $inReplyTo) {
            $log = Database::fetch(
                "SELECT ticket_id FROM email_log WHERE message_id = ? AND direction = 'outbound' LIMIT 1",
                [$inReplyTo]
            );
            if ($log && $log['ticket_id']) {
                $existingTicket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$log['ticket_id']]);
            }
        }

        if ($existingTicket) {
            $ticketId = (int)$existingTicket['id'];
            $commentText = self::stripReplyText($bodyText);
            if ($existingTicket['status'] === 'awaiting_vendor' && self::isVendorSender($existingTicket, $fromEmail)) {
                // Vendor answer: stored as vendor comment and moves the ticket to in_progress.
                $vendorName = $fromEmail;
                if (!empty($existingTicket['vendor_id'])) {
                    $v = Database::fetch("SELECT name FROM vendors WHERE id = ?", [$existingTicket['vendor_id']]);
                    if ($v) $vendorName = $v['name'];
                }
                VendorReply::apply($ticketId, $vendorName, $commentText ?: null, null, null, $user['id'] ?? null, 'email');
            } elseif ($commentText) {
                $commentId = Database::insert('ticket_comments', [
                    'ticket_id' => $ticketId,
                    'user_id' => $user['id'] ?? null,
                    'body' => "[Email from {$fromEmail}]\n\n{$commentText}",
                    'is_internal' => 0,
                ]);
                Logger::log('email_reply', 'tickets', $ticketId, "Email reply from {$fromEmail}");
            }

            MailService::logInbound(
                $fromEmail,
                $request->input('to', ''),
                $subject,
                $bodyText,
                $bodyHtml,
                $messageId,
                $inReplyTo,
                $ticketId,
                $customerId
            );

            $ticket = Ticket::find($ticketId);
            Response::success($ticket, 'Ticket updated via email');
        } else {
            if (!$customerId) {
                MailService::logInbound(
                    $fromEmail,
                    $request->input('to', ''),
                    $subject,
                    $bodyText,
                    $bodyHtml,
                    $messageId,
                    $inReplyTo,
                    null,
                    null,
                    'failed'
                );
                Response::error('No customer found for sender', 400);
            }

            $cleanBody = self::stripReplyText($bodyText);
            $title = !empty($subject) && !str_starts_with($subject, '(no subject)')
                ? $subject
                : substr($cleanBody, 0, 100);

            $ticketData = [
                'type' => $request->input('type', 'incident'),
                'title' => $title,
                'description' => $cleanBody ? "[Email from {$fromEmail}]\n\n{$cleanBody}" : "Email from {$fromEmail}: {$subject}",
                'source' => 'email',
                'customer_id' => $customerId,
                'email_message_id' => $messageId,
                'email_from' => $fromEmail,
            ];

            if ($user) {
                $ticketData['reported_by'] = (int)$user['id'];
                $ticketData['requester_id'] = (int)$user['id'];
            }

            $ticketId = Ticket::create($ticketData);

            MailService::logInbound(
                $fromEmail,
                $request->input('to', ''),
                $subject,
                $bodyText,
                $bodyHtml,
                $messageId,
                $inReplyTo,
                $ticketId,
                $customerId
            );

            $ticket = Ticket::find($ticketId);
            Response::success($ticket, 'Ticket created from email', 201);
        }
    }

    public function test(Request $request): void
    {
        $ticketId = (int)$request->input('ticket_id');
        if (!$ticketId) {
            Response::error('ticket_id is required', 400);
        }
        MailService::sendTicketCreated($ticketId);
        Response::success(null, 'Test email sent');
    }

    /** A vendor status change requires the sender to be the vendor's address or one we mailed this ticket to. */
    private static function isVendorSender(array $ticket, string $fromEmail): bool
    {
        $from = strtolower(trim($fromEmail));
        if ($from === '') return false;
        if (!empty($ticket['vendor_id'])) {
            $v = Database::fetch("SELECT email FROM vendors WHERE id = ?", [$ticket['vendor_id']]);
            if ($v && strtolower((string)$v['email']) === $from) return true;
        }
        $sent = Database::fetch(
            "SELECT id FROM email_log WHERE ticket_id = ? AND direction = 'outbound' AND LOWER(to_email) = ? LIMIT 1",
            [$ticket['id'], $from]
        );
        return (bool)$sent;
    }

    private static function stripReplyText(string $body): string
    {
        $lines = preg_split('/\r?\n/', $body);
        $clean = [];
        foreach ($lines as $line) {
            if (preg_match('/^>+ ?/', $line)) continue;
            if (preg_match('/^-- ?$/', trim($line))) break;
            if (preg_match('/^_+/', $line)) break;
            if (preg_match('/^On .+ wrote:$/', $line)) break;
            if (preg_match('/^From:.*/i', $line)) break;
            if (preg_match('/^Sent:.*/i', $line)) break;
            if (preg_match('/^To:.*/i', $line)) break;
            if (preg_match('/^Subject:.*/i', $line)) break;
            $clean[] = $line;
        }
        return trim(implode("\n", $clean));
    }

    private static function resolveCustomerFromRecipient(string $to): ?int
    {
        if (empty($to)) return null;
        $parts = explode('@', $to);
        $local = strtolower(trim($parts[0] ?? ''));
        $domain = strtolower(trim($parts[1] ?? ''));

        $rows = Database::fetchAll("SELECT id, settings FROM customers WHERE is_active = 1");
        foreach ($rows as $row) {
            $settings = json_decode($row['settings'] ?? '{}', true);
            $inbound = strtolower($settings['email']['inbound_email'] ?? '');
            if ($inbound === $to || $inbound === $local) {
                return (int)$row['id'];
            }
        }
        return null;
    }
}
