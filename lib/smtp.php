<?php
/**
 * Lightweight SMTP mailer — no dependencies required.
 * Supports STARTTLS (Gmail, Outlook, etc.) and plain SMTP.
 *
 * Usage:
 *   $result = smtp_send([
 *       'host'     => 'smtp.gmail.com',
 *       'port'     => 587,
 *       'user'     => 'you@gmail.com',
 *       'pass'     => 'app-password-here',
 *       'from'     => 'you@gmail.com',
 *       'fromName' => 'Your Name',
 *       'to'       => 'recipient@example.com',
 *       'toName'   => 'Recipient Name',
 *       'subject'  => 'Hello',
 *       'html'     => '<h1>Hi</h1>',
 *       'replyTo'  => 'you@gmail.com',  // optional
 *   ]);
 *   // Returns ['success'=>true] or ['success'=>false,'error'=>'...']
 */

function smtp_send(array $opts): array {
    $host     = $opts['host']     ?? '';
    $port     = (int)($opts['port'] ?? 587);
    $user     = $opts['user']     ?? '';
    $pass     = $opts['pass']     ?? '';
    $from     = $opts['from']     ?? $user;
    $fromName = $opts['fromName'] ?? '';
    $to       = $opts['to']       ?? '';
    $toName   = $opts['toName']   ?? '';
    $subject  = $opts['subject']  ?? '(no subject)';
    $html     = $opts['html']     ?? '';
    $replyTo  = $opts['replyTo']  ?? $from;

    if (!$host || !$user || !$pass || !$to) {
        return ['success' => false, 'error' => 'SMTP not configured — missing host, user, pass, or to.'];
    }

    $log = [];

    try {
        $errno = 0; $errstr = '';
        $timeout = 15;

        // Connect
        if ($port === 465) {
            // Implicit TLS (SMTPS)
            $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout);
        } else {
            $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$sock) {
            return ['success' => false, 'error' => "SMTP connect failed: {$errstr} ({$errno})"];
        }

        stream_set_timeout($sock, 30);

        // Helper: read response
        $read = function() use ($sock, &$log) {
            $resp = '';
            while ($line = fgets($sock, 4096)) {
                $resp .= $line;
                $log[] = "S: " . trim($line);
                // Multi-line responses have '-' after code; last line has space
                if (isset($line[3]) && $line[3] === ' ') break;
                if (strlen($line) < 4) break;
            }
            return $resp;
        };

        // Helper: send command
        $send = function(string $cmd) use ($sock, &$log) {
            $log[] = "C: " . trim($cmd);
            fwrite($sock, $cmd . "\r\n");
        };

        // Helper: expect response code
        $expect = function(int $code, string $context = '') use ($read) {
            $resp = $read();
            $actual = (int)substr($resp, 0, 3);
            if ($actual !== $code) {
                throw new Exception("SMTP {$context}: expected {$code}, got {$actual} — " . trim($resp));
            }
            return $resp;
        };

        // Greeting
        $expect(220, 'greeting');

        // EHLO
        $send("EHLO " . gethostname());
        $ehloResp = $expect(250, 'EHLO');

        // STARTTLS (for port 587)
        if ($port !== 465 && stripos($ehloResp, 'STARTTLS') !== false) {
            $send("STARTTLS");
            $expect(220, 'STARTTLS');

            $crypto = stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            if (!$crypto) {
                throw new Exception("SMTP: TLS handshake failed");
            }

            // Re-EHLO after TLS
            $send("EHLO " . gethostname());
            $expect(250, 'EHLO-TLS');
        }

        // AUTH LOGIN
        $send("AUTH LOGIN");
        $expect(334, 'AUTH');

        $send(base64_encode($user));
        $expect(334, 'AUTH-user');

        $send(base64_encode($pass));
        $expect(235, 'AUTH-pass');

        // MAIL FROM
        $send("MAIL FROM:<{$from}>");
        $expect(250, 'MAIL FROM');

        // RCPT TO
        $send("RCPT TO:<{$to}>");
        $expect(250, 'RCPT TO');

        // DATA
        $send("DATA");
        $expect(354, 'DATA');

        // Build message
        $boundary = '----=_Part_' . bin2hex(random_bytes(12));
        $date = date('r');
        $msgId = '<' . bin2hex(random_bytes(16)) . '@' . ($from ? explode('@', $from)[1] : 'localhost') . '>';

        $fromHeader = $fromName ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>" : $from;
        $toHeader   = $toName   ? "=?UTF-8?B?" . base64_encode($toName)   . "?= <{$to}>"   : $to;

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$fromHeader}\r\n";
        $headers .= "To: {$toHeader}\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $body = $headers . "\r\n" . chunk_split(base64_encode($html), 76, "\r\n");

        // Dot-stuff: lines starting with '.' must be doubled
        $body = str_replace("\r\n.", "\r\n..", $body);

        fwrite($sock, $body);
        $send(".");
        $expect(250, 'message accepted');

        // QUIT
        $send("QUIT");
        @$read(); // don't care if QUIT response is clean

        fclose($sock);

        return ['success' => true, 'log' => $log];

    } catch (Exception $e) {
        if (isset($sock) && is_resource($sock)) {
            @fwrite($sock, "QUIT\r\n");
            @fclose($sock);
        }
        return ['success' => false, 'error' => $e->getMessage(), 'log' => $log];
    }
}
