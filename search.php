<?php
// Catch ALL PHP errors and return them as JSON so the browser
// shows a meaningful message instead of a blank/HTML response
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function($severity, $message, $file, $line) {
    $f = basename($file);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "PHP Error in {$f} line {$line}: {$message}"]);
    exit;
});

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $f = basename($e['file']);
        // Headers may already be sent; try anyway
        @header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Fatal PHP error in {$f} line {$e['line']}: {$e['message']}"]);
    }
});

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/api.php';
require_once __DIR__ . '/lib/geocode.php';
require_once __DIR__ . '/lib/search_lib.php';
require_once __DIR__ . '/lib/photos.php';
require_once __DIR__ . '/lib/records.php';

if (!function_exists('jsonError')) {
    function jsonError(string $msg): void {
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
}

// Block direct browser navigation to this file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Extra guard: if somehow a native form POST reaches here without AJAX,
// redirect back instead of dumping JSON
$isAjax = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$hasFetch = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
// Allow both fetch() and form-based — the JSON header we set will handle display

// ── Address ────────────────────────────────────────────────────
$fullAddress = trim($_POST['full_address'] ?? '');

if (empty($fullAddress)) {
    jsonError('Please enter an address to search.');
}

// ── Statuses ────────────────────────────────────────────────────
$statusRaw     = $_POST['status'] ?? ['Active'];
$statuses      = is_array($statusRaw) ? $statusRaw : [$statusRaw];
$validStatuses = ['Active','Coming Soon','Active Under Contract','Pending','Closed','Canceled','Expired'];
$statuses      = array_values(array_filter($statuses, fn($s) => in_array($s, $validStatuses)));
if (empty($statuses)) $statuses = ['Active'];

// ── Other params ────────────────────────────────────────────────
$closedDays  = (int)($_POST['closed_days'] ?? 90);
$radiusRaw   = $_POST['radius'] ?? '0.125';
$radiusMiles = is_numeric($radiusRaw) ? (float)$radiusRaw : 0.125;
if ($radiusMiles < 0.01) $radiusMiles = 0.125;  // safety: never search with zero radius
$skip        = max(0, (int)($_POST['skip'] ?? 0));
$isLoadMore  = $skip > 0;

// For public records we also extract parts from the typed address
// so the records lookup can use them
$addrParts   = parseAddressString($fullAddress);

try {
    // 1. Geocode
    $geo = geocodeAddress($fullAddress);
    if (!$geo) jsonError("Could not locate \"{$fullAddress}\" — try adding city and state.");

    // 2. Search MLS — fetch a broad set, then filter by true radius
    $searchResult = searchNearAddress($geo, $radiusMiles, $statuses, $closedDays, $skip);
    $properties   = $searchResult['properties'];

    // 2b. Normalize RESO enum status names back to display names
    $statusDisplayMap = [
        'ComingSoon'          => 'Coming Soon',
        'ActiveUnderContract' => 'Active Under Contract',
    ];
    foreach ($properties as &$prop) {
        $st = $prop['StandardStatus'] ?? '';
        if (isset($statusDisplayMap[$st])) {
            $prop['StandardStatus'] = $statusDisplayMap[$st];
        }
    }
    unset($prop);

    // 3. Distances + true radius filter
    attachDistances($properties, $geo['lat'], $geo['lng']);
    $allCount = count($properties);
    $properties = array_values(array_filter($properties, function($p) use ($radiusMiles) {
        return isset($p['_distance']) && $p['_distance'] <= $radiusMiles;
    }));
    $totalCount = count($properties);

    // 4. Photos — proxied through photo.php so auth token is sent server-side
    $listingKeys = array_values(array_filter(array_column($properties, 'ListingKey')));
    $photos      = batchGetAllPhotos($listingKeys);
    $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
               . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/photo.php?url=';
    foreach ($properties as &$prop) {
        $rawList = $photos[$prop['ListingKey'] ?? ''] ?? [];
        // Filter out empty/null URLs
        $rawList = array_values(array_filter($rawList, fn($u) => $u && strlen($u) > 5));
        // _photo = first image, _photos = all images (for scrolling)
        if (!empty($rawList)) {
            $prop['_photo']  = $baseUrl . urlencode($rawList[0]);
            $prop['_photos'] = array_map(fn($u) => $baseUrl . urlencode($u), $rawList);
        } else {
            $prop['_photo']  = null;
            $prop['_photos'] = [];
        }
    }
    unset($prop);

    // For "load more" requests, just return the new batch
    if ($isLoadMore) {
        echo json_encode([
            'success'    => true,
            'properties' => $properties,
            'totalCount' => $totalCount,
            'skip'       => $skip,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 5. Public records
    // Use parsed address parts first, fall back to geocoder results
    $publicRecords = getPublicRecords(
        $addrParts['number'],
        $addrParts['street'],
        $addrParts['city']  ?: ($geo['city']     ?? ''),
        $addrParts['state'] ?: ($geo['state']    ?? ''),
        $addrParts['zip']   ?: ($geo['postcode'] ?? ''),
        $geo,
        $fullAddress   // pass the raw full address as ATTOM fallback
    );

    // 6. Scope label
    $radiusLabels = ['0.0625'=>'1/16 mile','0.125'=>'⅛ mile','0.25'=>'¼ mile','0.5'=>'½ mile','1.0'=>'1 mile','2.0'=>'2 miles','5.0'=>'5 miles','10.0'=>'10 miles'];
    $geoScope     = $geo['city'] ?? $geo['postcode'] ?? 'this area';

    // 7. Count — now accurate since properties are filtered by true radius
    $propCount  = count($properties);
    $totalCount = $propCount;
    $hitCap     = false;
    $countHtml  = '<strong>' . number_format($propCount) . '</strong> home' . ($propCount !== 1 ? 's' : '') . ' within ' . ($radiusLabels[(string)$radiusMiles] ?? "{$radiusMiles} mi");

    echo json_encode([
        'success'         => true,
        'geocoded'        => $geo,
        'geoScope'        => $geoScope,
        'statusLabel'     => implode(', ', $statuses),
        'statuses'        => $statuses,
        'hasClosed'       => in_array('Closed', $statuses),
        'radiusLabel'     => $radiusLabels[(string)$radiusMiles] ?? "{$radiusMiles} miles",
        'closedDays'      => $closedDays,
        'properties'      => $properties,
        'totalCount'      => $totalCount,
        'displayCap'      => 50,
        'hitCap'          => $hitCap,
        'countHtml'       => $countHtml,
        'publicRecords'   => $publicRecords,
        'searchedAddress' => $fullAddress,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    jsonError($e->getMessage());
}

// ── Helper: crude address string parser for records lookup ──────
function parseAddressString(string $addr): array {
    $parts = ['number'=>'','street'=>'','city'=>'','state'=>'','zip'=>''];
    // Match: 123 Main St, City, ST 12345
    if (preg_match('/^(\d+)\s+(.+?),\s*(.+?),?\s*([A-Z]{2})\s*(\d{5})?/i', $addr, $m)) {
        $parts['number'] = $m[1];
        $parts['street'] = trim($m[2]);
        $parts['city']   = trim($m[3]);
        $parts['state']  = strtoupper($m[4]);
        $parts['zip']    = $m[5] ?? '';
    }
    return $parts;
}
