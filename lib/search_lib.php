<?php

/**
 * Core RESO Data Dictionary fields that are present in virtually
 * every Trestle/Cotality feed. Stripped of any non-standard or
 * feed-specific fields that cause HTTP 400 errors.
 */
function getSelectFields(): string {
    return implode(',', [
        // Identity
        'ListingKey',
        'ListingId',
        'StandardStatus',

        // Price
        'ListPrice',
        'OriginalListPrice',
        'ClosePrice',
        'CloseDate',

        // Address
        'StreetNumber',
        'StreetName',
        'UnitNumber',
        'City',
        'StateOrProvince',
        'PostalCode',
        'CountyOrParish',

        // Coordinates
        'Latitude',
        'Longitude',

        // Structure
        'BedroomsTotal',
        'BathroomsTotalInteger',
        'BathroomsFull',
        'BathroomsHalf',
        'LivingArea',
        'LotSizeAcres',
        'YearBuilt',
        'GarageSpaces',

        // Type
        'PropertyType',
        'PropertySubType',

        // Description
        'PublicRemarks',

        // Agent / Office
        'ListAgentFullName',
        'ListOfficeName',

        // HOA
        'AssociationFee',
        'AssociationFeeFrequency',

        // Timing
        'DaysOnMarket',
        'CumulativeDaysOnMarket',
        'ModificationTimestamp',
        'ListingContractDate',
    ]);
}

/**
 * Build OData filter array from geocoded address + status params
 * $statuses is an array of one or more status strings
 */
function buildFilters(array $geo, float $radiusMiles, array $statuses, int $closedDays): array {
    $filters = [];

    // Build status filter — single eq or OR'd conditions
    if (!empty($statuses)) {
        if (count($statuses) === 1) {
            $filters[] = "StandardStatus eq '" . addslashes($statuses[0]) . "'";
        } else {
            $parts = array_map(fn($s) => "StandardStatus eq '" . addslashes($s) . "'", $statuses);
            $filters[] = '(' . implode(' or ', $parts) . ')';
        }
    }

    // Close date window only when Closed is among selected statuses
    if (in_array('Closed', $statuses) && $closedDays > 0) {
        $cutoff    = date('Y-m-d', strtotime("-{$closedDays} days"));
        $filters[] = "CloseDate ge $cutoff";
    }

    // Geographic scope: zip for tight radius, city for wider
    if ($radiusMiles <= 1.0 && !empty($geo['postcode'])) {
        $filters[] = "PostalCode eq '" . addslashes($geo['postcode']) . "'";
    } elseif (!empty($geo['city'])) {
        $filters[] = "City eq '" . addslashes($geo['city']) . "'";
        if (!empty($geo['state'])) {
            $filters[] = "StateOrProvince eq '" . addslashes($geo['state']) . "'";
        }
    } elseif (!empty($geo['postcode'])) {
        $filters[] = "PostalCode eq '" . addslashes($geo['postcode']) . "'";
    }

    return $filters;
}

/**
 * Run the main property search
 */
function searchNearAddress(array $geo, float $radiusMiles, array $statuses, int $closedDays): array {
    $filters = buildFilters($geo, $radiusMiles, $statuses, $closedDays);
    if (empty($filters)) throw new Exception("Could not build a geographic filter from that address.");

    $result = trestleGet('Property', [
        '$filter'  => implode(' and ', $filters),
        '$select'  => getSelectFields(),
        '$top'     => 50,
        '$orderby' => 'ModificationTimestamp desc',
    ]);

    return $result['value'] ?? [];
}

/**
 * Search all statuses for a specific address (listing history)
 */
function getAddressHistory(string $streetNumber, string $streetName, string $city, string $state): array {
    $filters = [];
    if ($streetNumber) $filters[] = "StreetNumber eq '" . addslashes($streetNumber) . "'";
    if ($streetName)   $filters[] = "contains(StreetName, '" . addslashes(strtok($streetName, ' ')) . "')";
    if ($city)         $filters[] = "City eq '" . addslashes($city) . "'";
    if ($state)        $filters[] = "StateOrProvince eq '" . addslashes($state) . "'";

    if (empty($filters)) return [];

    try {
        $result = trestleGet('Property', [
            '$filter'  => implode(' and ', $filters),
            '$select'  => getSelectFields(),
            '$top'     => 20,
            '$orderby' => 'ModificationTimestamp desc',
        ]);
        return $result['value'] ?? [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Batch fetch primary photos for an array of listing keys
 * Returns ['listingKey' => 'photoUrl', ...]
 */
function batchGetPrimaryPhotos(array $listingKeys): array {
    if (empty($listingKeys)) return [];

    $photos = [];
    $chunks = array_chunk($listingKeys, 15);

    foreach ($chunks as $chunk) {
        $orParts = array_map(fn($k) => "ResourceRecordKey eq '$k'", $chunk);
        $filter  = '(' . implode(' or ', $orParts) . ') and Order eq 1';

        try {
            $result = trestleGet('Media', [
                '$filter'  => $filter,
                '$select'  => 'ResourceRecordKey,MediaURL,Order',
                '$orderby' => 'ResourceRecordKey asc,Order asc',
                '$top'     => count($chunk) * 4,  // up to 4 photos per listing
            ]);
            foreach ($result['value'] ?? [] as $m) {
                $key = $m['ResourceRecordKey'];
                if (!isset($photos[$key])) $photos[$key] = [];
                if (count($photos[$key]) < 4) {
                    $photos[$key][] = $m['MediaURL'] ?? '';
                }
            }
        } catch (Exception $e) {
            // Silently continue — photos are nice-to-have
        }
    }

    return $photos;
}

/**
 * Get multiple photos for a single listing (for detail/records section)
 */
function getPhotosForListing(string $listingKey, int $limit = 8): array {
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

/**
 * Attach distance from center point to each property
 */
function attachDistances(array &$properties, float $centerLat, float $centerLng): void {
    foreach ($properties as &$prop) {
        $lat = (float)($prop['Latitude']  ?? 0);
        $lng = (float)($prop['Longitude'] ?? 0);
        $prop['_distance'] = ($lat && $lng)
            ? haversineDistance($centerLat, $centerLng, $lat, $lng)
            : null;
    }
}
