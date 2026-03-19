<?php

/**
 * Geocode any address string → lat/lng + structured address parts
 * Uses OpenStreetMap Nominatim (free, no key required)
 */
function geocodeAddress(string $fullAddress): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $fullAddress,
        'format'         => 'json',
        'limit'          => 1,
        'addressdetails' => 1,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'MLSPropertySearch/2.0 (contact@yourdomain.com)',
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data[0]['lat'])) return null;

    $addr  = $data[0]['address'] ?? [];
    $state = stateNameToAbbr($addr['state'] ?? '') ?: ($addr['state'] ?? '');
    $city  = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? '';

    return [
        'lat'          => (float)$data[0]['lat'],
        'lng'          => (float)$data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? $fullAddress,
        'postcode'     => $addr['postcode']         ?? '',
        'city'         => $city,
        'state'        => $state,
        'county'       => $addr['county']           ?? '',
        'country_code' => $addr['country_code']     ?? 'us',
    ];
}

/**
 * Haversine distance in miles between two lat/lng points
 */
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 3959.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Build a county assessor link for the given address/state/county
 */
function getAssessorUrl(array $geo, string $parcelNumber = ''): array {
    $state  = strtoupper($geo['state'] ?? '');
    $county = strtolower($geo['county'] ?? '');
    $links  = [];

    // Known county assessor portals
    $known = [
        'CA' => [
            'los angeles' => 'https://portal.assessor.lacounty.gov/',
            'san diego'   => 'https://arcc.sdcounty.ca.gov/pages/RealPropertySearch.aspx',
            'orange'      => 'https://www.ocassessor.gov/index.asp',
            'riverside'   => 'https://www.rivcoacr.org/',
            'san bernardino' => 'https://arc.sbcounty.gov/',
            'sacramento'  => 'https://assessor.saccounty.gov/',
            'alameda'     => 'https://www.acgov.org/ptax/index.page',
            'santa clara' => 'https://www.sccassessor.org/',
        ],
        'TX' => [
            'harris'   => 'https://hcad.org/property-search/',
            'dallas'   => 'https://www.dallascad.org/SearchAddr.aspx',
            'tarrant'  => 'https://www.tad.org/property-search/',
            'travis'   => 'https://apps.traviscad.org/iasWorld/',
            'bexar'    => 'https://bexar.trueautomation.com/clientdb/?cid=110',
            'collin'   => 'https://esearch.collincad.org/',
        ],
        'FL' => [
            'miami-dade' => 'https://www.miami-dadeclerk.com/public-records/real-property/',
            'broward'    => 'https://www.bcpa.net/RecMenu.asp',
            'palm beach' => 'https://www.pbcgov.com/papa/',
            'hillsborough' => 'https://gis.hcpafl.org/propertysearch/',
            'orange'     => 'https://www.ocpafl.org/searches/parcel.aspx',
            'pinellas'   => 'https://www.pcpao.gov/search.php',
        ],
        'NY' => [
            'new york'  => 'https://a836-acris.nyc.gov/CP/',
            'kings'     => 'https://a836-acris.nyc.gov/CP/',
            'nassau'    => 'https://i2.nassaucountyny.gov/apps/ArchPropTax/',
            'suffolk'   => 'https://www.suffolkcountyny.gov/departments/realPropertyTaxServiceAgency',
            'westchester' => 'https://lrv.westchestergov.com/search',
        ],
        'IL' => [
            'cook' => 'https://www.cookcountyassessor.com/address-search',
            'dupage' => 'https://www.dupageco.org/propertytax/',
        ],
        'WA' => [
            'king' => 'https://blue.kingcounty.com/Assessor/eRealProperty/default.aspx',
            'pierce' => 'https://www.piercecountywa.gov/591/Assessor-Treasurer',
        ],
        'AZ' => [
            'maricopa' => 'https://mcassessor.maricopa.gov/mcs.php',
            'pima'     => 'https://assessor.pima.gov/assessor-parcel-search/',
        ],
        'GA' => [
            'fulton' => 'https://iasworld.fultoncountyga.gov/iasworld/iDoc/servlet/iDocServlet',
            'gwinnett' => 'https://www.gwinnettassessor.manatron.com/',
        ],
        'NC' => [
            'mecklenburg' => 'https://polaris3g.mecklenburgcountync.gov/',
            'wake'        => 'https://www.wake.gov/departments-government/tax-administration/data-files-statistics-and-reports/real-estate-property-search',
        ],
        'NV' => [
            'clark'   => 'https://assessor.clarkcountynv.gov/AssessorParcelDetail',
            'washoe'  => 'https://www.washoecounty.gov/assessor/',
        ],
        'CO' => [
            'denver'   => 'https://www.denvergov.org/property',
            'arapahoe' => 'https://www.arapahoegov.com/assessor',
            'jefferson' => 'https://www.jeffco.us/assessor',
            'adams'    => 'https://assessor.adcogov.org/',
            'boulder'  => 'https://www.bouldercounty.gov/property-and-land/assessor/',
        ],
        'OR' => [
            'multnomah' => 'https://multcoproptax.com/',
            'washington' => 'https://www.co.washington.or.us/AssessmentTaxation/',
        ],
        'MA' => [
            'middlesex' => 'https://www.masslandrecords.com/',
            'suffolk'   => 'https://www.masslandrecords.com/',
            'worcester' => 'https://www.masslandrecords.com/',
        ],
    ];

    // Check known portals first
    foreach ($known[$state] ?? [] as $countyKey => $url) {
        if (str_contains($county, $countyKey) || str_contains($countyKey, explode(' ', $county)[0])) {
            $links['assessor'] = [
                'label' => ucwords($countyKey) . ' County Assessor',
                'url'   => $url,
            ];
            break;
        }
    }

    // Always add a Google search fallback
    $q = urlencode(trim(($geo['display_name'] ?? '') . ' property records owner'));
    $links['google'] = [
        'label' => 'Search Public Records (Google)',
        'url'   => "https://www.google.com/search?q=$q",
    ];

    // Zillow and Redfin links (always useful)
    $addr = urlencode(trim(($geo['display_name'] ?? '')));
    $links['zillow'] = [
        'label' => 'View on Zillow',
        'url'   => "https://www.zillow.com/homes/{$addr}_rb/",
    ];
    $links['redfin'] = [
        'label' => 'View on Redfin',
        'url'   => "https://www.redfin.com/search#location={$addr}",
    ];

    return $links;
}

/**
 * US state full name → 2-letter abbreviation
 */
function stateNameToAbbr(string $name): string {
    $map = [
        'Alabama'=>'AL','Alaska'=>'AK','Arizona'=>'AZ','Arkansas'=>'AR',
        'California'=>'CA','Colorado'=>'CO','Connecticut'=>'CT','Delaware'=>'DE',
        'Florida'=>'FL','Georgia'=>'GA','Hawaii'=>'HI','Idaho'=>'ID',
        'Illinois'=>'IL','Indiana'=>'IN','Iowa'=>'IA','Kansas'=>'KS',
        'Kentucky'=>'KY','Louisiana'=>'LA','Maine'=>'ME','Maryland'=>'MD',
        'Massachusetts'=>'MA','Michigan'=>'MI','Minnesota'=>'MN','Mississippi'=>'MS',
        'Missouri'=>'MO','Montana'=>'MT','Nebraska'=>'NE','Nevada'=>'NV',
        'New Hampshire'=>'NH','New Jersey'=>'NJ','New Mexico'=>'NM','New York'=>'NY',
        'North Carolina'=>'NC','North Dakota'=>'ND','Ohio'=>'OH','Oklahoma'=>'OK',
        'Oregon'=>'OR','Pennsylvania'=>'PA','Rhode Island'=>'RI','South Carolina'=>'SC',
        'South Dakota'=>'SD','Tennessee'=>'TN','Texas'=>'TX','Utah'=>'UT',
        'Vermont'=>'VT','Virginia'=>'VA','Washington'=>'WA','West Virginia'=>'WV',
        'Wisconsin'=>'WI','Wyoming'=>'WY','District of Columbia'=>'DC',
    ];
    return $map[$name] ?? '';
}
