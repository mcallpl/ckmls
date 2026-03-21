<?php

/**
 * Public records: ATTOM Data API + MLS history + county assessor links
 */
function getPublicRecords(
    string $streetNumber,
    string $streetName,
    string $city,
    string $state,
    string $postalCode,
    array  $geo,
    string $fullAddress = ''
): array {
    $records = [
        'mls_history'    => [],
        'attom'          => null,
        'assessor_links' => [],
        'tax_info'       => [],
    ];

    // 1. MLS history for this address
    $records['mls_history'] = getAddressHistory($streetNumber, $streetName, $city, $state);

    // 2. Tax info from MLS history
    foreach ($records['mls_history'] as $listing) {
        if (!empty($listing['TaxAnnualAmount'])) {
            $records['tax_info'] = [
                'annual_tax' => $listing['TaxAnnualAmount'] ?? null,
                'tax_year'   => $listing['TaxYear']         ?? '',
                'county'     => $listing['CountyOrParish']  ?? ($geo['county'] ?? ''),
            ];
            break;
        }
    }

    // 3. ATTOM — read the key and call the API
    // PHP define() constants are globally accessible — no 'global' keyword needed
    $attomKey = defined('ATTOM_API_KEY') ? (string) ATTOM_API_KEY : '';

    if ($attomKey === '') {
        $records['attom'] = ['_error' => 'NO_KEY'];
    } else {
        $records['attom'] = fetchAttomData(
            $streetNumber, $streetName, $city, $state, $postalCode,
            $fullAddress, $attomKey
        );
    }

    // 4. County assessor links
    $records['assessor_links'] = getAssessorUrl($geo);

    return $records;
}

/**
 * Hit ATTOM's property/detail + mortgage endpoints
 */
function fetchAttomData(
    string $streetNumber,
    string $streetName,
    string $city,
    string $state,
    string $postalCode,
    string $fullAddress,
    string $apiKey
): array {

    // Build address strings
    if ($streetNumber && $streetName) {
        $address1 = trim("$streetNumber $streetName");
        $address2 = trim("$city $state $postalCode");
    } elseif ($fullAddress) {
        $parts    = explode(',', $fullAddress, 2);
        $address1 = trim($parts[0]);
        $address2 = trim($parts[1] ?? '');
    } else {
        return ['_error' => 'No address provided to ATTOM'];
    }

    // ── Property Detail ──────────────────────────────────────
    $propData = attomGet('/propertyapi/v1.0.0/property/basicprofile', [
        'address1' => $address1,
        'address2' => $address2,
    ], $apiKey);

    if (isset($propData['_error'])) return $propData;

    $prop = $propData['property'][0] ?? null;
    if (!$prop) {
        $msg = $propData['status']['msg'] ?? 'Property not found';
        return ['_error' => "ATTOM: $msg"];
    }

    // ── Mortgage / Loan data ─────────────────────────────────
    $attomId  = $prop['identifier']['attomId'] ?? null;
    $mortgages = [];
    if ($attomId) {
        $mortData = attomGet('/propertyapi/v1.0.0/attomavm/detail', [
            'attomid' => $attomId,
        ], $apiKey);
        if (!isset($mortData['_error'])) {
            $mortgages = $mortData['property'][0]['mortgage'] ?? [];
        }
    }

    // ── Normalize — field paths confirmed from live ATTOM basicprofile response ──
    // Owner lives inside assessment block, NOT at prop top level
    $assessment = $prop['assessment'] ?? [];
    $owner      = $assessment['owner'] ?? [];   // <-- inside assessment
    $building   = $prop['building']   ?? [];
    $lot        = $prop['lot']        ?? [];
    $sale       = $prop['sale']       ?? [];

    // Owner names — camelCase fullName
    $owner1Name = $owner['owner1']['fullName'] ?? '';
    $owner2Name = $owner['owner2']['fullName'] ?? '';
    $owner3Name = $owner['owner3']['fullName'] ?? ''; // trust / additional owner
    $allOwners  = array_filter([$owner1Name, $owner2Name, $owner3Name]);

    // Mailing address — camelCase mailingAddressOneLine
    $mailAddr = $owner['mailingAddressOneLine'] ?? '';

    // Parse loans
    $loans = [];
    foreach ((array)$mortgages as $m) {
        if (empty($m['amount'])) continue;
        $loans[] = [
            'amount'     => $m['amount']        ?? null,
            'type'       => $m['loantype']      ?? '',
            'lender'     => $m['lenderName']    ?? ($m['lender'] ?? ''),
            'date'       => $m['recordingdate'] ?? '',
            'rate_type'  => $m['interestratetype'] ?? '',
            'due_date'   => $m['maturitydate']  ?? '',
            'position'   => $m['position']      ?? '',
        ];
    }

    return [
        // Owner
        'owner1'           => $owner1Name,
        'owner2'           => $owner2Name,
        'owner3'           => $owner3Name,
        'all_owners'       => implode(', ', $allOwners),
        'mailing_address'  => $mailAddr,
        'absentee_status'  => $owner['absenteeOwnerStatus'] ?? '',
        'owner_occupied'   => ($prop['summary']['absenteeInd'] ?? '') === 'OWNER OCCUPIED',

        // Purchase / Sale history — data lives in saleAmountData sub-object
        'last_sale_date'   => $sale['saleSearchDate']
                           ?? ($sale['saleAmountData']['saleRecDate']  ?? ''),
        'last_sale_price'  => $sale['saleAmountData']['saleAmt']       ?? null,
        'last_doc_type'    => $sale['saleAmountData']['saleDocType']   ?? '',
        'last_trans_type'  => $sale['saleAmountData']['saleTransType'] ?? '',
        'prior_sale_date'  => $sale['priorSaleDate']                   ?? '',
        'prior_sale_price' => $sale['priorSaleAmt']                    ?? null,

        // Assessment & Tax — camelCase field names confirmed from live response
        'apn'              => $prop['identifier']['apn']          ?? '',
        'assessed_total'   => $assessment['assessed']['assdTtlValue']  ?? null,
        'assessed_land'    => $assessment['assessed']['assdLandValue']  ?? null,
        'assessed_impr'    => $assessment['assessed']['assdImprValue']  ?? null,
        'market_total'     => null, // not returned by basicprofile for this property
        'tax_amount'       => $assessment['tax']['taxAmt']              ?? null,
        'tax_year'         => $assessment['tax']['taxYear']             ?? '',

        // Property details
        'land_use'         => $lot['lotuse1']                         ?? ($prop['summary']['proptype'] ?? ''),
        'zoning'           => $lot['zoning']                          ?? '',
        'lot_size_sqft'    => $lot['lotsize1']                        ?? null,
        'year_built'       => $prop['summary']['yearBuilt']  ?? null,
        'gross_sqft'       => $building['size']['bldgSize']
                           ?? ($building['size']['livingsize']       ?? null),
        'bedrooms'         => $building['rooms']['beds']              ?? null,
        'bathrooms'        => $building['rooms']['bathstotal']
                           ?? ($building['rooms']['bathsfull']
                           ?? ($building['rooms']['bathsTotal']
                           ?? ($building['rooms']['bathsFull']
                           ?? ($building['rooms']['bathTotal']
                           ?? ($building['rooms']['baths']             ?? null))))),
        '_raw_rooms'       => $building['rooms'] ?? null,  // debug: see what ATTOM returns
        'stories'          => $building['summary']['levels']          ?? null,
        'heating'          => $building['interior']['heatingtype']    ?? '',
        'cooling'          => $building['interior']['coolingtype']    ?? '',
        'garage'           => $building['parking']['garagetype']      ?? '',
        'legal_desc'       => $prop['legal']['legaldesc1']            ?? '',

        // Mortgages / Loans
        'loans'            => $loans,
    ];
}

/**
 * Generic ATTOM GET helper
 */
function attomGet(string $path, array $params, string $apiKey): array {
    $url = 'https://api.gateway.attomdata.com' . $path . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr)          return ['_error' => "Connection failed: $curlErr"];
    if ($httpCode === 401) return ['_error' => 'ATTOM API key rejected (401 Unauthorized) — verify key in config.php'];
    if ($httpCode === 403) return ['_error' => 'ATTOM API key does not have access to this endpoint (403)'];
    if ($httpCode === 404) return ['_error' => 'Property not found in ATTOM database'];
    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['status']['msg'] ?? substr($response, 0, 200);
        return ['_error' => "ATTOM HTTP $httpCode: $msg"];
    }

    $data = json_decode($response, true);
    if (!$data) return ['_error' => 'ATTOM returned invalid JSON'];
    return $data;
}
