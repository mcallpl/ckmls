/* ============================================================
   sort.js — Sort bar and sort logic
   ============================================================ */

let currentSort    = 'newest';
let currentSortDir = 'desc';

function buildSortBar(count) {
    const bar = document.createElement('div');
    bar.className = 'sort-bar';
    bar.id = 'sort-bar';

    const sorts = [
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

    const countEl = document.createElement('span');
    countEl.className = 'result-count';
    countEl.innerHTML = `<strong>${count}</strong> listing${count !== 1 ? 's' : ''}`;
    bar.appendChild(countEl);
    return bar;
}

function getSortedProperties(props, sortKey) {
    const sorted = [...props];
    const dir    = currentSortDir === 'asc' ? 1 : -1;
    switch (sortKey) {
        case 'price':    return sorted.sort((a,b) => dir * ((a.ListPrice||a.ClosePrice||0) - (b.ListPrice||b.ClosePrice||0)));
        case 'distance': return sorted.sort((a,b) => dir * ((a._distance??Infinity) - (b._distance??Infinity)));
        case 'dom':      return sorted.sort((a,b) => dir * ((a.DaysOnMarket||a.CumulativeDaysOnMarket||0) - (b.DaysOnMarket||b.CumulativeDaysOnMarket||0)));
        case 'sqft':     return sorted.sort((a,b) => dir * ((a.LivingArea||0) - (b.LivingArea||0)));
        case 'year':     return sorted.sort((a,b) => dir * ((a.YearBuilt||0) - (b.YearBuilt||0)));
        case 'newest':
        default:         return sorted.sort((a,b) => dir * (new Date(b.ModificationTimestamp||0) - new Date(a.ModificationTimestamp||0)));
    }
}
