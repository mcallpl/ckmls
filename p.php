<?php
/**
 * Public Property Detail Page
 * URL: p.php?k=<12-char-hex>
 * No auth required — standalone page for CMA email recipients
 */
require_once __DIR__ . '/config.php';

$key = preg_replace('/[^a-f0-9]/', '', $_GET['k'] ?? '');
$file = __DIR__ . "/data/$key.json";

if (!$key || strlen($key) !== 12 || !file_exists($file)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not Found</title>';
    echo '<style>body{font-family:sans-serif;background:#08090d;color:#e8eaf2;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}';
    echo '.wrap{max-width:400px;padding:40px}h1{font-size:4rem;margin:0}p{color:#6b7080;font-size:1rem;line-height:1.6}</style></head>';
    echo '<body><div class="wrap"><h1>404</h1><p>This property page is no longer available or the link is invalid.</p></div></body></html>';
    exit;
}

$data  = json_decode(file_get_contents($file), true);
$prop  = $data['property']  ?? [];
$agent = $data['agent']     ?? [];

// Property fields
$addr      = trim(($prop['StreetNumber'] ?? '') . ' ' . ($prop['StreetName'] ?? ''));
$cityLine  = implode(', ', array_filter([$prop['City'] ?? '', $prop['StateOrProvince'] ?? ''])) . ' ' . ($prop['PostalCode'] ?? '');

// Fetch ATTOM data live for THIS property's address
require_once __DIR__ . '/lib/records.php';
$attom = null;
$attomKey = defined('ATTOM_API_KEY') ? (string)ATTOM_API_KEY : '';
if ($attomKey && ($prop['StreetNumber'] ?? '') && ($prop['StreetName'] ?? '')) {
    $attom = fetchAttomData(
        $prop['StreetNumber'] ?? '',
        $prop['StreetName'] ?? '',
        $prop['City'] ?? '',
        $prop['StateOrProvince'] ?? '',
        $prop['PostalCode'] ?? '',
        $addr . ', ' . ($prop['City'] ?? '') . ', ' . ($prop['StateOrProvince'] ?? '') . ' ' . ($prop['PostalCode'] ?? ''),
        $attomKey
    );
}
$status    = $prop['StandardStatus'] ?? 'Unknown';
$isClosed  = $status === 'Closed';
$price     = $isClosed ? ($prop['ClosePrice'] ?? $prop['ListPrice'] ?? 0) : ($prop['ListPrice'] ?? 0);
$listPrice = $prop['ListPrice'] ?? 0;
$beds      = $prop['BedroomsTotal'] ?? '—';
$baths     = $prop['BathroomsTotalInteger'] ?? '—';
$sqft      = $prop['LivingArea'] ?? null;
$yearBuilt = $prop['YearBuilt'] ?? '—';
$dom       = $prop['DaysOnMarket'] ?? $prop['CumulativeDaysOnMarket'] ?? null;
$lotAcres  = $prop['LotSizeAcres'] ?? null;
$garage    = $prop['GarageSpaces'] ?? null;
$pType     = $prop['PropertySubType'] ?? $prop['PropertyType'] ?? '';
$hoa       = $prop['AssociationFee'] ?? null;
$hoaFreq   = $prop['AssociationFeeFrequency'] ?? '';
$remarks   = $prop['PublicRemarks'] ?? '';
$agent_name_listing = $prop['ListAgentFullName'] ?? '';
$office    = $prop['ListOfficeName'] ?? '';
$photos    = $prop['_photos'] ?? [];
$photo     = $prop['_photo'] ?? '';
if (!$photos && $photo) $photos = [$photo];

// Status badge colors
$statusColors = [
    'Active'                => ['bg'=>'#d1fae5','text'=>'#065f46'],
    'Coming Soon'           => ['bg'=>'#ede9fe','text'=>'#5b21b6'],
    'Active Under Contract' => ['bg'=>'#fef3c7','text'=>'#92400e'],
    'Pending'               => ['bg'=>'#ffedd5','text'=>'#9a3412'],
    'Closed'                => ['bg'=>'#fee2e2','text'=>'#991b1b'],
    'Canceled'              => ['bg'=>'#f3f4f6','text'=>'#374151'],
    'Expired'               => ['bg'=>'#f3f4f6','text'=>'#374151'],
];
$sc = $statusColors[$status] ?? ['bg'=>'#f3f4f6','text'=>'#374151'];

// Agent info
$aName    = $agent['name']     ?? (defined('AGENT_NAME')  ? AGENT_NAME  : '');
$aTitle   = $agent['title']    ?? (defined('AGENT_TITLE') ? AGENT_TITLE : '');
$aLicense = $agent['license']  ?? (defined('AGENT_LICENSE') ? AGENT_LICENSE : '');
$aEmail   = $agent['email']    ?? (defined('AGENT_EMAIL') ? AGENT_EMAIL : '');
$aPhone   = $agent['phone']    ?? (defined('AGENT_PHONE') ? AGENT_PHONE : '');
$aWebsite = $agent['website']  ?? (defined('AGENT_WEBSITE') ? AGENT_WEBSITE : '');
$aPhoto   = $agent['photo']    ?? (defined('AGENT_PHOTO_URL') ? AGENT_PHOTO_URL : '');
$aTeam    = $agent['team']     ?? (defined('AGENT_TEAM_NAME') ? AGENT_TEAM_NAME : '');
$aBroker  = $agent['brokerage']?? (defined('BROKERAGE_NAME') ? BROKERAGE_NAME : '');
$aLogo    = $agent['logo']     ?? (defined('BROKERAGE_LOGO_URL') ? BROKERAGE_LOGO_URL : '');

function fmtP($n) { return $n ? '$' . number_format((float)$n) : '—'; }
function fmtD($s) {
    if (!$s) return '—';
    try { return (new DateTime($s))->format('M j, Y'); } catch(Exception $e) { return $s; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($addr) ?> — Property Details</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #08090d; --surf: #111218; --surf2: #16181f;
            --bord: #1f2130; --bord2: #2a2d40; --accent: #5b7fff; --acc2: #7c9dff;
            --text: #e8eaf2; --muted: #6b7080; --mut2: #3a3d52;
            --radius: 16px; --radius-sm: 10px;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; line-height: 1.6; }
        .container { max-width: 720px; margin: 0 auto; padding: 0 16px 60px; }

        /* Photo gallery — matches app exactly */
        .pc-wrap { position: relative; width: 100%; aspect-ratio: 4/3; overflow: hidden; background: var(--surf2); border-radius: var(--radius); margin-bottom: 24px; display: block; }
        .pc-wrap.no-photo { display: flex; align-items: center; justify-content: center; }
        .np-icon { font-size: 4rem; color: var(--mut2); }
        .pc-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
        .pc-prev, .pc-next { position: absolute; top: 0; bottom: 0; width: 48px; background: rgba(0,0,0,.52); color: #fff; border: none; font-size: 2rem; font-weight: 400; line-height: 1; cursor: pointer; z-index: 15; display: flex; align-items: center; justify-content: center; transition: background .15s; padding: 0; user-select: none; }
        .pc-prev { left: 0; border-radius: var(--radius) 0 0 var(--radius); }
        .pc-next { right: 0; border-radius: 0 var(--radius) var(--radius) 0; }
        .pc-prev:hover, .pc-next:hover { background: rgba(0,0,0,.78); }
        .pc-count { position: absolute; bottom: 9px; right: 10px; background: rgba(0,0,0,.62); color: #fff; font-size: .68rem; font-family: 'Syne', sans-serif; font-weight: 600; padding: 2px 9px; border-radius: 100px; z-index: 15; pointer-events: none; letter-spacing: .04em; }

        /* Header */
        .prop-header { margin-bottom: 28px; }
        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 100px; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 12px; }
        .prop-addr { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 4px; }
        .prop-city { font-size: .95rem; color: var(--muted); margin-bottom: 12px; }
        .prop-price { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--accent); }

        /* Closed sale strip */
        .sold-strip { display: flex; gap: 0; background: var(--surf2); border-radius: var(--radius-sm); border: 1px solid rgba(255,92,92,.2); margin: 16px 0; overflow: hidden; }
        .sold-strip .sold-cell { flex: 1; padding: 12px; text-align: center; border-right: 1px solid rgba(255,92,92,.12); }
        .sold-strip .sold-cell:last-child { border-right: none; }
        .sold-cell-label { font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; color: #ff8a8a; margin-bottom: 2px; }
        .sold-cell-val { font-size: 1rem; font-weight: 700; color: #ff5c5c; }

        /* Stats bar */
        .stats-bar { display: flex; background: var(--surf); border: 1px solid var(--bord); border-radius: var(--radius-sm); overflow: hidden; margin-bottom: 28px; }
        .stat { flex: 1; padding: 16px 8px; text-align: center; border-right: 1px solid var(--bord); }
        .stat:last-child { border-right: none; }
        .stat-val { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; color: #fff; }
        .stat-label { font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-top: 2px; }

        /* Details section */
        .section { background: var(--surf); border: 1px solid var(--bord); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; }
        .section-title { font-family: 'Syne', sans-serif; font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--accent); margin-bottom: 16px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .detail-item-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
        .detail-item-val { font-size: .95rem; font-weight: 600; color: var(--text); }
        .remarks { font-size: .9rem; line-height: 1.7; color: var(--text); }

        /* ATTOM section */
        .attom-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--bord); }
        .attom-row:last-child { border-bottom: none; }
        .attom-label { font-size: .82rem; color: var(--muted); }
        .attom-val { font-size: .82rem; font-weight: 600; color: var(--text); }

        /* Agent card */
        .agent-card { display: flex; gap: 20px; align-items: center; }
        .agent-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--bord2); flex-shrink: 0; }
        .agent-name { font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 700; color: #fff; }
        .agent-title { font-size: .8rem; color: var(--muted); }
        .agent-contact { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
        .agent-contact a { font-size: .85rem; color: var(--acc2); text-decoration: none; }
        .agent-contact a:hover { text-decoration: underline; }
        .broker-logo { max-width: 160px; margin-top: 12px; display: block; }
        .cta-btn { display: inline-block; margin-top: 20px; padding: 14px 32px; background: var(--accent); color: #fff; font-size: .9rem; font-weight: 700; text-decoration: none; border-radius: var(--radius-sm); transition: background .2s; }
        .cta-btn:hover { background: #4a6de0; }

        /* Footer */
        .footer { margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--bord); text-align: center; font-size: .72rem; color: var(--mut2); line-height: 1.7; }

        @media (max-width: 600px) {
            .pc-wrap { aspect-ratio: 4/3; }
            .pc-prev, .pc-next { width: 36px; font-size: 1.6rem; }
            .prop-addr { font-size: 1.3rem; }
            .prop-price { font-size: 1.5rem; }
            .stats-bar { flex-wrap: wrap; }
            .stat { min-width: 33%; }
            .detail-grid { grid-template-columns: 1fr; }
            .agent-card { flex-direction: column; text-align: center; align-items: center; }
        }
    </style>
</head>
<body>

<div class="container" style="padding-top:32px;">

    <!-- Photo Gallery — same as app -->
    <div class="pc-wrap<?= count($photos) === 0 ? ' no-photo' : '' ?>">
        <?php if (count($photos) > 0): ?>
            <img id="pcImg" class="pc-img" src="<?= htmlspecialchars($photos[0]) ?>" alt="<?= htmlspecialchars($addr) ?>">
            <?php if (count($photos) > 1): ?>
                <button class="pc-prev" onclick="photoStep(-1)">&#8249;</button>
                <button class="pc-next" onclick="photoStep(1)">&#8250;</button>
                <div class="pc-count"><span id="pcCur">1</span> / <?= count($photos) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="np-icon">🏠</div>
        <?php endif; ?>
    </div>

    <!-- Header -->
    <div class="prop-header">
        <span class="status-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>"><?= htmlspecialchars($status) ?></span>
        <?php if ($pType): ?><span style="font-size:.75rem;color:var(--muted);margin-left:8px;"><?= htmlspecialchars($pType) ?></span><?php endif; ?>
        <div class="prop-addr"><?= htmlspecialchars($addr) ?></div>
        <div class="prop-city"><?= htmlspecialchars($cityLine) ?></div>
        <?php if (!$isClosed): ?>
            <div class="prop-price"><?= fmtP($price) ?></div>
        <?php endif; ?>
    </div>

    <!-- Closed Sale Strip -->
    <?php if ($isClosed): ?>
    <div class="sold-strip">
        <div class="sold-cell">
            <div class="sold-cell-label">Sold</div>
            <div class="sold-cell-val"><?= fmtD($prop['CloseDate'] ?? '') ?></div>
        </div>
        <div class="sold-cell">
            <div class="sold-cell-label">Sale Price</div>
            <div class="sold-cell-val"><?= fmtP($prop['ClosePrice'] ?? 0) ?></div>
        </div>
        <div class="sold-cell">
            <div class="sold-cell-label">List Price</div>
            <div class="sold-cell-val"><?= fmtP($listPrice) ?></div>
        </div>
        <?php
            $diff = ($listPrice && !empty($prop['ClosePrice']))
                ? round((($prop['ClosePrice'] - $listPrice) / $listPrice) * 100, 1)
                : null;
        ?>
        <?php if ($diff !== null): ?>
        <div class="sold-cell">
            <div class="sold-cell-label">vs List</div>
            <div class="sold-cell-val" style="color:<?= $diff >= 0 ? '#3ecf8e' : '#ff5c5c' ?>"><?= ($diff >= 0 ? '+' : '') . $diff ?>%</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat"><div class="stat-val"><?= $beds ?></div><div class="stat-label">Beds</div></div>
        <div class="stat"><div class="stat-val"><?= $baths ?></div><div class="stat-label">Baths</div></div>
        <div class="stat"><div class="stat-val"><?= $sqft ? number_format((float)$sqft) : '—' ?></div><div class="stat-label">Sq Ft</div></div>
        <div class="stat"><div class="stat-val"><?= $yearBuilt ?></div><div class="stat-label">Built</div></div>
        <?php if ($dom !== null): ?>
        <div class="stat"><div class="stat-val"><?= $dom ?></div><div class="stat-label">DOM</div></div>
        <?php endif; ?>
        <?php if ($lotAcres): ?>
        <div class="stat"><div class="stat-val"><?= round((float)$lotAcres, 2) ?></div><div class="stat-label">Acres</div></div>
        <?php endif; ?>
    </div>

    <!-- Property Details -->
    <div class="section">
        <div class="section-title">Property Details</div>
        <div class="detail-grid">
            <?php if ($pType): ?>
            <div><div class="detail-item-label">Type</div><div class="detail-item-val"><?= htmlspecialchars($pType) ?></div></div>
            <?php endif; ?>
            <?php if ($garage): ?>
            <div><div class="detail-item-label">Garage</div><div class="detail-item-val"><?= $garage ?> spaces</div></div>
            <?php endif; ?>
            <?php if ($hoa): ?>
            <div><div class="detail-item-label">HOA</div><div class="detail-item-val"><?= fmtP($hoa) ?><?= $hoaFreq ? " / $hoaFreq" : '' ?></div></div>
            <?php endif; ?>
            <?php if ($sqft && $price): ?>
            <div><div class="detail-item-label">Price / Sq Ft</div><div class="detail-item-val">$<?= number_format($price / (float)$sqft) ?></div></div>
            <?php endif; ?>
            <?php if ($agent_name_listing): ?>
            <div><div class="detail-item-label">Listing Agent</div><div class="detail-item-val"><?= htmlspecialchars($agent_name_listing) ?></div></div>
            <?php endif; ?>
            <?php if ($office): ?>
            <div><div class="detail-item-label">Listing Office</div><div class="detail-item-val"><?= htmlspecialchars($office) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($prop['ListingContractDate'])): ?>
            <div><div class="detail-item-label">Listed</div><div class="detail-item-val"><?= fmtD($prop['ListingContractDate']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($prop['OriginalListPrice']) && $prop['OriginalListPrice'] != $listPrice): ?>
            <div><div class="detail-item-label">Original Price</div><div class="detail-item-val"><?= fmtP($prop['OriginalListPrice']) ?></div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Public Remarks -->
    <?php if ($remarks): ?>
    <div class="section">
        <div class="section-title">Description</div>
        <div class="remarks"><?= nl2br(htmlspecialchars($remarks)) ?></div>
    </div>
    <?php endif; ?>

    <!-- ATTOM Public Records -->
    <?php if ($attom && empty($attom['_error'])): ?>
    <div class="section">
        <div class="section-title">Public Records</div>
        <?php
            $owners = array_filter([$attom['owner1'] ?? '', $attom['owner2'] ?? '', $attom['owner3'] ?? '']);
            if ($owners): ?>
            <div class="attom-row"><span class="attom-label">Owner</span><span class="attom-val"><?= htmlspecialchars(implode(' & ', $owners)) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($attom['owner_occupied'])): ?>
            <div class="attom-row"><span class="attom-label">Occupancy</span><span class="attom-val">Owner Occupied</span></div>
        <?php elseif (!empty($attom['absentee_status'])): ?>
            <div class="attom-row"><span class="attom-label">Occupancy</span><span class="attom-val">Absentee Owner</span></div>
        <?php endif; ?>
        <?php if (!empty($attom['last_sale_date'])): ?>
            <div class="attom-row"><span class="attom-label">Last Purchase</span><span class="attom-val"><?= fmtD($attom['last_sale_date']) ?><?= !empty($attom['last_sale_price']) ? ' for ' . fmtP($attom['last_sale_price']) : '' ?></span></div>
        <?php endif; ?>
        <?php if (!empty($attom['assessed_total'])): ?>
            <div class="attom-row"><span class="attom-label">Assessed Value</span><span class="attom-val"><?= fmtP($attom['assessed_total']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($attom['tax_amount'])): ?>
            <div class="attom-row"><span class="attom-label">Annual Tax</span><span class="attom-val"><?= fmtP($attom['tax_amount']) ?><?= !empty($attom['tax_year']) ? ' (' . $attom['tax_year'] . ')' : '' ?></span></div>
        <?php endif; ?>
        <?php if (!empty($attom['land_use'])): ?>
            <div class="attom-row"><span class="attom-label">Land Use</span><span class="attom-val"><?= htmlspecialchars($attom['land_use']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($attom['loans'])): foreach ($attom['loans'] as $loan): ?>
            <div class="attom-row">
                <span class="attom-label"><?= htmlspecialchars($loan['position'] ?? 'Loan') ?></span>
                <span class="attom-val"><?= !empty($loan['amount']) ? fmtP($loan['amount']) : '—' ?><?= !empty($loan['type']) ? ' (' . htmlspecialchars($loan['type']) . ')' : '' ?></span>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <!-- Agent Contact Card -->
    <div class="section">
        <div class="section-title">Questions? Let's Talk</div>
        <div class="agent-card">
            <?php if ($aPhoto): ?>
                <img src="<?= htmlspecialchars($aPhoto) ?>" alt="<?= htmlspecialchars($aName) ?>" class="agent-photo">
            <?php endif; ?>
            <div>
                <div class="agent-name"><?= htmlspecialchars($aName) ?></div>
                <?php if ($aTitle || $aLicense): ?>
                    <div class="agent-title"><?= htmlspecialchars($aTitle) ?><?= $aTitle && $aLicense ? ' | ' : '' ?><?= $aLicense ? 'Lic# ' . htmlspecialchars($aLicense) : '' ?></div>
                <?php endif; ?>
                <div class="agent-contact">
                    <?php if ($aPhone): ?><a href="tel:<?= htmlspecialchars($aPhone) ?>"><?= htmlspecialchars($aPhone) ?></a><?php endif; ?>
                    <?php if ($aEmail): ?><a href="mailto:<?= htmlspecialchars($aEmail) ?>"><?= htmlspecialchars($aEmail) ?></a><?php endif; ?>
                    <?php if ($aWebsite): ?><a href="<?= htmlspecialchars($aWebsite) ?>" target="_blank"><?= preg_replace('#^https?://#', '', htmlspecialchars($aWebsite)) ?></a><?php endif; ?>
                </div>
                <?php if ($aLogo): ?>
                    <img src="<?= htmlspecialchars($aLogo) ?>" alt="<?= htmlspecialchars($aBroker) ?>" class="broker-logo">
                <?php elseif ($aBroker): ?>
                    <div style="margin-top:8px;font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($aBroker) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($aEmail): ?>
            <a href="mailto:<?= htmlspecialchars($aEmail) ?>?subject=<?= urlencode('Question about ' . $addr) ?>" class="cta-btn">Contact <?= htmlspecialchars($aTeam ?: $aName) ?></a>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        This property information is based on MLS data and public records. All information is deemed reliable but not guaranteed. This is not an appraisal. Contact your agent for a full evaluation.
    </div>

</div>

<?php if (count($photos) > 1): ?>
<script>
var photos = <?= json_encode($photos) ?>;
var idx = 0;
function photoStep(dir) {
    idx = (idx + dir + photos.length) % photos.length;
    document.getElementById('pcImg').src = photos[idx];
    document.getElementById('pcCur').textContent = idx + 1;
}
</script>
<?php endif; ?>

</body>
</html>
