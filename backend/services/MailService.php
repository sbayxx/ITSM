<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../services/SmtpMailer.php';

class MailService
{
    public static function sendTicketCreated(int $ticketId): void
    {
        $ticket = self::getTicketWithRelations($ticketId);
        if (!$ticket || !$ticket['requester_email']) return;

        $subject = "[{$ticket['ticket_number']}] {$ticket['title']}";
        $body = self::renderTemplate('ticket_created', $ticket);

        self::sendWithSettings(
            $ticket['customer_id'],
            $ticket['requester_email'],
            $subject,
            $body,
            $ticketId,
            null
        );
    }

    public static function sendTicketUpdated(int $ticketId, array $changes): void
    {
        $ticket = self::getTicketWithRelations($ticketId);
        if (!$ticket || !$ticket['requester_email']) return;

        $subject = "Re: [{$ticket['ticket_number']}] {$ticket['title']}";
        $body = self::renderTemplate('ticket_updated', array_merge($ticket, ['changes' => $changes]));

        self::sendWithSettings(
            $ticket['customer_id'],
            $ticket['requester_email'],
            $subject,
            $body,
            $ticketId,
            $ticket['email_message_id']
        );
    }

    public static function sendCommentAdded(int $ticketId, array $comment): void
    {
        $ticket = self::getTicketWithRelations($ticketId);
        if (!$ticket || !$ticket['requester_email']) return;

        $comment['author_name'] = $comment['user_name'] ?? 'Agent';
        $subject = "Re: [{$ticket['ticket_number']}] {$ticket['title']}";
        $body = self::renderTemplate('comment_added', array_merge($ticket, ['comment' => $comment]));

        self::sendWithSettings(
            $ticket['customer_id'],
            $ticket['requester_email'],
            $subject,
            $body,
            $ticketId,
            $ticket['email_message_id']
        );
    }

    /** Sends a ticket to an external vendor. Subject carries the ticket number so the reply matches back. */
    public static function sendVendorRequest(int $ticketId, string $toEmail, string $message): bool
    {
        $ticket = self::getTicketWithRelations($ticketId);
        if (!$ticket) return false;

        $subject = "[{$ticket['ticket_number']}] {$ticket['title']}";
        $body = self::renderTemplate('vendor_request', array_merge($ticket, [
            'message' => $message,
        ]));

        return self::sendWithSettings(
            $ticket['customer_id'],
            $toEmail,
            $subject,
            $body,
            $ticketId,
            null
        );
    }

    public static function parseIncomingEmail(string $rawEmail): ?array
    {
        $parts = self::parseMimeMessage($rawEmail);
        if (!$parts || !isset($parts['from'])) return null;

        $ticket = null;
        $number = self::extractTicketNumber($parts['subject'] ?? '');
        if ($number) {
            $ticket = Database::fetch(
                "SELECT t.*, u.email as requester_email, u.name as requester_name
                 FROM tickets t
                 LEFT JOIN users u ON t.requester_id = u.id
                 WHERE t.ticket_number = ?",
                [$number]
            );
        }

        $customerId = $ticket ? $ticket['customer_id'] : null;
        if (!$customerId) {
            $email = strtolower(trim($parts['from']));
            $user = Database::fetch("SELECT customer_id FROM users WHERE LOWER(email) = ? AND is_active = 1", [$email]);
            if ($user && $user['customer_id']) {
                $customerId = $user['customer_id'];
            }
        }

        return [
            'from_email' => $parts['from'],
            'from_name' => $parts['from_name'] ?? $parts['from'],
            'subject' => $parts['subject'] ?? '(no subject)',
            'body_text' => $parts['text'] ?? '',
            'body_html' => $parts['html'] ?? null,
            'message_id' => $parts['message_id'] ?? null,
            'in_reply_to' => $parts['in_reply_to'] ?? null,
            'ticket' => $ticket,
            'customer_id' => $customerId,
        ];
    }

    private static function getTicketWithRelations(int $ticketId): ?array
    {
        $ticket = Database::fetch(
            "SELECT t.*, u.email as requester_email, u.name as requester_name
             FROM tickets t
             LEFT JOIN users u ON t.requester_id = u.id
             WHERE t.id = ?",
            [$ticketId]
        );
        return $ticket ?: null;
    }

    private static function sendWithSettings(
        ?int $customerId,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?int $ticketId = null,
        ?string $inReplyTo = null
    ): bool {
        $settings = $customerId ? Customer::getSettings($customerId) : [];
        $emailSettings = $settings['email'] ?? [];

        $fromEmail = $emailSettings['from_email'] ?? 'noreply@itsm.governit.dk';
        $fromName = $emailSettings['from_name'] ?? 'ITSM System';

        $messageId = '<' . bin2hex(random_bytes(16)) . '@itsm.governit.dk>';
        $textBody = strip_tags($htmlBody);

        $headers = [];
        $headers[] = 'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Message-ID: ' . $messageId;
        $headers[] = 'X-Mailer: ITSM/1.0';

        if ($inReplyTo) {
            $headers[] = 'In-Reply-To: <' . $inReplyTo . '>';
            $headers[] = 'References: <' . $inReplyTo . '>';
        }

        $smtpHost = trim($emailSettings['smtp_host'] ?? '');
        if ($smtpHost !== '') {
            $success = SmtpMailer::send(
                $smtpHost,
                (int)($emailSettings['smtp_port'] ?? 587),
                $emailSettings['smtp_encryption'] ?? 'tls',
                $emailSettings['smtp_username'] ?? '',
                $emailSettings['smtp_password'] ?? '',
                $fromEmail,
                $fromName,
                $toEmail,
                $subject,
                $htmlBody,
                $inReplyTo
            );
        } else {
            $success = mail($toEmail, self::encodeHeader($subject), $htmlBody, implode("\r\n", $headers));
        }

        self::logOutbound(
            $ticketId,
            $customerId,
            $toEmail,
            $fromEmail,
            $subject,
            $textBody,
            $htmlBody,
            $messageId,
            $inReplyTo,
            $success ? 'sent' : 'failed',
            $success ? null : 'mail() returned false'
        );
        return $success;
    }

    private static function logOutbound(
        ?int $ticketId,
        ?int $customerId,
        string $toEmail,
        string $fromEmail,
        string $subject,
        ?string $textBody,
        ?string $htmlBody,
        string $messageId,
        ?string $inReplyTo,
        string $status,
        ?string $error = null
    ): void {
        Database::insert('email_log', [
            'ticket_id' => $ticketId,
            'customer_id' => $customerId,
            'direction' => 'outbound',
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_text' => $textBody,
            'body_html' => $htmlBody,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'status' => $status,
            'error_message' => $error,
        ]);
    }

    public static function logInbound(
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $bodyText,
        ?string $bodyHtml,
        ?string $messageId,
        ?string $inReplyTo,
        ?int $ticketId,
        ?int $customerId,
        string $status = 'received'
    ): void {
        Database::insert('email_log', [
            'ticket_id' => $ticketId,
            'customer_id' => $customerId,
            'direction' => 'inbound',
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'status' => $status,
        ]);
    }

    private static function renderTemplate(string $template, array $data): string
    {
        $css = 'body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#333;line-height:1.6;margin:0;padding:0}';
        $css .= '.container{max-width:600px;margin:0 auto;padding:20px}';
        $css .= '.header{background:#2563EB;color:#fff;padding:24px 20px;border-radius:8px 8px 0 0}';
        $css .= '.header h1{margin:0;font-size:20px}';
        $css .= '.content{padding:24px 20px;background:#f9fafb;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px}';
        $css .= '.field{margin-bottom:16px}';
        $css .= '.field-label{font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:600}';
        $css .= '.field-value{font-size:14px;color:#111827}';
        $css .= '.footer{margin-top:24px;font-size:12px;color:#9ca3af;text-align:center}';

        $ticketNumber = htmlspecialchars($data['ticket_number'] ?? '');
        $title = htmlspecialchars($data['title'] ?? '');
        $ticketId = (int)($data['id'] ?? 0);
        $type = ucfirst(str_replace('_', ' ', $data['type'] ?? 'ticket'));

        $statusLabels = ['new' => 'New', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
        $status = $statusLabels[$data['status'] ?? ''] ?? ($data['status'] ?? '');

        switch ($template) {
            case 'ticket_created':
                $desc = nl2br(htmlspecialchars($data['description'] ?? ''));
                return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>{$css}</style></head><body>
<div class="container">
<div class="header"><h1>{$type} Created</h1></div>
<div class="content">
<div class="field"><div class="field-label">Ticket</div><div class="field-value">{$ticketNumber}</div></div>
<div class="field"><div class="field-label">Title</div><div class="field-value">{$title}</div></div>
<div class="field"><div class="field-label">Status</div><div class="field-value">{$status}</div></div>
<div class="field"><div class="field-label">Description</div><div class="field-value">{$desc}</div></div>
<p style="margin-top:20px"><a href="https://itsm.governit.dk/tickets/{$ticketId}" style="display:inline-block;background:#2563EB;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px">View Ticket</a></p>
</div>
<div class="footer">This is an automated notification from the ITSM system.</div>
</div></body></html>
HTML;

            case 'ticket_updated':
                $changesHtml = '';
                foreach (($data['changes'] ?? []) as $field => $change) {
                    $old = htmlspecialchars((string)($change['old'] ?? ''));
                    $new = htmlspecialchars((string)($change['new'] ?? ''));
                    $label = ucfirst(str_replace('_', ' ', $field));
                    $changesHtml .= "<div class=\"field\"><div class=\"field-label\">{$label}</div><div class=\"field-value\">{$old} &rarr; <strong>{$new}</strong></div></div>";
                }
                return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>{$css}</style></head><body>
<div class="container">
<div class="header"><h1>{$type} Updated</h1></div>
<div class="content">
<div class="field"><div class="field-label">Ticket</div><div class="field-value">{$ticketNumber}</div></div>
<div class="field"><div class="field-label">Title</div><div class="field-value">{$title}</div></div>
{$changesHtml}
<p style="margin-top:20px"><a href="https://itsm.governit.dk/tickets/{$ticketId}" style="display:inline-block;background:#2563EB;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px">View Ticket</a></p>
</div>
<div class="footer">This is an automated notification from the ITSM system.</div>
</div></body></html>
HTML;

            case 'comment_added':
                $comment = $data['comment'] ?? [];
                $commentBody = nl2br(htmlspecialchars($comment['body'] ?? ''));
                $author = htmlspecialchars($comment['author_name'] ?? 'Agent');
                $commentCss = '.comment{background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin:12px 0}';
                $commentCss .= '.comment-author{font-weight:600;color:#111827;margin-bottom:4px}';
                $commentCss .= '.comment-body{color:#374151;font-size:14px;line-height:1.5}';
                return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>{$css}{$commentCss}</style></head><body>
<div class="container">
<div class="header"><h1>New Comment on {$ticketNumber}</h1></div>
<div class="content">
<div class="field"><div class="field-label">Ticket</div><div class="field-value">{$ticketNumber}</div></div>
<div class="field"><div class="field-label">Title</div><div class="field-value">{$title}</div></div>
<div class="comment">
<div class="comment-author">{$author}</div>
<div class="comment-body">{$commentBody}</div>
</div>
<p style="margin-top:20px"><a href="https://itsm.governit.dk/tickets/{$ticketId}" style="display:inline-block;background:#2563EB;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px">View Ticket</a></p>
</div>
<div class="footer">This is an automated notification from the ITSM system.</div>
</div></body></html>
HTML;

            case 'vendor_request':
                $vDesc = nl2br(htmlspecialchars($data['description'] ?? ''));
                $vMsg = nl2br(htmlspecialchars($data['message'] ?? ''));
                $vPriority = htmlspecialchars(ucfirst(str_replace('_', ' ', $data['priority'] ?? '')));
                return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>{$css}</style></head><body>
<div class="container">
<div class="header"><h1>Request: {$ticketNumber}</h1></div>
<div class="content">
<div class="field"><div class="field-label">Ticket</div><div class="field-value">{$ticketNumber}</div></div>
<div class="field"><div class="field-label">Title</div><div class="field-value">{$title}</div></div>
<div class="field"><div class="field-label">Priority</div><div class="field-value">{$vPriority}</div></div>
<div class="field"><div class="field-label">Description</div><div class="field-value">{$vDesc}</div></div>
<div class="field"><div class="field-label">Message</div><div class="field-value">{$vMsg}</div></div>
<p style="margin-top:20px;font-size:13px;color:#4b5563">Please reply to this email to update the ticket. Keep the ticket number <strong>{$ticketNumber}</strong> in the subject line.</p>
</div>
<div class="footer">This is an automated notification from the ITSM system.</div>
</div></body></html>
HTML;

            default:
                return '';
        }
    }

    private static function extractTicketNumber(?string $subject): ?string
    {
        if (!$subject) return null;
        // Match the full token with or without brackets. The old bracketed
        // branch returned only the type group ("INC"), so replies with a
        // "[INC-26-0017] ..." subject never matched their ticket.
        if (preg_match('/(?:\[)?((?:INC|SR|PBL|CHG)-\d{2}-\d{4})(?:\])?/', $subject, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function parseMimeMessage(string $raw): ?array
    {
        $lines = preg_split('/\r?\n/', $raw);
        $headers = [];
        $currentHeader = null;
        $bodyStart = 0;

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                $bodyStart = $i + 1;
                break;
            }
            if (preg_match('/^([\w-]+):\s*(.*)/', $line, $m)) {
                $currentHeader = strtolower($m[1]);
                $headers[$currentHeader] = trim($m[2]);
            } elseif ($currentHeader && preg_match('/^\s+(.*)/', $line, $m)) {
                $headers[$currentHeader] .= ' ' . trim($m[1]);
            }
        }

        if (empty($headers)) return null;

        $body = implode("\n", array_slice($lines, $bodyStart));
        $text = '';
        $html = null;

        $contentType = $headers['content-type'] ?? 'text/plain';
        if (stripos($contentType, 'multipart/') === 0) {
            $boundary = self::extractBoundary($contentType);
            if ($boundary) {
                $parts = self::splitMimeParts($body, $boundary);
                foreach ($parts as $part) {
                    $ct = $part['headers']['content-type'] ?? 'text/plain';
                    if (stripos($ct, 'text/plain') !== false) {
                        $text .= $part['body'] . "\n";
                    } elseif (stripos($ct, 'text/html') !== false) {
                        $html = ($html ?? '') . $part['body'];
                    }
                }
            }
        } else {
            if (stripos($contentType, 'text/html') !== false) {
                $html = $body;
            } else {
                $text = $body;
            }
        }

        $text = trim($text);
        $html = $html ? trim($html) : null;

        $from = $headers['from'] ?? '';
        $fromName = '';
        $fromEmail = '';
        if (preg_match('/(.*?)<([^>]+)>/', $from, $m)) {
            $fromName = trim($m[1], ' "\'');
            $fromEmail = strtolower(trim($m[2]));
        } else {
            $fromEmail = strtolower(trim($from));
        }

        $messageId = trim($headers['message-id'] ?? '');
        $messageId = trim($messageId, '<>');

        $inReplyTo = trim($headers['in-reply-to'] ?? '');
        $inReplyTo = trim($inReplyTo, '<>');

        return [
            'from' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $headers['subject'] ?? '',
            'text' => $text,
            'html' => $html,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
        ];
    }

    private static function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary="?([^";\s]+)"?/', $contentType, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function splitMimeParts(string $body, string $boundary): array
    {
        $parts = [];
        $sections = preg_split('/--' . preg_quote($boundary, '/') . '(--)?\s*\r?\n?/', $body);
        array_shift($sections);
        array_pop($sections);

        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            $lines = preg_split('/\r?\n/', $section);
            $partHeaders = [];
            $currentHeader = null;
            $bodyStart = 0;
            foreach ($lines as $i => $line) {
                if (trim($line) === '') {
                    $bodyStart = $i + 1;
                    break;
                }
                if (preg_match('/^([\w-]+):\s*(.*)/', $line, $m)) {
                    $currentHeader = strtolower($m[1]);
                    $partHeaders[$currentHeader] = trim($m[2]);
                } elseif ($currentHeader && preg_match('/^\s+(.*)/', $line, $m)) {
                    $partHeaders[$currentHeader] .= ' ' . trim($m[1]);
                }
            }
            $partBody = implode("\n", array_slice($lines, $bodyStart));
            $parts[] = [
                'headers' => $partHeaders,
                'body' => $partBody,
            ];
        }
        return $parts;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
