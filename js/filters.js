/* ============================================================
   filters.js — Filter bar, state, and applyFilters logic
   ============================================================ */

let activeFilters = {
    beds:'any', baths:'any', types:[],
    minPrice:'', maxPrice:'',
    minSqft:'', maxSqft:'',
    minLot:'', maxLot:'',
    rental:'exclude',
};

function resetFilters() {
    activeFilters = { beds:'any', baths:'any', types:[], minPrice:'', maxPrice:'', minSqft:'', maxSqft:'', minLot:'', maxLot:'', rental:'exclude' };
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

    const builtInTypes = ['SFR','Single Family','SingleFamilyResidence','Single Family Residence',
                          'Residential','Condo','Condominium','Townhouse','Townhome',
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
                    <div class="filter-label">Rental / Lease</div>
                    <select class="filter-select" id="f_rental">
                        <option value="exclude" selected>Don't show rental/lease</option>
                        <option value="include">Include rental/lease</option>
                        <option value="only">Show only rental/lease</option>
                    </select>
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
        const filterKey = group.dataset.filter;
        group.querySelectorAll('.fpill').forEach(btn => {
            btn.addEventListener('click', () => {
                if (filterKey === 'type') {
                    // Multi-select for home types
                    const val = btn.dataset.val;
                    if (val === 'any') {
                        // "Any" clears all selections
                        group.querySelectorAll('.fpill').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        activeFilters.types = [];
                    } else {
                        // Toggle this type
                        group.querySelector('[data-val="any"]').classList.remove('active');
                        btn.classList.toggle('active');
                        const selected = [...group.querySelectorAll('.fpill.active')]
                            .map(b => b.dataset.val).filter(v => v !== 'any');
                        activeFilters.types = selected;
                        // If nothing selected, reactivate "Any"
                        if (selected.length === 0) {
                            group.querySelector('[data-val="any"]').classList.add('active');
                        }
                    }
                } else {
                    // Single-select for beds/baths
                    group.querySelectorAll('.fpill').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    activeFilters[filterKey] = btn.dataset.val;
                }
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

    // Rental dropdown
    const rentalSel = wrap.querySelector('#f_rental');
    rentalSel.value = activeFilters.rental;
    rentalSel.addEventListener('change', () => {
        activeFilters.rental = rentalSel.value;
        showResetIfNeeded();
        applyFiltersAndRender();
    });

    // Reset button
    wrap.querySelector('#filterReset').addEventListener('click', () => {
        resetFilters();
        wrap.querySelectorAll('.filter-pills .fpill').forEach(b => {
            b.classList.toggle('active', b.dataset.val === 'any');
        });
        wrap.querySelectorAll('.filter-input').forEach(i => i.value = '');
        const rentalReset = wrap.querySelector('#f_rental');
        if (rentalReset) rentalReset.value = 'exclude';
        wrap.querySelector('#filterReset').style.display = 'none';
        applyFiltersAndRender();
    });

    return wrap;
}

function showResetIfNeeded() {
    const btn = document.getElementById('filterReset');
    if (!btn) return;
    const dirty = activeFilters.beds !== 'any' || activeFilters.baths !== 'any' ||
                  activeFilters.types.length > 0 || activeFilters.minPrice ||
                  activeFilters.maxPrice || activeFilters.minSqft || activeFilters.maxSqft ||
                  activeFilters.minLot || activeFilters.maxLot ||
                  activeFilters.rental !== 'exclude';
    btn.style.display = dirty ? 'inline-flex' : 'none';
}

// ── Restore filter UI to match activeFilters state ───────────────
function restoreFilterUI() {
    // Beds
    const bedsGroup = document.querySelector('[data-filter="beds"]');
    if (bedsGroup) {
        bedsGroup.querySelectorAll('.fpill').forEach(b => {
            b.classList.toggle('active', b.dataset.val === activeFilters.beds);
        });
    }
    // Baths
    const bathsGroup = document.querySelector('[data-filter="baths"]');
    if (bathsGroup) {
        bathsGroup.querySelectorAll('.fpill').forEach(b => {
            b.classList.toggle('active', b.dataset.val === activeFilters.baths);
        });
    }
    // Types
    const typeGroup = document.querySelector('[data-filter="type"]');
    if (typeGroup) {
        if (activeFilters.types.length === 0) {
            typeGroup.querySelectorAll('.fpill').forEach(b => {
                b.classList.toggle('active', b.dataset.val === 'any');
            });
        } else {
            typeGroup.querySelectorAll('.fpill').forEach(b => {
                if (b.dataset.val === 'any') b.classList.remove('active');
                else b.classList.toggle('active', activeFilters.types.includes(b.dataset.val));
            });
        }
    }
    // Text inputs
    const inputMap = { f_minPrice:'minPrice', f_maxPrice:'maxPrice', f_minSqft:'minSqft', f_maxSqft:'maxSqft', f_minLot:'minLot', f_maxLot:'maxLot' };
    Object.entries(inputMap).forEach(([id, key]) => {
        const el = document.getElementById(id);
        if (el && activeFilters[key]) el.value = activeFilters[key];
    });
    // Rental dropdown
    const rentalEl = document.getElementById('f_rental');
    if (rentalEl) rentalEl.value = activeFilters.rental;
    // Show reset button if needed
    showResetIfNeeded();
}

// ── Apply filters + sort, then re-render ─────────────────────────
function applyFiltersAndRender() {
    if (!appData) return;
    const filtered = applyFilters(appData.properties);
    const sorted   = getSortedProperties(filtered, currentSort);

    const countEl = document.getElementById('result-count');
    if (countEl) updateResultCount(countEl, filtered.length, appTotalCount);

    if (typeof window.updateMapMarkers === 'function') {
        window.updateMapMarkers(sorted);
    }
    renderCards(sorted);
    updateSelectAllToggle();
}

// ── Filter logic ─────────────────────────────────────────────────
function applyFilters(props) {
    // Spatial filter (dragged radius or polygon)
    if (window.spatialFilter) {
        props = props.filter(window.spatialFilter);
    }
    return props.filter(p => {
        const price    = p.ListPrice || p.ClosePrice || 0;
        const beds     = p.BedroomsTotal        || 0;
        const baths    = p.BathroomsTotalInteger || 0;
        const sqft     = p.LivingArea           || 0;
        const lot      = p.LotSizeAcres         || 0;
        const pSubType = (p.PropertySubType || '').toLowerCase().trim();
        const pType    = (p.PropertyType    || '').toLowerCase().trim();

        // Rental / Lease filter
        if (activeFilters.rental !== 'include') {
            const rentalTerms = ['lease','rental','rent'];
            const isRental = rentalTerms.some(x => pSubType.includes(x) || pType.includes(x));
            if (activeFilters.rental === 'exclude' && isRental) return false;
            if (activeFilters.rental === 'only' && !isRental) return false;
        }

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

        // Type — multi-select, strict two-field matching
        if (activeFilters.types.length > 0) {
            // Non-SFR types that must be excluded from Single Family results
            const nonSfr = ['income','multi','duplex','triplex','quadruplex','apartment',
                             'commercial','lease','condo','condominium','mixed'];

            const passRules = {
                'sfr': () => {
                    if (nonSfr.some(x => pSubType.includes(x) || pType.includes(x))) return false;
                    const ok = ['single family','singlefamily','singlefamilyresidence','sfr','single-family'];
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

            // Property must match at least one of the selected types
            const matchesAny = activeFilters.types.some(sel => {
                const s = sel.toLowerCase();
                if (passRules[s]) return passRules[s]();
                const raw = s.replace(/-/g,' ');
                return pSubType.includes(raw) || pType.includes(raw);
            });
            if (!matchesAny) return false;
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
