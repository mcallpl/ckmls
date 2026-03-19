/* ============================================================
   cards.js — Property card rendering
   ============================================================ */

const _photoStore = {};

function renderCards(props) {
    const wrap = document.getElementById('cards-container');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!props.length) {
        const empty = document.createElement('div');
        empty.className = 'alert a-empty';
        empty.innerHTML = '<div style="font-size:2rem;margin-bottom:10px">🔍</div><strong>No listings match your current filters.</strong><br><span style="font-size:.82rem;margin-top:8px;display:block">Try adjusting your criteria above.</span>';
        wrap.appendChild(empty);
        return;
    }
    props.forEach((prop, idx) => wrap.appendChild(buildCard(prop, idx)));
}

// ── Photo section ─────────────────────────────────────────────────
function buildPhotoSection(prop, cardIdx) {
    const outer = document.createElement('div');
    outer.style.cssText = 'position:relative;width:100%;';

    // Card number badge
    const num = document.createElement('div');
    num.className = 'card-num';
    num.textContent = cardIdx + 1;
    outer.appendChild(num);

    const photos = prop._photos && prop._photos.length ? prop._photos
                 : prop._photo ? [prop._photo] : [];

    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;width:100%;height:300px;overflow:hidden;background:#16181f;';
    outer.appendChild(wrap);

    if (!photos.length) {
        wrap.style.cssText += 'display:flex;flex-direction:column;align-items:center;justify-content:center;';
        const icon = document.createElement('div');
        icon.textContent = '🏠';
        icon.style.cssText = 'font-size:2.5rem;opacity:.25;';
        wrap.appendChild(icon);
        return outer;
    }

    // Image
    const img = document.createElement('img');
    img.src = photos[0];
    img.alt = 'Property photo';
    img.loading = 'lazy';
    img.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;display:block;';
    wrap.appendChild(img);

    if (photos.length < 2) return outer;

    // Store for cycling
    const uid = 'p' + Math.random().toString(36).slice(2,8);
    _photoStore[uid] = { photos, idx: 0, img };

    // Prev button
    const prev = document.createElement('button');
    prev.innerHTML = '&#8592;';
    prev.title = 'Previous photo';
    prev.style.cssText = 'position:absolute;top:0;left:0;bottom:0;width:52px;background:rgba(0,0,0,.55);color:#fff;border:none;font-size:2rem;cursor:pointer;z-index:10;padding:0;display:flex;align-items:center;justify-content:center;transition:background .15s;';
    prev.onmouseenter = function(){ this.style.background='rgba(0,0,0,.82)'; };
    prev.onmouseleave = function(){ this.style.background='rgba(0,0,0,.55)'; };
    prev.onclick = function(e) { e.stopPropagation(); photoStep(uid, -1); };
    wrap.appendChild(prev);

    // Next button
    const next = document.createElement('button');
    next.innerHTML = '&#8594;';
    next.title = 'Next photo';
    next.style.cssText = 'position:absolute;top:0;right:0;bottom:0;width:52px;background:rgba(0,0,0,.55);color:#fff;border:none;font-size:2rem;cursor:pointer;z-index:10;padding:0;display:flex;align-items:center;justify-content:center;transition:background .15s;';
    next.onmouseenter = function(){ this.style.background='rgba(0,0,0,.82)'; };
    next.onmouseleave = function(){ this.style.background='rgba(0,0,0,.55)'; };
    next.onclick = function(e) { e.stopPropagation(); photoStep(uid, 1); };
    wrap.appendChild(next);

    // Counter
    const ctr = document.createElement('div');
    ctr.style.cssText = 'position:absolute;bottom:9px;right:10px;background:rgba(0,0,0,.65);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;z-index:10;pointer-events:none;font-family:Syne,sans-serif;';
    ctr.innerHTML = '<span class="pc-cur">1</span> / ' + photos.length;
    _photoStore[uid].counter = ctr;
    wrap.appendChild(ctr);

    return outer;
}

function photoStep(uid, dir) {
    const s = _photoStore[uid];
    if (!s) return;
    s.idx = (s.idx + dir + s.photos.length) % s.photos.length;
    s.img.src = s.photos[s.idx];
    const cur = s.counter ? s.counter.querySelector('.pc-cur') : null;
    if (cur) cur.textContent = s.idx + 1;
}

// ── Card ─────────────────────────────────────────────────────────
function buildCard(prop, idx) {
    const el = document.createElement('div');
    el.className = 'p-card';

    const isClosed = prop.StandardStatus === 'Closed';
    const price    = isClosed ? (prop.ClosePrice || prop.ListPrice || 0) : (prop.ListPrice || 0);
    const priceStr = price ? '$' + fmtNum(price) : '—';
    const address  = [prop.StreetNumber, prop.StreetName, prop.UnitNumber ? '#'+prop.UnitNumber : ''].filter(Boolean).join(' ');
    const dom      = prop.DaysOnMarket ?? prop.CumulativeDaysOnMarket;
    const statusCls= 'b-' + (prop.StandardStatus || 'Unknown').replace(/ /g,'-');

    // Photo section (real DOM)
    el.appendChild(buildPhotoSection(prop, idx));

    // Card body (safe innerHTML — no photos here)
    const body = document.createElement('div');
    body.className = 'c-body';

    // Head row
    const head = document.createElement('div');
    head.className = 'c-head';
    head.innerHTML = '<div class="c-addr-text">' + esc(address) + '</div>'
                   + '<div class="c-price">' + esc(priceStr) + '</div>';
    body.appendChild(head);

    // Sub line
    const sub = document.createElement('div');
    sub.className = 'c-sub';
    sub.innerHTML = esc(prop.PropertySubType || prop.PropertyType || 'Residential')
        + (dom != null ? ' &nbsp;&middot;&nbsp; ' + dom + ' days on market' : '')
        + (prop._distance != null ? ' <span class="dist-pill">📍 ' + prop._distance.toFixed(2) + ' mi</span>' : '');
    body.appendChild(sub);

    // Status badge
    const badge = document.createElement('span');
    badge.className = 'badge ' + statusCls;
    badge.innerHTML = '<span class="b-dot"></span>' + esc(prop.StandardStatus || 'Unknown');
    body.appendChild(badge);

    // Sale strip (closed only)
    if (isClosed && prop.ClosePrice) {
        const diff = prop.ClosePrice - (prop.OriginalListPrice || prop.ListPrice || 0);
        const pct  = prop.OriginalListPrice ? ((diff / prop.OriginalListPrice) * 100).toFixed(1) : null;
        const strip = document.createElement('div');
        strip.className = 'sale-strip';
        strip.innerHTML = '<div class="ss-item"><div class="ss-lbl">Sold</div><div class="ss-val">' + fmtDate(prop.CloseDate) + '</div></div>'
            + '<div class="ss-item"><div class="ss-lbl">Sale Price</div><div class="ss-val">' + priceStr + '</div></div>'
            + (prop.ListPrice ? '<div class="ss-item"><div class="ss-lbl">List Price</div><div class="ss-val">$' + fmtNum(prop.ListPrice) + '</div></div>' : '')
            + (pct !== null ? '<div class="ss-item"><div class="ss-lbl">vs List</div><div class="ss-val" style="color:' + (diff>=0?'var(--green)':'var(--red)') + '">' + (diff>=0?'+':'') + pct + '%</div></div>' : '');
        body.appendChild(strip);
    }

    // Stats bar
    const stats = document.createElement('div');
    stats.className = 'stats';
    [
        [prop.BedroomsTotal ?? '—', 'Beds'],
        [prop.BathroomsTotalInteger ?? '—', 'Baths'],
        [prop.LivingArea ? fmtNum(prop.LivingArea) : '—', 'Sq Ft'],
        [prop.YearBuilt ?? '—', 'Built'],
        [dom ?? '—', 'DOM'],
        [prop.LotSizeAcres ? Number(prop.LotSizeAcres).toFixed(2) : '—', 'Acres'],
    ].forEach(([val, label]) => {
        const stat = document.createElement('div');
        stat.className = 'stat';
        stat.innerHTML = '<div class="sv">' + val + '</div><div class="sl">' + label + '</div>';
        stats.appendChild(stat);
    });
    body.appendChild(stats);

    // HOA
    if (prop.AssociationFee) {
        const sep = document.createElement('hr');
        sep.className = 'sep';
        body.appendChild(sep);
        const meta = document.createElement('div');
        meta.className = 'c-meta';
        meta.innerHTML = 'HOA: $' + fmtNum(prop.AssociationFee) + '/' + (prop.AssociationFeeFrequency || 'mo');
        body.appendChild(meta);
    }

    // Remarks
    if (prop.PublicRemarks) {
        const rem = document.createElement('div');
        rem.className = 'c-rem';
        rem.textContent = prop.PublicRemarks;
        body.appendChild(rem);
    }

    el.appendChild(body);

    el.addEventListener('click', () => {
        highlightCard(el);
        if (typeof window.highlightMapMarker === 'function') window.highlightMapMarker(idx);
    });

    return el;
}
