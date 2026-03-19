<?php
/**
 * Photo proxy — fetches Trestle/Cotality images with OAuth token.
 * Usage: photo.php?url=https://api.cotality.com/trestle/Media/...
 * 
 * Upload to: ckmls/photo.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

$url = trim($_GET['url'] ?? '');

// Unwrap any accidental double/triple proxy nesting
// e.g. photo.php?url=photo.php?url=photo.php?url=https://api...
for ($i = 0; $i < 5; $i++) {
    if (preg_match('/photo\.php\?url=(.+)$/i', $url, $m)) {
        $url = urldecode($m[1]);
    } else {
        break;
    }
}

// Decode if still percent-encoded
if (strpos($url, 'http') !== 0 && strpos($url, '%') !== false) {
    $url = urldecode($url);
}

// Validate — only Cotality/Trestle domains allowed
$host    = parse_url($url, PHP_URL_HOST) ?: '';
$allowed = [
    'api.cotality.com',
    'mls-photos.cotality.com',
    'api.bridgedataoutput.com',
    'trestle.mlsgrid.com',
    'mls-photos.mlsgrid.com',
];

$valid = false;
foreach ($allowed as $a) {
    if ($host === $a || substr($host, -strlen($a)-1) === '.'.$a) {
        $valid = true; break;
    }
}

if (!$valid || !$url) {
    // Return transparent 1x1 GIF — no broken image icon
    header('Content-Type: image/gif');
    header('Cache-Control: no-store');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Get OAuth token
try {
    $token = getAccessToken();
} catch (Exception $e) {
    http_response_code(502);
    exit;
}

// Fetch image with bearer token
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Accept: image/jpeg, image/png, image/webp, image/*',
    ],
    CURLOPT_HEADER         => true,
]);

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    // Return transparent 1x1 GIF instead of broken image
    header('Content-Type: image/gif');
    header('Cache-Control: no-store');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$body    = substr($response, $headerSize);
$headers = substr($response, 0, $headerSize);

// Get content type from response
$contentType = 'image/jpeg';
foreach (explode("\r\n", $headers) as $line) {
    if (stripos($line, 'Content-Type:') === 0) {
        $ct = trim(substr($line, 13));
        if (strpos($ct, 'image/') === 0) $contentType = $ct;
        break;
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400'); // 24h cache
header('Content-Length: ' . strlen($body));
echo $body;
