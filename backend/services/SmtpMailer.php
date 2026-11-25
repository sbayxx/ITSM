<?php
require_once __DIR__ . '/../core/Database.php';

/**
 * Minimal SMTP client (PLAIN/LOGIN auth, STARTTLS on 587, SSL on 465, plain on 25).
 * Returns true on success, false on any failure. All I/O has a timeout.
 */
class SmtpMailer
{
    public static function send(
        string $host,
        int $port,
        string $encryption, // 'tls' (STARTTLS), 'ssl', or '' (plain)
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $inReplyTo = null
    ): bool {
        $timeout = 20;
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$socket) return false;
        stream_set_timeout($socket, $timeout);

        $read = function () use ($socket): string {
            $r = '';
            while ($line = fgets($socket, 512)) {
                $r .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $r;
        };
        $cmd = function (string $c) use ($socket, $read): string {
            if ($c !== '') fwrite($socket, $c . "\r\n");
            return $read();
        };

        $greeting = $cmd('');
        if (substr($greeting, 0, 3) !== '220') { fclose($socket); return false; }

        $ehlo = $cmd('EHLO itsm.governit.dk');
        if (substr($ehlo, 0, 3) !== '250') { fclose($socket); return false; }

        // STARTTLS upgrade
        if ($encryption === 'tls') {
            $tls = $cmd('STARTTLS');
            if (substr($tls, 0, 3) !== '220') { fclose($socket); return false; }
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) { fclose($socket); return false; }
            $ehlo = $cmd('EHLO itsm.governit.dk');
            if (substr($ehlo, 0, 3) !== '250') { fclose($socket); return false; }
        }

        // AUTH
        if ($username !== '' && $password !== '') {
            $auth = $cmd('AUTH LOGIN');
            if (substr($auth, 0, 3) !== '334') { fclose($socket); return false; }
            $u = $cmd(base64_encode($username));
            if (substr($u, 0, 3) !== '334') { fclose($socket); return false; }
            $p = $cmd(base64_encode($password));
            if (substr($p, 0, 3) !== '235') { fclose($socket); return false; }
        }

        $from = $cmd("MAIL FROM:<{$fromEmail}>");
        if (substr($from, 0, 3) !== '250') { fclose($socket); return false; }

        $to = $cmd("RCPT TO:<{$toEmail}>");
        if (substr($to, 0, 3) !== '250' && substr($to, 0, 3) !== '251') { fclose($socket); return false; }

        $data = $cmd('DATA');
        if (substr($data, 0, 3) !== '354') { fclose($socket); return false; }

        $messageId = '<' . bin2hex(random_bytes(16)) . '@itsm.governit.dk>';
        $headers = "From: " . self::encodeHeader($fromName) . " <{$fromEmail}>\r\n";
        $headers .= "To: {$toEmail}\r\n";
        $headers .= "Subject: " . self::encodeHeader($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Message-ID: {$messageId}\r\n";
        if ($inReplyTo) {
            $headers .= "In-Reply-To: <{$inReplyTo}>\r\n";
            $headers .= "References: <{$inReplyTo}>\r\n";
        }
        $headers .= "\r\n";

        // Dot-stuffing: lines starting with '.' must be escaped
        $body = str_replace("\r\n.\r\n", "\r\n..\r\n", str_replace("\n", "\r\n", $htmlBody));
        fwrite($socket, $headers . $body . "\r\n.\r\n");

        $result = $cmd('');
        $cmd('QUIT');
        fclose($socket);

        return substr($result, 0, 3) === '250';
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
