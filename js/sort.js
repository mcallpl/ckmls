/* ============================================================
   sort.js — Sort bar, sort logic, and result count display
   ============================================================ */

let currentSort    = 'newest';
let currentSortDir = 'desc';

function buildSortBar(count, totalCount) {
    const bar = document.createElement('div');
    bar.className = 'sort-bar';
    bar.id = 'sort-bar';

    const sorts = [
        { key:'status',   label:'Status' },
        { key:'newest',   label:'Date' },
        { key:'price',    label:'Price' },
        { key:'distance', label:'📍 Distance' },
        { key:'dom',      label:'Days on Mkt' },
        { key:'sqft',     label:'Sq Ft' },
        { key:'year',     label:'Year Built' },
    ];

    const labelEl = document.createElement('span');
    labelEl.className = 'sort-label';
    labelEl.textContent = 'Sort:';
    bar.appendChild(labelEl);

    sorts.forEach(s => {
        const btn = document.createElement('button');
        const isActive = s.key === currentSort;
        btn.className   = 'sort-btn' + (isActive ? ' active sort-' + currentSortDir : '');
        btn.dataset.sort = s.key;
        btn.textContent  = s.label + (isActive ? (currentSortDir === 'asc' ? ' ↑' : ' ↓') : '');

        btn.addEventListener('click', () => {
            if (currentSort === s.key) {
                currentSortDir = currentSortDir === 'desc' ? 'asc' : 'desc';
            } else {
                currentSort    = s.key;
                currentSortDir = 'desc';
            }
            bar.querySelectorAll('.sort-btn').forEach(b => {
                const k   = b.dataset.sort;
                const def = sorts.find(x => x.key === k);
                if (!def) return;
                b.classList.remove('active','sort-asc','sort-desc');
                if (k === currentSort) {
                    b.classList.add('active','sort-' + currentSortDir);
                    b.textContent = def.label + (currentSortDir === 'asc' ? ' ↑' : ' ↓');
                } else {
                    b.textContent = def.label;
                }
            });
            if (appData) applyFiltersAndRender();
        });
        bar.appendChild(btn);
    });

    return bar;
}

function updateResultCount(el, showing, total) {
    if (!el) return;
    el.innerHTML = `<strong>${showing.toLocaleString()}</strong> home${showing !== 1 ? 's' : ''}`;
}

// Status sort order: Active first, then the pipeline, then closed/dead
const _statusOrder = {
    'Active': 0, 'Coming Soon': 1, 'Active Under Contract': 2,
    'Pending': 3, 'Closed': 4, 'Canceled': 5, 'Expired': 6,
};

function getSortedProperties(props, sortKey) {
    const sorted = [...props];
    const dir    = currentSortDir === 'asc' ? 1 : -1;
    switch (sortKey) {
        case 'status':
            return sorted.sort((a,b) => {
                const sa = _statusOrder[a.StandardStatus] ?? 9;
                const sb = _statusOrder[b.StandardStatus] ?? 9;
                if (sa !== sb) return dir * (sa - sb);
                // Within same status, sort by price descending
                const pa = a.ClosePrice || a.ListPrice || 0;
                const pb = b.ClosePrice || b.ListPrice || 0;
                return pb - pa;
            });
        case 'price':    return sorted.sort((a,b) => dir * ((a.ListPrice||a.ClosePrice||0) - (b.ListPrice||b.ClosePrice||0)));
        case 'distance': return sorted.sort((a,b) => dir * ((a._distance??Infinity) - (b._distance??Infinity)));
        case 'dom':      return sorted.sort((a,b) => dir * ((a.DaysOnMarket||a.CumulativeDaysOnMarket||0) - (b.DaysOnMarket||b.CumulativeDaysOnMarket||0)));
        case 'sqft':     return sorted.sort((a,b) => dir * ((a.LivingArea||0) - (b.LivingArea||0)));
        case 'year':     return sorted.sort((a,b) => dir * ((a.YearBuilt||0) - (b.YearBuilt||0)));
        case 'newest':
        default:         return sorted.sort((a,b) => dir * (new Date(b.ModificationTimestamp||0) - new Date(a.ModificationTimestamp||0)));
    }
}
