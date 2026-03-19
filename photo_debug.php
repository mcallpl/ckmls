<?php
// Photo proxy debug — DELETE after use
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

header('Content-Type: application/json');

$url = trim($_GET['url'] ?? '');

$result = ['url' => $url, 'steps' => []];

// Step 1: Token
try {
    $token = getAccessToken();
    $result['steps'][] = 'Token OK: ' . substr($token, 0, 20) . '...';
} catch (Exception $e) {
    $result['steps'][] = 'Token FAILED: ' . $e->getMessage();
    echo json_encode($result); exit;
}

// Step 2: Try WITH auth
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_HEADER         => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Accept: image/jpeg,image/*,*/*',
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hSz  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$err  = curl_error($ch);
curl_close($ch);

$result['steps'][] = "With auth: HTTP $code, type=$type, body=" . strlen(substr($resp,$hSz)) . " bytes, finalUrl=$finalUrl";
if ($err) $result['steps'][] = "CURL err: $err";

// Step 3: Try WITHOUT auth
$ch2 = curl_init($url);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Accept: image/jpeg,image/*,*/*'],
]);
$resp2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$type2 = curl_getinfo($ch2, CURLINFO_CONTENT_TYPE);
$err2  = curl_error($ch2);
curl_close($ch2);

$result['steps'][] = "Without auth: HTTP $code2, type=$type2, body=" . strlen($resp2) . " bytes";
if ($err2) $result['steps'][] = "CURL err2: $err2";

echo json_encode($result, JSON_PRETTY_PRINT);
