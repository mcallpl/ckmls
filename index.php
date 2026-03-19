<?php
require_once __DIR__ . '/config.php';
$cacheBust = filemtime(__DIR__ . '/js/app.js');
header('Cache-Control: no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MLS Property Search</title>
    <link rel="stylesheet" href="css/style.css?v=<?=$cacheBust?>">
</head>
<body>

<!-- ── THEME TOGGLE ────────────────────────────────────── -->
<button class="theme-toggle" id="themeToggle" title="Switch theme">🌙</button>

<!-- ── LOADING OVERLAY ──────────────────────────────────── -->
<div id="loader">
    <div class="loader-wrap">
        <div class="loader-ring"></div>
        <div id="loader-icon" style="font-size:2rem">🏠</div>
    </div>
    <div id="loader-msg" class="loader-msg">Warming up the engines...</div>
    <div class="loader-sub">Searching the MLS</div>
    <div class="loader-dots"><span></span><span></span><span></span></div>
</div>

<!-- ── HEADER ──────────────────────────────────────────── -->
<div class="header">
    <div class="eyebrow">MLS Property Intelligence</div>
    <h1>Find Every Home<br><span>Near Any Address</span></h1>
    <p class="header-sub">Enter any address — listed or not — and explore all MLS activity, public records, and ownership info nearby.</p>
</div>

<div class="container">

<!-- ── SEARCH CARD ─────────────────────────────────────── -->
<div class="search-card">

    <!-- Status pills -->
    <div class="sec-label">Listing Status <span class="sec-label-sub">(select one or more)</span></div>
    <div class="status-pills">
        <label class="status-pill active-pill">
            <input type="checkbox" class="pill-cb" value="Active" checked>
            <span class="pill-dot"></span>Active
        </label>
        <label class="status-pill cs-pill">
            <input type="checkbox" class="pill-cb" value="Coming Soon">
            <span class="pill-dot"></span>Coming Soon
        </label>
        <label class="status-pill auc-pill">
            <input type="checkbox" class="pill-cb" value="Active Under Contract">
            <span class="pill-dot"></span>Under Contract
        </label>
        <label class="status-pill pending-pill">
            <input type="checkbox" class="pill-cb" value="Pending">
            <span class="pill-dot"></span>Pending
        </label>
        <label class="status-pill closed-pill">
            <input type="checkbox" class="pill-cb" value="Closed" id="closedChk">
            <span class="pill-dot"></span>Closed (Sales)
        </label>
        <label class="status-pill canceled-pill">
            <input type="checkbox" class="pill-cb" value="Canceled">
            <span class="pill-dot"></span>Canceled
        </label>
        <label class="status-pill expired-pill">
            <input type="checkbox" class="pill-cb" value="Expired">
            <span class="pill-dot"></span>Expired
        </label>
    </div>

    <!-- Sold within (shows when Closed checked) -->
    <div id="cdGroup" class="closed-group" style="margin-top:14px">
        <label>Closed Sales — Sold Within</label>
        <select id="cdSel" style="max-width:220px">
            <option value="30">Last 30 days</option>
            <option value="60">Last 60 days</option>
            <option value="90" selected>Last 90 days</option>
            <option value="180">Last 6 months</option>
            <option value="365">Last year</option>
            <option value="730">Last 2 years</option>
            <option value="1825">Last 5 years</option>
            <option value="3650">Last 10 years</option>
        </select>
    </div>

    <hr class="divider">

    <!-- Radius -->
    <div class="sec-label">Search Radius</div>
    <select id="rSel" style="max-width:200px">
        <option value="0.0625">1/16 mile</option>
        <option value="0.125">⅛ mile</option>
        <option value="0.25">¼ mile</option>
        <option value="0.5">½ mile</option>
        <option value="1.0" selected>1 mile</option>
        <option value="2.0">2 miles</option>
        <option value="5.0">5 miles</option>
        <option value="10.0">10 miles</option>
    </select>

    <hr class="divider">

    <!-- THE FORM — only contains what gets POSTed -->
    <form id="searchForm">

        <!-- Status checkboxes injected here by syncForm() -->
        <div id="statusContainer"></div>

        <!-- closed_days and radius hidden -->
        <input type="hidden" id="hCD" name="closed_days" value="90">
        <input type="hidden" id="hR"  name="radius"      value="1.0">

        <!-- Address — full-width single field -->
        <div class="search-hero">
            <div class="search-hero-inner">
                <span class="search-hero-icon">📍</span>
                <input type="text"
                       id="addrInput"
                       name="full_address"
                       class="search-hero-input"
                       placeholder="e.g. 24312 Airporter Way, Laguna Niguel CA 92677"
                       autocomplete="new-password"
                       spellcheck="false"
                       required>
                <button type="button" id="clearBtn" class="search-clear" style="display:none">✕</button>
            </div>
        </div>

        <button type="submit" class="btn-search">
            🔍 &nbsp;Search MLS Activity Near This Address
        </button>

    </form>
</div><!-- /search-card -->

<!-- ── MAP ──────────────────────────────────────────────── -->
<div id="map-container">
    <div id="map"></div>
    <div id="map-draw-controls">
        <button id="btn-draw-poly" class="map-ctrl-btn" type="button">Draw Area</button>
        <button id="btn-clear-poly" class="map-ctrl-btn" type="button" style="display:none">Clear Area</button>
    </div>
</div>

<!-- ── RESULTS ──────────────────────────────────────────── -->
<div id="results-wrap"></div>

</div><!-- /container -->

<script src="js/sort.js?v=<?=$cacheBust?>"></script>
<script src="js/filters.js?v=<?=$cacheBust?>"></script>
<script src="js/cards.js?v=<?=$cacheBust?>"></script>
<script src="js/records.js?v=<?=$cacheBust?>"></script>
<script src="js/cma.js?v=<?=$cacheBust?>"></script>
<script src="js/app.js?v=<?=$cacheBust?>"></script>
<script src="js/map.js?v=<?=$cacheBust?>"></script>
<script>
/* Fix the result count display — watches for #result-count and rewrites it.
   This works even if the browser has cached old JS that sets it directly. */
(function() {
    var observer = new MutationObserver(function() {
        var el = document.getElementById('result-count');
        if (!el) return;
        if (el.dataset.fixed) return; /* already fixed */
        if (typeof appData === 'undefined' || !appData) return;

        var showing = appData.properties ? appData.properties.length : 0;
        {
            el.innerHTML = '<strong>' + showing.toLocaleString() + '</strong> home' + (showing !== 1 ? 's' : '');
        }
        el.dataset.fixed = '1';
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>
<script>
/* =============================================================
   Inline UI logic — pills, selects, address clear button
   app.js handles: loader, fetch, render, sorting, cards
   ============================================================= */

const pills     = document.querySelectorAll('.pill-cb');
const closedChk = document.getElementById('closedChk');
const cdGroup   = document.getElementById('cdGroup');
const cdSel     = document.getElementById('cdSel');
const rSel      = document.getElementById('rSel');
const hCD       = document.getElementById('hCD');
const hR        = document.getElementById('hR');
const scBox     = document.getElementById('statusContainer');
const addrInput = document.getElementById('addrInput');
const clearBtn  = document.getElementById('clearBtn');

// Build hidden status[] inputs so they POST correctly
function syncForm() {
    scBox.innerHTML = '';
    pills.forEach(cb => {
        if (!cb.checked) return;
        const h = document.createElement('input');
        h.type  = 'hidden';
        h.name  = 'status[]';
        h.value = cb.value;
        scBox.appendChild(h);
    });
    hCD.value = cdSel.value;
    hR.value  = rSel.value;
}

// Show/hide "Sold Within" when Closed is toggled
function toggleClosedDays() {
    cdGroup.classList.toggle('on', closedChk.checked);
}

// Init pill visual state
pills.forEach(cb => {
    if (cb.checked) cb.closest('.status-pill').classList.add('checked');
    cb.addEventListener('change', () => {
        cb.closest('.status-pill').classList.toggle('checked', cb.checked);
        toggleClosedDays();
        syncForm();
    });
});

cdSel.addEventListener('change', syncForm);
rSel.addEventListener('change', function() {
    if (typeof clearPolygon === 'function' && typeof spatialMode !== 'undefined' && spatialMode === 'polygon') {
        clearPolygon();
    }
    syncForm();
    if (typeof appData !== 'undefined' && appData && addrInput.value.trim()) {
        document.getElementById('searchForm').requestSubmit();
    }
});
toggleClosedDays();
syncForm();

// Address clear button
addrInput.addEventListener('input', () => {
    clearBtn.style.display = addrInput.value ? 'flex' : 'none';
});
clearBtn.addEventListener('click', () => {
    addrInput.value = '';
    clearBtn.style.display = 'none';
    addrInput.focus();
});

/* =============================================================
   Google Maps callback — map display only, no Places needed
   ============================================================= */
function initGoogleMaps() {
    window._googleMapsReady = true;
    if (window._pendingMapCall) {
        window._pendingMapCall();
        window._pendingMapCall = null;
    }
}

/* =============================================================
   Theme toggle — persisted in localStorage
   ============================================================= */
(function() {
    const btn   = document.getElementById('themeToggle');
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    btn.textContent = saved === 'dark' ? '☀️' : '🌙';

    btn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') || 'dark';
        const next    = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        btn.textContent = next === 'dark' ? '☀️' : '🌙';
    });
})();
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=geometry&callback=initGoogleMaps"
    async defer>
</script>

</body>
</html>
