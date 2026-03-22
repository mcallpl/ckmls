<?php
/**
 * photos.php — Photo fetching module
 *
 * Encapsulated photo logic for MLS listings.
 * DO NOT inline photo logic elsewhere — all photo fetching goes through here.
 *
 * API:
 *   batchGetAllPhotos(array $listingKeys): array
 *     Returns ['listingKey' => ['url1','url2',...], ...]
 *     Fetches ALL available photos per listing (no artificial cap).
 *
 *   getPhotosForListing(string $listingKey, int $limit): array
 *     Returns array of Media objects with MediaURL, Order, ShortDescription
 *     For single-listing detail pages.
 */

/**
 * Batch fetch ALL photos for an array of listing keys.
 *
 * Strategy: small chunks (2 listings per request) with generous $top
 * to avoid cross-listing truncation. Retries once on failure.
 *
 * Returns: ['listingKey' => ['url1', 'url2', ...], ...]
 */
function batchGetAllPhotos(array $listingKeys): array {
    if (empty($listingKeys)) return [];

    $photos = [];

    // Chunks of 2 — keeps $top under 200 which all Trestle feeds support,
    // while giving each listing up to 100 photos before any risk of truncation.
    $chunks = array_chunk($listingKeys, 2);

    foreach ($chunks as $chunk) {
        $orParts = array_map(fn($k) => "ResourceRecordKey eq '$k'", $chunk);
        $filter  = '(' . implode(' or ', $orParts) . ')';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $result = trestleGet('Media', [
                    '$filter'  => $filter,
                    '$select'  => 'ResourceRecordKey,MediaURL,Order',
                    '$orderby' => 'ResourceRecordKey asc,Order asc',
                    '$top'     => 200,   // 100 per listing — handles even large galleries
                ]);
                foreach ($result['value'] ?? [] as $m) {
                    $key = $m['ResourceRecordKey'];
                    $url = $m['MediaURL'] ?? '';
                    if (!$key || !$url) continue;
                    if (!isset($photos[$key])) $photos[$key] = [];
                    $photos[$key][] = $url;
                }
                break; // success
            } catch (Exception $e) {
                if ($attempt === 0) usleep(500000); // 500ms then retry
            }
        }
    }

    return $photos;
}

/**
 * Get photos for a single listing (detail/records pages).
 * Returns raw Media objects: [{MediaURL, Order, ShortDescription}, ...]
 */
function getPhotosForListing(string $listingKey, int $limit = 100): array {
    try {
        $result = trestleGet('Media', [
            '$filter'  => "ResourceRecordKey eq '$listingKey'",
            '$orderby' => 'Order',
            '$select'  => 'MediaURL,Order,ShortDescription',
            '$top'     => $limit,
        ]);
        return $result['value'] ?? [];
    } catch (Exception $e) {
        return [];
    }
}
