/* ============================================================
   filters.js — Filter bar, state, and applyFilters logic
   ============================================================ */

let activeFilters = {
    beds:'any', baths:'any', type:'any',
    minPrice:'', maxPrice:'',
    minSqft:'', maxSqft:'',
    minLot:'', maxLot:'',
};

function resetFilters() {
    activeFilters = { beds:'any', baths:'any', type:'any', minPrice:'', maxPrice:'', minSqft:'', maxSqft:'', minLot:'', maxLot:'' };
}

// ── Build filter bar ─────────────────────────────────────────────
function buildFilterBar(properties) {
    // Dynamic types from results
    const types = [...new Set(properties
        .map(p => p.PropertySubType || p.PropertyType || '')
        .filter(Boolean))].sort();

    const prices = properties.map(p => p.ListPrice || p.ClosePrice || 0).filter(Boolean);
    const minP   = prices.length ? Math.min(...prices) : 0;
    const maxP   = prices.length ? Math.max(...prices) : 0;

    const builtInTypes = ['SFR','Single Family','Residential','Condo','Condominium','Townhouse','Townhome',
                          'Duplex','Multi-Family','ResidentialIncome'];
    const dynamicTypes = types.filter(t => !builtInTypes.some(b =>
        b.toLowerCase() === t.toLowerCase()
    ));

    const wrap = document.createElement('div');
    wrap.className = 'filter-bar';
    wrap.id = 'filter-bar';
    wrap.innerHTML = `
        <div class="filter-bar-title">
            <span>🎯 Narrow Results</span>
            <button class="filter-reset" id="filterReset" style="display:none">✕ Reset filters</button>
        </div>
        <div class="filter-rows">
            <div class="filter-row">
                <div class="filter-group">
                    <div class="filter-label">Bedrooms</div>
                    <div class="filter-pills" data-filter="beds">
                        <button class="fpill active" data-val="any">Any</button>
                        <button class="fpill" data-val="1">1</button>
                        <button class="fpill" data-val="2">2</button>
                        <button class="fpill" data-val="2+">2+</button>
                        <button class="fpill" data-val="3">3</button>
                        <button class="fpill" data-val="3+">3+</button>
                        <button class="fpill" data-val="4">4</button>
                        <button class="fpill" data-val="4+">4+</button>
                        <button class="fpill" data-val="5+">5+</button>
                    </div>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Bathrooms</div>
                    <div class="filter-pills" data-filter="baths">
                        <button class="fpill active" data-val="any">Any</button>
                        <button class="fpill" data-val="1">1</button>
                        <button class="fpill" data-val="1+">1+</button>
                        <button class="fpill" data-val="2">2</button>
                        <button class="fpill" data-val="2+">2+</button>
                        <button class="fpill" data-val="3">3</button>
                        <button class="fpill" data-val="3+">3+</button>
                        <button class="fpill" data-val="4+">4+</button>
                    </div>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group wide">
                    <div class="filter-label">Home Type</div>
                    <div class="filter-pills" data-filter="type">
                        <button class="fpill active" data-val="any">Any</button>
                        <button class="fpill" data-val="SFR">Single Family</button>
                        <button class="fpill" data-val="Condo">Condo</button>
                        <button class="fpill" data-val="Townhouse">Townhouse</button>
                        <button class="fpill" data-val="Multi">Multi-Family</button>
                        ${dynamicTypes.map(t => `<button class="fpill" data-val="${esc(t)}">${esc(t)}</button>`).join('')}
                    </div>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <div class="filter-label">Min Price</div>
                    <div class="filter-input-wrap">
                        <span class="filter-input-prefix">$</span>
                        <input type="text" class="filter-input" id="f_minPrice" placeholder="${minP ? fmtNum(minP) : '0'}" inputmode="numeric">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Max Price</div>
                    <div class="filter-input-wrap">
                        <span class="filter-input-prefix">$</span>
                        <input type="text" class="filter-input" id="f_maxPrice" placeholder="${maxP ? fmtNum(maxP) : 'No limit'}" inputmode="numeric">
                    </div>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <div class="filter-label">Min Sq Ft</div>
                    <div class="filter-input-wrap">
                        <input type="text" class="filter-input" id="f_minSqft" placeholder="Any" inputmode="numeric">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Max Sq Ft</div>
                    <div class="filter-input-wrap">
                        <input type="text" class="filter-input" id="f_maxSqft" placeholder="Any" inputmode="numeric">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Min Lot (ac)</div>
                    <div class="filter-input-wrap">
                        <input type="text" class="filter-input" id="f_minLot" placeholder="Any" inputmode="decimal">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Max Lot (ac)</div>
                    <div class="filter-input-wrap">
                        <input type="text" class="filter-input" id="f_maxLot" placeholder="Any" inputmode="decimal">
                    </div>
                </div>
            </div>
        </div>`;

    // Pill clicks
    wrap.querySelectorAll('.filter-pills').forEach(group => {
        group.querySelectorAll('.fpill').forEach(btn => {
            btn.addEventListener('click', () => {
                group.querySelectorAll('.fpill').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeFilters[group.dataset.filter] = btn.dataset.val;
                showResetIfNeeded();
                applyFiltersAndRender();
            });
        });
    });

    // Text inputs (debounced)
    const inputMap = { f_minPrice:'minPrice', f_maxPrice:'maxPrice', f_minSqft:'minSqft', f_maxSqft:'maxSqft', f_minLot:'minLot', f_maxLot:'maxLot' };
    let inputTimer;
    wrap.querySelectorAll('.filter-input').forEach(inp => {
        inp.addEventListener('input', () => {
            clearTimeout(inputTimer);
            inputTimer = setTimeout(() => {
                activeFilters[inputMap[inp.id]] = inp.value.replace(/[^0-9.]/g,'');
                showResetIfNeeded();
                applyFiltersAndRender();
            }, 350);
        });
    });

    // Reset button
    wrap.querySelector('#filterReset').addEventListener('click', () => {
        resetFilters();
        wrap.querySelectorAll('.filter-pills .fpill').forEach(b => {
            b.classList.toggle('active', b.dataset.val === 'any');
        });
        wrap.querySelectorAll('.filter-input').forEach(i => i.value = '');
        wrap.querySelector('#filterReset').style.display = 'none';
        applyFiltersAndRender();
    });

    return wrap;
}

function showResetIfNeeded() {
    const btn = document.getElementById('filterReset');
    if (!btn) return;
    const dirty = activeFilters.beds !== 'any' || activeFilters.baths !== 'any' ||
                  activeFilters.type !== 'any' || activeFilters.minPrice ||
                  activeFilters.maxPrice || activeFilters.minSqft || activeFilters.maxSqft ||
                  activeFilters.minLot || activeFilters.maxLot;
    btn.style.display = dirty ? 'inline-flex' : 'none';
}

// ── Apply filters + sort, then re-render ─────────────────────────
function applyFiltersAndRender() {
    if (!appData) return;
    const filtered = applyFilters(appData.properties);
    const sorted   = getSortedProperties(filtered, currentSort);

    const countSpan = document.querySelector('.result-count');
    if (countSpan) countSpan.innerHTML =
        `<strong>${filtered.length}</strong> listing${filtered.length !== 1 ? 's' : ''}`;

    if (typeof window.updateMapMarkers === 'function') {
        window.updateMapMarkers(sorted);
    }
    renderCards(sorted);
}

// ── Filter logic ─────────────────────────────────────────────────
function applyFilters(props) {
    return props.filter(p => {
        const price    = p.ListPrice || p.ClosePrice || 0;
        const beds     = p.BedroomsTotal        || 0;
        const baths    = p.BathroomsTotalInteger || 0;
        const sqft     = p.LivingArea           || 0;
        const lot      = p.LotSizeAcres         || 0;
        const pSubType = (p.PropertySubType || '').toLowerCase().trim();
        const pType    = (p.PropertyType    || '').toLowerCase().trim();

        // Beds
        if (activeFilters.beds !== 'any') {
            const v = activeFilters.beds;
            if (v.endsWith('+')) { if (beds < parseInt(v)) return false; }
            else                 { if (beds !== parseInt(v)) return false; }
        }

        // Baths
        if (activeFilters.baths !== 'any') {
            const v = activeFilters.baths;
            if (v.endsWith('+')) { if (baths < parseInt(v)) return false; }
            else                 { if (baths !== parseInt(v)) return false; }
        }

        // Type — strict two-field matching
        if (activeFilters.type !== 'any') {
            const sel = activeFilters.type.toLowerCase();

            // Non-SFR types that must be excluded from Single Family results
            const nonSfr = ['income','multi','duplex','triplex','quadruplex','apartment',
                             'commercial','lease','condo','condominium','mixed'];

            const passRules = {
                'sfr': () => {
                    if (nonSfr.some(x => pSubType.includes(x) || pType.includes(x))) return false;
                    const ok = ['single family','singlefamily','sfr','single-family'];
                    // PropertyType "Residential" alone is too broad — must have SFR SubType OR explicit SFR type
                    if (pSubType) return ok.some(x => pSubType.includes(x));
                    return ok.some(x => pType.includes(x)) || pType === 'residential';
                },
                'condo': () => {
                    const ok = ['condo','condominium'];
                    return ok.some(x => pSubType.includes(x) || pType.includes(x));
                },
                'townhouse': () => {
                    const ok = ['townhouse','townhome','town house'];
                    return ok.some(x => pSubType.includes(x) || pType.includes(x));
                },
                'multi': () => {
                    const ok = ['multi','duplex','triplex','quadruplex','fourplex','income','apartment'];
                    return ok.some(x => pSubType.includes(x) || pType.includes(x));
                },
            };

            if (passRules[sel]) {
                if (!passRules[sel]()) return false;
            } else {
                const raw = sel.replace(/-/g,' ');
                if (!pSubType.includes(raw) && !pType.includes(raw)) return false;
            }
        }

        // Price
        if (activeFilters.minPrice && price < parseFloat(activeFilters.minPrice)) return false;
        if (activeFilters.maxPrice && price > parseFloat(activeFilters.maxPrice)) return false;

        // Sqft
        if (activeFilters.minSqft && sqft < parseFloat(activeFilters.minSqft)) return false;
        if (activeFilters.maxSqft && sqft > parseFloat(activeFilters.maxSqft)) return false;

        // Lot
        if (activeFilters.minLot && lot < parseFloat(activeFilters.minLot)) return false;
        if (activeFilters.maxLot && lot > parseFloat(activeFilters.maxLot)) return false;

        return true;
    });
}
