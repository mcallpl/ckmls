<?php
/**
 * SendGrid mailer — sends via the SendGrid Web API v3 (api.sendgrid.com).
 * Mirrors the proven SparkLead integration. No external SMTP relay involved.
 *
 * Usage:
 *   $result = sendgrid_send([
 *       'apiKey'   => SENDGRID_API_KEY,
 *       'from'     => 'Chip@ChipAndKim.com',   // must be a verified sender
 *       'fromName' => 'Chip McAllister',
 *       'to'       => 'client@example.com',
 *       'toName'   => 'Client Name',
 *       'subject'  => 'Your CMA',
 *       'html'     => '<h1>Hi</h1>',
 *       'replyTo'  => 'Chip@ChipAndKim.com',   // optional
 *       'bcc'      => 'mcallpl@gmail.com',      // optional silent agent copy
 *   ]);
 *   // Returns ['success'=>true] or ['success'=>false,'error'=>'...']
 */

function sendgrid_send(array $opts): array {
    $apiKey   = $opts['apiKey']   ?? '';
    $from     = $opts['from']     ?? '';
    $fromName = $opts['fromName'] ?? '';
    $to       = $opts['to']       ?? '';
    $toName   = $opts['toName']   ?? '';
    $subject  = $opts['subject']  ?? '(no subject)';
    $html     = $opts['html']     ?? '';
    $replyTo  = $opts['replyTo']  ?? $from;
    $bcc      = $opts['bcc']      ?? '';

    if (!$apiKey || !$from || !$to) {
        return ['success' => false, 'error' => 'SendGrid not configured — missing apiKey, from, or to.'];
    }

    // Build the single personalization (one recipient per call, matching the
    // per-recipient loop in cma.php).
    $personalization = ['to' => [array_filter(['email' => $to, 'name' => $toName ?: null])]];
    // Silent agent copy — but never BCC the same address we're sending TO
    // (SendGrid rejects a duplicate recipient across to/bcc).
    if ($bcc && strcasecmp(trim($bcc), trim($to)) !== 0) {
        $personalization['bcc'] = [['email' => $bcc]];
    }

    $payload = [
        'personalizations' => [$personalization],
        'from'     => array_filter(['email' => $from, 'name' => $fromName ?: null]),
        'reply_to' => ['email' => $replyTo],
        'subject'  => $subject,
        'content'  => [['type' => 'text/html', 'value' => $html]],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    // SendGrid returns 202 Accepted on success.
    if ($code === 202) {
        return ['success' => true];
    }
    if ($resp === false || $code === 0) {
        return ['success' => false, 'error' => "SendGrid connection failed: {$cerr}"];
    }
    // Surface SendGrid's own error message when present.
    $detail = $resp;
    $j = json_decode($resp, true);
    if (isset($j['errors'][0]['message'])) {
        $detail = $j['errors'][0]['message'];
    }
    return ['success' => false, 'error' => "SendGrid HTTP {$code}: {$detail}"];
}
