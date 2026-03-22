/* ============================================================
   app.js — State, loader, form submit, result rendering (v2)
   Depends on: filters.js, sort.js, cards.js, records.js, cma.js
   ============================================================ */

const LOADER_MESSAGES = [
    { icon: '🍪', text: 'Baking fresh listings just for you...' },
    { icon: '🥚', text: 'Hatching your neighborhood analysis...' },
    { icon: '🔑', text: 'Jingling through 10,000 MLS keys...' },
    { icon: '🔭', text: 'Peeking over the property fence...' },
    { icon: '☕', text: 'Brewing your neighborhood report...' },
    { icon: '🧙', text: 'Consulting the oracle of home prices...' },
    { icon: '🏡', text: 'Counting windows & judging curb appeal...' },
    { icon: '📬', text: 'Checking every neighborhood mailbox...' },
    { icon: '🐢', text: 'Convincing the MLS to reveal secrets...' },
    { icon: '🎯', text: 'Triangulating the perfect comps...' },
    { icon: '🌮', text: 'Assembling your data tacos...' },
    { icon: '🧁', text: 'Frosting your market analysis...' },
    { icon: '🦅', text: 'Soaring over the MLS database...' },
    { icon: '📊', text: 'Making spreadsheets look inadequate...' },
    { icon: '🏊', text: 'Swimming through a sea of listings...' },
    { icon: '💎', text: 'Mining the MLS for diamonds...' },
    { icon: '🚀', text: 'Launching into the neighborhood data...' },
    { icon: '🌱', text: 'Growing your property intelligence...' },
    { icon: '🎪', text: 'Wrangling the neighborhood data circus...' },
    { icon: '🧲', text: 'Attracting the best listings your way...' },
];

// ── Global state ─────────────────────────────────────────────────
let appData        = null;
let appTotalCount  = null;
let loadingMore    = false;
let loaderInterval = null;
let loaderMsgIndex = 0;
window.mapMarkers  = [];
window.centerMarker= null;

// ── Home selection state ─────────────────────────────────────────
// Keys are a unique property identifier (ListingId or index-based fallback)
const selectedHomes = new Set();

function getPropKey(prop) {
    return prop.ListingId || prop.ListingKey || (prop.StreetNumber + ' ' + prop.StreetName + ' ' + prop.PostalCode);
}

function selectAllVisible() {
    const filtered = getSortedProperties(applyFilters(appData?.properties || []), currentSort);
    filtered.forEach(p => selectedHomes.add(getPropKey(p)));
    syncCheckboxes();
    updateSelectAllToggle();
}

function unselectAll() {
    selectedHomes.clear();
    syncCheckboxes();
    updateSelectAllToggle();
}

function syncCheckboxes() {
    document.querySelectorAll('.card-select-cb').forEach(cb => {
        cb.checked = selectedHomes.has(cb.dataset.propKey);
    });
    updateSelectedCount();
}

function updateSelectAllToggle() {
    const toggle = document.getElementById('selectAllToggle');
    if (!toggle) return;
    const filtered = applyFilters(appData?.properties || []);
    const allSelected = filtered.length > 0 && filtered.every(p => selectedHomes.has(getPropKey(p)));
    toggle.checked = allSelected;
    updateSelectedCount();
}

function updateSelectedCount() {
    const el = document.getElementById('selected-count');
    if (!el) return;
    // Count only checked homes that are currently visible (pass filters)
    const filtered = applyFilters(appData?.properties || []);
    const checkedCount = filtered.filter(p => selectedHomes.has(getPropKey(p))).length;
    const totalFiltered = filtered.length;
    el.innerHTML = `<span class="count-selected">${checkedCount} selected</span>`
        + `<span class="count-total">${totalFiltered} home${totalFiltered !== 1 ? 's' : ''} listed</span>`;
    el.style.display = 'inline';
}

function buildSelectionBar() {
    const bar = document.createElement('div');
    bar.className = 'selection-bar';
    bar.id = 'selection-bar';
    bar.innerHTML = `
        <label class="select-all-label">
            <input type="checkbox" id="selectAllToggle" checked>
            <span class="select-all-box"></span>
            <span>Select All</span>
        </label>
        <span class="selected-count" id="selected-count"></span>`;
    bar.querySelector('#selectAllToggle').addEventListener('change', function() {
        if (this.checked) selectAllVisible();
        else unselectAll();
    });
    // Initialize count display
    setTimeout(updateSelectedCount, 0);
    return bar;
}

// ── Loader ───────────────────────────────────────────────────────
const loader    = document.getElementById('loader');
const loaderMsg = document.getElementById('loader-msg');
const loaderIco = document.getElementById('loader-icon');

function showLoader() {
    loaderMsgIndex = Math.floor(Math.random() * LOADER_MESSAGES.length);
    setLoaderMessage(loaderMsgIndex);
    loader.classList.add('active');
    loaderInterval = setInterval(() => {
        loaderMsgIndex = (loaderMsgIndex + 1) % LOADER_MESSAGES.length;
        loaderMsg.style.opacity = 0;
        setTimeout(() => { setLoaderMessage(loaderMsgIndex); loaderMsg.style.opacity = 1; }, 300);
    }, 2400);
}
function setLoaderMessage(idx) {
    const m = LOADER_MESSAGES[idx];
    loaderIco.textContent = m.icon;
    loaderMsg.textContent = m.text;
}
function hideLoader() {
    clearInterval(loaderInterval);
    loader.classList.remove('active');
}

// ── Form submit ──────────────────────────────────────────────────
document.getElementById('searchForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const addr = document.getElementById('addrInput');
    if (!addr || !addr.value.trim()) { showError('Please enter an address to search.'); return; }

    showLoader();

    // Preserve the radius value during drag-triggered submits
    const savedRadius = document.getElementById('hR')?.value;
    try { syncForm(); } catch(ex) { console.warn('syncForm error:', ex); }
    // If this submit was triggered by a radius drag, ensure the dragged value survives
    if (typeof radiusDragInProgress !== 'undefined' && radiusDragInProgress && savedRadius) {
        const hR = document.getElementById('hR');
        if (hR) hR.value = savedRadius;
    }

    const formData = new FormData(e.target);

    try {
        const res  = await fetch('search.php', { method: 'POST', body: formData });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); }
        catch (_) {
            console.error('Raw response:', text);
            throw new Error('Server error — open browser console (F12) for details.');
        }

        hideLoader();
        if (!data.success) { showError(data.error || 'An unknown error occurred.'); return; }

        appData = data;
        appTotalCount = data.totalCount || null;
        appData.searchedAddress = addr.value.trim();
        renderResults(data);
        // Init map with filtered properties so markers match visible cards
        const filteredForMap = getSortedProperties(applyFilters(data.properties), currentSort);
        initMap(data.geocoded, filteredForMap);

    } catch (err) {
        hideLoader();
        showError(err.message || 'Network error — check your connection.');
        console.error(err);
    }
});

// ── Render results ───────────────────────────────────────────────
function renderResults(data) {
    clearError();
    const wrap = document.getElementById('results-wrap');
    wrap.innerHTML = '';

    // Banner
    const banner = document.createElement('div');
    banner.className = 'c-banner';
    banner.innerHTML = `
        <div class="c-pin">📍</div>
        <div class="c-text">
            <div class="c-addr">${esc(data.geocoded.display_name)}</div>
            <div class="c-scope">
                Showing <strong>${esc(data.statusLabel)}${data.hasClosed ? ', last ' + data.closedDays + ' days' : ''}</strong>
                listings in <strong>${esc(data.geoScope)}</strong>
                &nbsp;(${esc(data.radiusLabel)} radius)
            </div>
        </div>`;
    wrap.appendChild(banner);

    document.getElementById('map-container').classList.add('visible');
    wrap.appendChild(buildRecordsSection(data));

    if (data.properties.length > 0) {
        // Preserve existing filter criteria across re-searches (criteria locking)
        // Do NOT call resetFilters() — keep beds, baths, type, price, sqft, lot, rental

        // Select all homes by default
        selectedHomes.clear();
        data.properties.forEach(p => selectedHomes.add(getPropKey(p)));

        wrap.appendChild(buildFilterBar(data.properties));
        // Restore filter UI state to match preserved activeFilters
        restoreFilterUI();
        wrap.appendChild(buildCmaButton());
        wrap.appendChild(buildSelectionBar());
        wrap.appendChild(buildSortBar(data.properties.length, appTotalCount));
    }

    const cardsWrap = document.createElement('div');
    cardsWrap.id = 'cards-container';
    wrap.appendChild(cardsWrap);

    if (data.properties.length === 0) {
        cardsWrap.innerHTML = `
            <div class="alert a-empty">
                <div style="font-size:2rem;margin-bottom:10px">🔍</div>
                <strong>No ${esc(data.statusLabel)} listings found in ${esc(data.geoScope)}.</strong><br>
                <span style="font-size:.82rem;margin-top:8px;display:block">
                    Try a larger radius, different statuses${data.hasClosed ? ', or a wider date range' : ''}.
                </span>
            </div>`;
        return;
    }

    applyFiltersAndRender();
}

// ── Error helpers ────────────────────────────────────────────────
function showError(msg) {
    clearError();
    const el = document.createElement('div');
    el.id = 'search-error';
    el.className = 'alert a-err';
    el.textContent = msg;
    document.querySelector('.container')?.prepend(el);
}
function clearError() {
    document.getElementById('search-error')?.remove();
}

// ── Utilities ────────────────────────────────────────────────────
function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
}
function fmtNum(n) { return Number(n).toLocaleString(); }
function fmtDate(str) {
    if (!str) return '—';
    try { return new Date(str).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }); }
    catch(_) { return str; }
}
function highlightCard(el) {
    document.querySelectorAll('.p-card.highlighted').forEach(c => c.classList.remove('highlighted'));
    if (el instanceof HTMLElement) { el.classList.add('highlighted'); el.scrollIntoView({ behavior:'smooth', block:'nearest' }); }
}
window.highlightCard = function(idx) {
    const cards = document.querySelectorAll('.p-card');
    if (cards[idx]) highlightCard(cards[idx]);
};
