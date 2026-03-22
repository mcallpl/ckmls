<?php
/**
 * One-time test: resend the last CMA email to verify delivery.
 * Sends the EXACT same HTML that the modal generated.
 * DELETE THIS FILE after testing.
 */
header('Content-Type: application/json');

$htmlFile = __DIR__ . '/data/last_email.html';
if (!file_exists($htmlFile)) {
    echo json_encode(['error' => 'No saved email found']); exit;
}

$html = file_get_contents($htmlFile);
$to1 = 'klee@peoplestar.com';
$to2 = 'chip@chipandkim.com';
$subject = 'RESEND TEST — exact same 35KB HTML';
$from = 'Chip@chipandkim.com';

$headers  = "From: Chip McAllister <{$from}>\r\n";
$headers .= "Reply-To: {$from}\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";
$headers .= "MIME-Version: 1.0\r\n";

$body = chunk_split(base64_encode($html), 76, "\r\n");

$r1 = @mail($to1, $subject, $body, $headers);
$r2 = @mail($to2, $subject, $body, $headers);

echo json_encode([
    'size' => strlen($html) . ' bytes',
    'to_klee' => $r1 ? 'sent' : 'FAILED',
    'to_chip' => $r2 ? 'sent' : 'FAILED',
]);
