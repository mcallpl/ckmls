<?php
/**
 * CMA Email Generator
 * Creates AI-enhanced CMA emails with subject property details + comps
 */
@ini_set('max_execution_time', 90);
@ini_set('memory_limit', '256M');
@ini_set('display_errors', 0);

header('Content-Type: application/json');

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success'=>false,'error'=>'Fatal: '.$e['message'].' line '.$e['line']]);
    }
});

try {

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit;
}

// ── Read input ────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode(['success'=>false,'error'=>'Empty request body.']); exit;
}

$input = json_decode($rawInput, true);
if (!is_array($input)) {
    echo json_encode(['success'=>false,'error'=>'Invalid JSON: '.json_last_error_msg()]); exit;
}

$recipients      = $input['recipients']       ?? [];
$properties      = $input['properties']       ?? [];
$agentNotes      = trim($input['agent_notes']       ?? '');
$editedNarrative = trim($input['edited_narrative']  ?? '');
$subjectAddr     = trim($input['subject_address']   ?? '');
$emailSubject    = trim($input['email_subject']     ?? '');
$siteUrl         = trim($input['site_url']          ?? '');
$previewOnly     = !empty($input['preview_only']);
$subjectAttom    = $input['subject_attom']    ?? null;
$recipientFirst  = trim($input['recipient_first']  ?? '');
$recipientLast   = trim($input['recipient_last']   ?? '');

// Agent info: modal input overrides config
$agentName  = trim($input['agent_name']  ?? '') ?: (defined('AGENT_NAME')  ? AGENT_NAME  : '');
$agentEmail = trim($input['agent_email'] ?? '') ?: (defined('AGENT_EMAIL') ? AGENT_EMAIL : '');
$agentPhone = trim($input['agent_phone'] ?? '') ?: (defined('AGENT_PHONE') ? AGENT_PHONE : '');

if (!$previewOnly && empty($recipients)) {
    echo json_encode(['success'=>false,'error'=>'Please enter the recipient email address.']); exit;
}
if (empty($properties)) {
    echo json_encode(['success'=>false,'error'=>'No properties received. Please select comps and try again.']); exit;
}

// Sort properties by price descending
usort($properties, function($a, $b) {
    $pa = (float)($a['ClosePrice'] ?? $a['ListPrice'] ?? 0);
    $pb = (float)($b['ClosePrice'] ?? $b['ListPrice'] ?? 0);
    return $pb <=> $pa;
});

// ── Helpers ───────────────────────────────────────────────────────
function fmtP($n)  { return $n ? '$'.number_format((float)$n) : '—'; }
function fmtD2($s) {
    if (!$s) return '—';
    try { return (new DateTime($s))->format('M j, Y'); } catch(Exception $e) { return $s; }
}

// ── Build subject property context ───────────────────────────────
$subjectContext = '';
if ($subjectAttom && empty($subjectAttom['_error'])) {
    $lines = [];
    // Owner info
    $owners = array_filter([$subjectAttom['owner1'] ?? '', $subjectAttom['owner2'] ?? '', $subjectAttom['owner3'] ?? '']);
    if ($owners) $lines[] = 'Owner: ' . implode(', ', $owners);
    if (!empty($subjectAttom['owner_occupied'])) $lines[] = 'Owner Occupied: Yes';
    elseif (!empty($subjectAttom['absentee_status'])) $lines[] = 'Absentee Owner';

    // Purchase
    if (!empty($subjectAttom['last_sale_date']))  $lines[] = 'Last Purchase: ' . $subjectAttom['last_sale_date'];
    if (!empty($subjectAttom['last_sale_price'])) $lines[] = 'Purchase Price: $' . number_format((float)$subjectAttom['last_sale_price']);
    if (!empty($subjectAttom['prior_sale_date']))  $lines[] = 'Prior Sale: ' . $subjectAttom['prior_sale_date'] . ($subjectAttom['prior_sale_price'] ? ' for $'.number_format((float)$subjectAttom['prior_sale_price']) : '');

    // Property details
    $details = [];
    if (!empty($subjectAttom['bedrooms']))     $details[] = $subjectAttom['bedrooms'] . ' bedrooms';
    if (!empty($subjectAttom['bathrooms']))    $details[] = $subjectAttom['bathrooms'] . ' bathrooms';
    if (!empty($subjectAttom['gross_sqft']))   $details[] = number_format((float)$subjectAttom['gross_sqft']) . ' sqft';
    if (!empty($subjectAttom['year_built']))   $details[] = 'built ' . $subjectAttom['year_built'];
    if (!empty($subjectAttom['lot_size_sqft']))$details[] = number_format((float)$subjectAttom['lot_size_sqft']) . ' sqft lot';
    if (!empty($subjectAttom['stories']))      $details[] = $subjectAttom['stories'] . ' stories';
    if ($details) $lines[] = 'Property: ' . implode(', ', $details);

    // Tax/assessment
    if (!empty($subjectAttom['assessed_total'])) $lines[] = 'Assessed Value: $' . number_format((float)$subjectAttom['assessed_total']);
    if (!empty($subjectAttom['tax_amount']))      $lines[] = 'Annual Tax: $' . number_format((float)$subjectAttom['tax_amount']) . ($subjectAttom['tax_year'] ? ' (' . $subjectAttom['tax_year'] . ')' : '');

    // Loans
    if (!empty($subjectAttom['loans'])) {
        foreach ($subjectAttom['loans'] as $loan) {
            $loanStr = ($loan['position'] ?? 'Loan');
            if (!empty($loan['amount'])) $loanStr .= ': $' . number_format((float)$loan['amount']);
            if (!empty($loan['type']))   $loanStr .= ' (' . $loan['type'] . ')';
            if (!empty($loan['lender'])) $loanStr .= ' - ' . $loan['lender'];
            $lines[] = $loanStr;
        }
    }

    if (!empty($subjectAttom['land_use'])) $lines[] = 'Land Use: ' . $subjectAttom['land_use'];

    $subjectContext = "SUBJECT PROPERTY DETAILS ({$subjectAddr}):\n" . implode("\n", array_map(fn($l) => "- $l", $lines)) . "\n\n";
}

// ── 1. AI Narrative ───────────────────────────────────────────────
$openaiKey   = defined('OPENAI_API_KEY') ? (string)OPENAI_API_KEY : '';
$aiNarrative = '';

if ($editedNarrative) {
    $aiNarrative = $editedNarrative;

} elseif ($openaiKey) {
    $summary      = [];
    $activePrices = [];
    $closedPrices = [];
    $domVals      = [];
    $sqftVals     = [];
    $bedVals      = [];
    $yearVals     = [];
    $aC = $cC = $pC = 0;

    foreach ($properties as $p) {
        $st    = $p['StandardStatus'] ?? '';
        $price = (float)($p['ClosePrice'] ?? $p['ListPrice'] ?? 0);
        $addr  = trim(($p['StreetNumber']??'').' '.($p['StreetName']??'').', '.($p['City']??''));
        $beds  = $p['BedroomsTotal'] ?? '?';
        $baths = $p['BathroomsTotalInteger'] ?? '?';
        $sqft  = (float)($p['LivingArea'] ?? 0);
        $dom   = $p['DaysOnMarket'] ?? null;
        $yr    = $p['YearBuilt'] ?? null;

        $summary[] = "$addr | $st | \$".number_format($price)." | {$beds}bd {$baths}ba"
            .($sqft ? ' | '.number_format($sqft).'sf' : '')
            .($dom  ? " | {$dom} DOM" : '')
            .($yr   ? " | built {$yr}" : '');

        if ($st === 'Active')  { $activePrices[] = $price; $aC++; }
        if ($st === 'Closed')  { $closedPrices[] = $price; $cC++; }
        if (in_array($st, ['Pending','Active Under Contract'])) $pC++;
        if ($dom)  $domVals[]  = (float)$dom;
        if ($sqft) $sqftVals[] = $sqft;
        if ($beds && is_numeric($beds)) $bedVals[]  = (int)$beds;
        if ($yr)   $yearVals[] = (int)$yr;
    }

    $priceRange = $activePrices ? '$'.number_format(min($activePrices)).'–$'.number_format(max($activePrices)) : 'N/A';
    $avgClosed  = $closedPrices ? '$'.number_format(array_sum($closedPrices)/count($closedPrices)) : 'N/A';
    $avgDom     = $domVals  ? round(array_sum($domVals)/count($domVals)).' days' : 'N/A';
    $avgSqft    = $sqftVals ? number_format(array_sum($sqftVals)/count($sqftVals)).'sf' : 'N/A';
    $bedRange   = $bedVals  ? min($bedVals).'–'.max($bedVals).' bedrooms' : 'N/A';
    $yearRange  = $yearVals ? min($yearVals).'–'.max($yearVals) : 'N/A';
    $city       = $properties[0]['City']       ?? 'the area';
    $zip        = $properties[0]['PostalCode'] ?? '';
    $propCount  = count($properties);
    $teamName   = defined('AGENT_TEAM_NAME') && AGENT_TEAM_NAME ? AGENT_TEAM_NAME : $agentName;

    $recipientGreeting = $recipientFirst ? "The client's name is {$recipientFirst}. " : '';

    $notesBlock = $agentNotes
        ? "AGENT'S PERSONAL INSIGHTS (IMPORTANT — these are the agent's own words about this market/client. "
          ."You MUST incorporate these points into the narrative. Weave them in naturally and personally, "
          ."as if the agent is speaking directly to the client. Do not quote verbatim, but every key point "
          ."the agent mentions should come through clearly):\n\"{$agentNotes}\"\n\n"
        : "";

    $prompt = "You are a top real estate professional writing a CMA email narrative.\n\n"
        ."{$recipientGreeting}"
        ."AREA: {$subjectAddr} — {$city}".($zip ? " {$zip}" : '')."\n\n"
        .$subjectContext
        ."MARKET SNAPSHOT ({$propCount} comps):\n"
        ."- Active: {$aC} | Pending: {$pC} | Closed: {$cC}\n"
        ."- Active price range: {$priceRange}\n"
        ."- Avg closed price: {$avgClosed}\n"
        ."- Avg days on market: {$avgDom}\n"
        ."- Homes: {$bedRange}, avg {$avgSqft}, built {$yearRange}\n\n"
        ."COMPS:\n".implode("\n", $summary)."\n\n"
        .$notesBlock
        ."Write 4 paragraphs:\n"
        ."1. Open with the subject property — describe what makes this home and its location special, "
        ."referencing the actual property details (beds, baths, sqft, year built, lot size). "
        ."Set the scene for the neighborhood.\n"
        ."2. Analyze how the subject property compares to the comps — discuss pricing trends, "
        ."size comparisons, age of homes, and where this property sits in the market. Use actual numbers.\n"
        ."3. Market dynamics — absorption rate, days on market trends, buyer demand indicators, "
        ."and what the comp data reveals about pricing power. Blend in the agent's personal insights here.\n"
        ."4. Actionable insight + warm invite to contact {$teamName} for a full analysis and personalized consultation.\n\n"
        ."Tone: expert, warm, conversational, personal. The agent's notes should feel like a natural part of "
        ."the message — as if the agent personally wrote this with the client in mind. "
        ."No greeting, no sign-off, no bullets. Reference the subject property and neighborhood by name.";

    try {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => 'gpt-4o-mini',
                'messages'    => [['role'=>'user','content'=>$prompt]],
                'max_tokens'  => 700,
                'temperature' => 0.70,
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$openaiKey,
                'Content-Type: application/json',
            ],
        ]);
        $aiResp  = curl_exec($ch);
        $aiCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($aiCode === 200) {
            $d = json_decode($aiResp, true);
            $aiNarrative = trim($d['choices'][0]['message']['content'] ?? '');
        }
    } catch (Exception $ex) { /* fall through */ }
}

if (!$aiNarrative) {
    $teamName = defined('AGENT_TEAM_NAME') && AGENT_TEAM_NAME ? AGENT_TEAM_NAME : $agentName;
    $aiNarrative = "A Comparative Market Analysis cuts through the noise by showing you exactly what homes are "
        ."actually selling for right now — not what an algorithm guesses.\n\n"
        ."The comparable properties in this report were carefully selected to reveal real pricing trends, "
        ."buyer demand, and days-on-market patterns in this specific area. Together they give you a "
        ."data-driven picture of what the market is doing right now.\n\n"
        ."This is your starting point. For a full personalized analysis — including pricing strategy, "
        ."net sheets, and a one-on-one consultation — reach out to {$teamName} anytime. We'd love to help.";
}

// ── Preview: return only the narrative ───────────────────────────
if ($previewOnly) {
    echo json_encode(['success'=>true, 'narrative'=>$aiNarrative]);
    exit;
}

// ── 2. Build email HTML ───────────────────────────────────────────

// Agent signature vars from config
$sigName    = $agentName  ?: (defined('AGENT_NAME')        ? AGENT_NAME        : '');
$sigEmail   = $agentEmail ?: (defined('AGENT_EMAIL')       ? AGENT_EMAIL       : '');
$sigPhone   = $agentPhone ?: (defined('AGENT_PHONE')       ? AGENT_PHONE       : '');
$sigTitle   = defined('AGENT_TITLE')        ? AGENT_TITLE        : '';
$sigLicense = defined('AGENT_LICENSE')      ? AGENT_LICENSE      : '';
$sigWebsite = defined('AGENT_WEBSITE')      ? AGENT_WEBSITE      : '';
$sigPhoto   = defined('AGENT_PHOTO_URL')    ? AGENT_PHOTO_URL    : '';
$sigFB      = defined('AGENT_FACEBOOK')     ? AGENT_FACEBOOK     : '';
$sigTW      = defined('AGENT_TWITTER')      ? AGENT_TWITTER      : '';
$sigLI      = defined('AGENT_LINKEDIN')     ? AGENT_LINKEDIN     : '';
$sigYT      = defined('AGENT_YOUTUBE')      ? AGENT_YOUTUBE      : '';
$sigIG      = defined('AGENT_INSTAGRAM')    ? AGENT_INSTAGRAM    : '';
$sigPIN     = defined('AGENT_PINTEREST')    ? AGENT_PINTEREST    : '';
$sigBlog    = defined('AGENT_BLOG')         ? AGENT_BLOG         : '';
$sigBroker  = defined('BROKERAGE_NAME')     ? BROKERAGE_NAME     : '';
$sigLogo    = defined('BROKERAGE_LOGO_URL') ? BROKERAGE_LOGO_URL : '';

$propCount  = count($properties);
$propWord   = $propCount === 1 ? 'Property' : 'Properties';
$reportDate = date('F j, Y');

// Narrative → HTML paragraphs
$narrativeHtml = '';
foreach (array_filter(explode("\n\n", $aiNarrative)) as $para) {
    $narrativeHtml .= '<p style="margin:0 0 16px;color:#374151;font-size:15px;line-height:1.8;">'
        .nl2br(htmlspecialchars(trim($para))).'</p>';
}

// ── Subject Property Section ─────────────────────────────────────
$subjectHtml = '';
if ($subjectAttom && empty($subjectAttom['_error'])) {
    $a = $subjectAttom;
    // Owner
    $owners = array_filter([$a['owner1'] ?? '', $a['owner2'] ?? '', $a['owner3'] ?? '']);
    $ownerHtml = $owners ? htmlspecialchars(implode(' & ', $owners)) : 'N/A';
    $occupancyBadge = '';
    if (!empty($a['owner_occupied'])) {
        $occupancyBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:20px;background:#d1fae5;color:#065f46;font-size:9px;font-weight:700;text-transform:uppercase;margin-left:8px;">Owner Occupied</span>';
    } elseif (!empty($a['absentee_status'])) {
        $occupancyBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:20px;background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;text-transform:uppercase;margin-left:8px;">Absentee</span>';
    }

    // Stats cells
    $statCells = '';
    $stats = [];
    if (!empty($a['bedrooms']))   $stats[] = ['val'=>$a['bedrooms'], 'label'=>'Beds'];
    if (!empty($a['bathrooms']))  $stats[] = ['val'=>$a['bathrooms'], 'label'=>'Baths'];
    if (!empty($a['gross_sqft'])) $stats[] = ['val'=>number_format((float)$a['gross_sqft']), 'label'=>'Sq Ft'];
    if (!empty($a['year_built'])) $stats[] = ['val'=>$a['year_built'], 'label'=>'Built'];
    if (!empty($a['lot_size_sqft'])) $stats[] = ['val'=>number_format((float)$a['lot_size_sqft']), 'label'=>'Lot Sqft'];
    if (!empty($a['stories']))    $stats[] = ['val'=>$a['stories'], 'label'=>'Stories'];
    foreach ($stats as $i => $s) {
        $border = $i < count($stats)-1 ? 'border-right:1px solid #e0e4f0;' : '';
        $statCells .= '<td style="padding:12px;text-align:center;'.$border.'">'
            .'<div style="font-size:18px;font-weight:800;color:#1e1b4b;">'.$s['val'].'</div>'
            .'<div style="font-size:9px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-top:2px;">'.$s['label'].'</div></td>';
    }

    // Financial info
    $finRows = '';
    if (!empty($a['last_sale_price'])) $finRows .= '<tr><td style="padding:6px 0;font-size:12px;color:#6b7280;">Last Purchase</td><td style="padding:6px 0;font-size:12px;font-weight:600;color:#111827;text-align:right;">'.fmtP($a['last_sale_price']).(!empty($a['last_sale_date']) ? ' <span style="color:#9ca3af;">('.fmtD2($a['last_sale_date']).')</span>' : '').'</td></tr>';
    if (!empty($a['prior_sale_price'])) $finRows .= '<tr><td style="padding:6px 0;font-size:12px;color:#6b7280;">Prior Sale</td><td style="padding:6px 0;font-size:12px;font-weight:600;color:#111827;text-align:right;">'.fmtP($a['prior_sale_price']).(!empty($a['prior_sale_date']) ? ' <span style="color:#9ca3af;">('.fmtD2($a['prior_sale_date']).')</span>' : '').'</td></tr>';
    if (!empty($a['assessed_total'])) $finRows .= '<tr><td style="padding:6px 0;font-size:12px;color:#6b7280;">Assessed Value</td><td style="padding:6px 0;font-size:12px;font-weight:600;color:#111827;text-align:right;">'.fmtP($a['assessed_total']).'</td></tr>';
    if (!empty($a['tax_amount'])) $finRows .= '<tr><td style="padding:6px 0;font-size:12px;color:#6b7280;">Annual Tax</td><td style="padding:6px 0;font-size:12px;font-weight:600;color:#111827;text-align:right;">'.fmtP($a['tax_amount']).($a['tax_year'] ? ' ('.$a['tax_year'].')' : '').'</td></tr>';

    // Loans
    $loanHtml = '';
    if (!empty($a['loans'])) {
        foreach ($a['loans'] as $loan) {
            $loanAmt = !empty($loan['amount']) ? fmtP($loan['amount']) : '—';
            $loanType = $loan['type'] ?? '';
            $loanLender = $loan['lender'] ?? '';
            $loanHtml .= '<tr><td style="padding:6px 0;font-size:12px;color:#6b7280;">'.htmlspecialchars($loan['position'] ?? 'Loan').'</td><td style="padding:6px 0;font-size:12px;font-weight:600;color:#111827;text-align:right;">'.$loanAmt.($loanType ? ' <span style="color:#9ca3af;">('.htmlspecialchars($loanType).')</span>' : '').'</td></tr>';
        }
    }

    $subjectHtml = '
    <tr><td style="background:#fff;padding:0 40px 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:14px;overflow:hidden;border:2px solid #4f46e5;box-shadow:0 8px 32px rgba(79,70,229,.12);">
            <tr><td style="background:linear-gradient(135deg,#eef2ff,#e0e7ff);padding:20px 24px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:#4f46e5;margin-bottom:6px;">Subject Property</div>
                <div style="font-size:18px;font-weight:800;color:#1e1b4b;margin-bottom:4px;">'.htmlspecialchars($subjectAddr).'</div>
                <div style="font-size:12px;color:#6366f1;">'.$ownerHtml.$occupancyBadge.'</div>
            </td></tr>
            <tr><td style="padding:0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff;">'
                    .($statCells ? '<tr>'.$statCells.'</tr>' : '').'
                </table>
            </td></tr>'
            .($finRows || $loanHtml ? '
            <tr><td style="padding:16px 24px;background:#fff;">
                <table width="100%" cellpadding="0" cellspacing="0">'
                    .$finRows.$loanHtml.'
                </table>
            </td></tr>' : '').'
        </table>
    </td></tr>';
}

// ── Status colors ────────────────────────────────────────────────
$statusColors = [
    'Active'                => ['bg'=>'#d1fae5','text'=>'#065f46'],
    'Coming Soon'           => ['bg'=>'#ede9fe','text'=>'#5b21b6'],
    'Active Under Contract' => ['bg'=>'#fef3c7','text'=>'#92400e'],
    'Pending'               => ['bg'=>'#ffedd5','text'=>'#9a3412'],
    'Closed'                => ['bg'=>'#fee2e2','text'=>'#991b1b'],
    'Canceled'              => ['bg'=>'#f3f4f6','text'=>'#374151'],
    'Expired'               => ['bg'=>'#f3f4f6','text'=>'#374151'],
];

// ── Property comp rows ───────────────────────────────────────────
$propRows = '';
$compNum = 0;
foreach ($properties as $p) {
    $compNum++;
    $status   = $p['StandardStatus'] ?? 'Unknown';
    $sc       = $statusColors[$status] ?? ['bg'=>'#f3f4f6','text'=>'#374151'];
    $isClosed = $status === 'Closed';
    $price    = $isClosed ? ((float)($p['ClosePrice'] ?? $p['ListPrice'] ?? 0)) : (float)($p['ListPrice'] ?? 0);
    $listP    = (float)($p['ListPrice'] ?? 0);
    $addr     = htmlspecialchars(trim(($p['StreetNumber']??'').' '.($p['StreetName']??'').', '.($p['City']??'').', '.($p['StateOrProvince']??'').' '.($p['PostalCode']??'')));
    $photo    = $p['_photo'] ?? '';
    $beds     = $p['BedroomsTotal']         ?? '—';
    $baths    = $p['BathroomsTotalInteger'] ?? '—';
    $sqft     = $p['LivingArea']   ? number_format((float)$p['LivingArea']) : '—';
    $yr       = $p['YearBuilt']    ?? '—';
    $dom      = $p['DaysOnMarket'] ?? null;
    $dist     = isset($p['_distance']) ? round((float)$p['_distance'],2).' mi' : '';
    $remarks  = htmlspecialchars(substr($p['PublicRemarks'] ?? '', 0, 200));

    $photoHtml = $photo
        ? '<img src="'.htmlspecialchars($photo).'" width="100%" style="display:block;width:100%;height:200px;object-fit:cover;border-radius:10px 10px 0 0;">'
        : '<div style="background:#e5e7eb;height:120px;border-radius:10px 10px 0 0;display:flex;align-items:center;justify-content:center;font-size:2rem;">🏠</div>';

    $closedStrip = $isClosed ? '
        <tr><td colspan="2" style="padding:0 0 12px;">
            <table width="100%" style="background:#fff5f5;border-radius:8px;border:1px solid #fecaca;"><tr>
                <td style="padding:8px 14px;text-align:center;">
                    <div style="font-size:10px;color:#991b1b;text-transform:uppercase;">Sold</div>
                    <div style="font-size:13px;font-weight:700;color:#dc2626;">'.fmtD2($p['CloseDate']??'').'</div>
                </td>
                <td style="padding:8px 14px;text-align:center;border-left:1px solid #fecaca;">
                    <div style="font-size:10px;color:#991b1b;text-transform:uppercase;">Sale Price</div>
                    <div style="font-size:13px;font-weight:700;color:#dc2626;">'.fmtP($price).'</div>
                </td>
                <td style="padding:8px 14px;text-align:center;border-left:1px solid #fecaca;">
                    <div style="font-size:10px;color:#991b1b;text-transform:uppercase;">List Price</div>
                    <div style="font-size:13px;font-weight:700;color:#dc2626;">'.fmtP($listP).'</div>
                </td>
            </tr></table>
        </td></tr>' : '';

    $domCell = $dom !== null
        ? '<td style="padding:10px;text-align:center;"><div style="font-size:16px;font-weight:700;color:#111827;">'.$dom.'</div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;">DOM</div></td>'
        : '';

    $propRows .= '
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 4px 16px rgba(0,0,0,.08);">
        <tr><td>'.$photoHtml.'</td></tr>
        <tr><td style="padding:16px 18px;background:#fff;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;"><tr>
                <td>
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;background:'.$sc['bg'].';color:'.$sc['text'].';font-size:10px;font-weight:700;text-transform:uppercase;">'.$status.'</span>
                    <span style="font-size:10px;color:#9ca3af;margin-left:6px;">Comp #'.$compNum.'</span>
                    '.($dist ? '<span style="font-size:10px;color:#9ca3af;margin-left:6px;">'.$dist.'</span>' : '').'
                </td>
                <td align="right"><span style="font-size:18px;font-weight:800;color:#4f46e5;">'.($isClosed ? '' : fmtP($price)).'</span></td>
            </tr></table>
            <div style="font-size:14px;font-weight:700;color:#111827;margin-bottom:12px;">'.$addr.'</div>
            <table width="100%" cellpadding="0" cellspacing="0">'.$closedStrip.'</table>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:8px;margin-bottom:10px;">
                <tr>
                    <td style="padding:10px;text-align:center;border-right:1px solid #f3f4f6;"><div style="font-size:15px;font-weight:700;color:#111827;">'.$beds.'</div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;">Beds</div></td>
                    <td style="padding:10px;text-align:center;border-right:1px solid #f3f4f6;"><div style="font-size:15px;font-weight:700;color:#111827;">'.$baths.'</div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;">Baths</div></td>
                    <td style="padding:10px;text-align:center;border-right:1px solid #f3f4f6;"><div style="font-size:15px;font-weight:700;color:#111827;">'.$sqft.'</div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;">Sq Ft</div></td>
                    <td style="padding:10px;text-align:center;'.($dom!==null?'border-right:1px solid #f3f4f6;':'').'"><div style="font-size:15px;font-weight:700;color:#111827;">'.$yr.'</div><div style="font-size:10px;color:#9ca3af;text-transform:uppercase;">Built</div></td>
                    '.$domCell.'
                </tr>
            </table>
            '.($remarks ? '<p style="font-size:12px;color:#6b7280;line-height:1.6;margin:0;border-left:3px solid #e5e7eb;padding-left:10px;">'.$remarks.'</p>' : '').'
        </td></tr>
    </table>';
}

// CTA button
$ctaHtml = $siteUrl
    ? '<a href="'.htmlspecialchars($siteUrl).'" style="display:inline-block;padding:13px 32px;background:#4f46e5;color:#fff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;">Explore Full Report Online</a>'
    : '';

// Headshot
$headshotHtml = $sigPhoto
    ? '<td width="90" valign="top" style="padding-right:16px;"><img src="'.htmlspecialchars($sigPhoto).'" alt="'.htmlspecialchars($sigName).'" width="80" height="80" style="border-radius:50%;display:block;border:2px solid #e5e7eb;"></td>'
    : '';

// Title / license line
$titleLine = ($sigTitle || $sigLicense)
    ? '<div style="font-size:12px;color:#333;font-family:Arial,sans-serif;margin-top:2px;">'.htmlspecialchars($sigTitle).($sigTitle && $sigLicense ? ' &nbsp;|&nbsp; ' : '').($sigLicense ? 'Lic# '.htmlspecialchars($sigLicense) : '').'</div>'
    : '';

// Social icons
$socialHtml = '';
$socialLinks = [
    $sigFB   => ['facebook-ft-blue.png', 'Facebook'],
    $sigTW   => ['twitter-ft-blue_v2.png', 'Twitter'],
    $sigLI   => ['linkedin-ft-blue.png', 'LinkedIn'],
    $sigYT   => ['youtube-ft-blue.png', 'YouTube'],
    $sigIG   => ['instagram-ft-blue.png', 'Instagram'],
    $sigPIN  => ['pinterest-ft-blue.png', 'Pinterest'],
    $sigBlog => ['blog-ft-blue.png', 'Blog'],
];
foreach ($socialLinks as $url => $info) {
    if (!$url) continue;
    $socialHtml .= '<td style="padding-right:6px;"><a href="'.htmlspecialchars($url).'" target="_blank">'
        .'<img src="http://agentphoto.firstteam.com/sigblock/social-icons/'.$info[0].'" width="19" height="19" alt="'.$info[1].'" style="display:block;"></a></td>';
}
$socialBlock = $socialHtml
    ? '<table cellpadding="0" cellspacing="0" style="margin-top:10px;"><tr>'.$socialHtml.'</tr></table>'
    : '';

// Brokerage logo
$brokerBlock = $sigLogo
    ? '<div style="margin-top:10px;"><img src="'.htmlspecialchars($sigLogo).'" alt="'.htmlspecialchars($sigBroker).'" style="display:block;max-width:200px;"></div>'
    : ($sigBroker ? '<div style="margin-top:8px;font-size:11px;color:#6b7280;font-family:Arial,sans-serif;">'.htmlspecialchars($sigBroker).'</div>' : '');

// Greeting
$greetingLine = $recipientFirst
    ? '<p style="margin:0 0 14px;font-size:15px;font-weight:600;color:#1e1b4b;">Hi '.htmlspecialchars($recipientFirst).',</p>'
    : '';

$htmlEmail = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CMA &mdash; '.htmlspecialchars($subjectAddr).'</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;">

<tr><td style="border-radius:16px 16px 0 0;background:linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#1e3a5f 100%);padding:40px 40px 36px;">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:#a5b4fc;margin-bottom:12px;">Comparative Market Analysis</div>
    <h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#fff;line-height:1.2;">'.htmlspecialchars($subjectAddr).'</h1>
    <div style="font-size:13px;color:#a5b4fc;">'.$reportDate.' &nbsp;&middot;&nbsp; '.$propCount.' Comparable '.$propWord.'</div>
</td></tr>

'.$subjectHtml.'

<tr><td style="background:#fff;padding:36px 40px;">
    <div style="margin-bottom:32px;padding:24px;background:#f8f7ff;border-radius:12px;border-left:4px solid #4f46e5;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#6366f1;margin-bottom:14px;">Market Analysis</div>
        '.$greetingLine.'
        '.$narrativeHtml.'
    </div>
    <div style="border-bottom:2px solid #e5e7eb;padding-bottom:12px;margin-bottom:24px;">
        <h2 style="margin:0;font-size:16px;font-weight:700;color:#111827;text-transform:uppercase;letter-spacing:.08em;">Comparable Properties (Sorted by Price)</h2>
    </div>
    '.$propRows.'
    <div style="border-top:1px solid #e5e7eb;margin:32px 0;"></div>
    <div style="text-align:center;padding:24px;background:#f8f7ff;border-radius:12px;">
        <p style="margin:0 0 8px;font-size:15px;color:#374151;font-weight:600;">Want a More In-Depth Analysis?</p>
        <p style="margin:0 0 20px;font-size:13px;color:#6b7280;line-height:1.7;">This CMA is a snapshot. For a full analysis including pricing strategy and a personalized consultation, reach out anytime.</p>
        '.$ctaHtml.'
    </div>
</td></tr>

<tr><td style="background:#fff;padding:0 40px 32px;">
    <p style="margin:0 0 20px;font-size:14px;color:#374151;">Warm regards,</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb;padding-top:20px;">
    <tr>
        '.$headshotHtml.'
        <td valign="top">
            <div style="font-size:18px;font-weight:700;font-family:Arial,sans-serif;color:#132337;">'.htmlspecialchars($sigName).'</div>
            '.$titleLine.'
            '.($sigPhone ? '<div style="margin-top:6px;"><a href="tel:'.htmlspecialchars($sigPhone).'" style="font-size:12px;color:#333;font-family:Arial,sans-serif;text-decoration:none;">'.htmlspecialchars($sigPhone).'</a></div>' : '').'
            '.($sigEmail ? '<div style="margin-top:2px;"><a href="mailto:'.htmlspecialchars($sigEmail).'" style="font-size:12px;color:#86899d;font-family:Arial,sans-serif;text-decoration:none;">'.htmlspecialchars($sigEmail).'</a></div>' : '').'
            '.($sigWebsite ? '<div style="margin-top:2px;"><a href="'.htmlspecialchars($sigWebsite).'" style="font-size:12px;color:#86899d;font-family:Arial,sans-serif;text-decoration:none;">'.preg_replace('#^https?://#','',htmlspecialchars($sigWebsite)).'</a></div>' : '').'
            '.$socialBlock.'
            '.$brokerBlock.'
        </td>
    </tr>
    </table>
</td></tr>

<tr><td style="background:#1f2130;border-radius:0 0 16px 16px;padding:20px 40px;">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td><div style="font-size:10px;color:#8892a4;line-height:1.6;">This CMA is based on MLS data and public records. All information is deemed reliable but not guaranteed. This is not an appraisal. Contact your agent for a full evaluation.</div></td>
        <td align="right" valign="middle"><div style="font-size:9px;color:#6b7080;text-transform:uppercase;letter-spacing:.1em;white-space:nowrap;">Powered by MLS Intelligence</div></td>
    </tr></table>
</td></tr>

</table>
</td></tr>
</table>
</body></html>';

// ── 3. Send email ─────────────────────────────────────────────────
$errors  = [];
$sent    = [];
$subject = $emailSubject ?: "Your CMA — {$subjectAddr}";

foreach ($recipients as $r) {
    $toEmail = filter_var(trim($r['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $toName  = trim($r['name'] ?? '');
    if (!$toEmail) { $errors[] = "Invalid email: ".($r['email']??''); continue; }

    $html = $htmlEmail;

    $fromEmail = $sigEmail ?: (defined('AGENT_EMAIL') ? AGENT_EMAIL : 'Chip@chipandkim.com');
    $fromName  = $sigName  ?: (defined('AGENT_NAME')  ? AGENT_NAME  : 'Chip McAllister');
    $cleanName = str_replace(['"',"'"], '', $fromName);
    $toHeader  = $toName ? '"'.str_replace('"','',$toName).'" <'.$toEmail.'>' : $toEmail;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: \"{$cleanName}\" <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: MLS-CMA/5.0\r\n";

    if (@mail($toHeader, $subject, $html, $headers, "-f {$fromEmail}")) {
        $sent[] = $toEmail;
    } else {
        $err = error_get_last();
        $errors[] = "Failed to send to {$toEmail}".($err ? ': '.$err['message'] : '');
    }
}

echo json_encode([
    'success' => count($sent) > 0,
    'sent'    => $sent,
    'errors'  => $errors,
    'message' => count($sent) > 0
        ? 'CMA sent to '.implode(', ',$sent).(count($errors) ? ' ('.implode('; ',$errors).')' : '')
        : 'Failed: '.implode('; ',$errors),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage().' line '.$e->getLine()]);
}
