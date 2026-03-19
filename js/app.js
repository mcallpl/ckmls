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
    try { syncForm(); } catch(ex) { console.warn('syncForm error:', ex); }

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
        initMap(data.geocoded, data.properties);

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
        resetFilters();
        wrap.appendChild(buildFilterBar(data.properties));
        wrap.appendChild(buildCmaButton());
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
