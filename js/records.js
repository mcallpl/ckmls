/* ============================================================
   records.js — Public records section (ATTOM data)
   ============================================================ */

function buildRecordsSection(data) {
    const outer = document.createElement('div');

    // ── Street View panorama above records ──
    if (data.geocoded && data.geocoded.lat && data.geocoded.lng) {
        const loc = new google.maps.LatLng(parseFloat(data.geocoded.lat), parseFloat(data.geocoded.lng));
        const svContainer = document.createElement('div');
        svContainer.id = 'streetview-container';
        svContainer.innerHTML = `
            <div class="streetview-header">
                <span class="streetview-icon">🏠</span>
                <span class="streetview-label">Street View</span>
            </div>
            <div id="streetview-pano"></div>
            <div id="streetview-nodata" class="streetview-nodata" style="display:none">
                No Street View available for this location
            </div>`;
        outer.appendChild(svContainer);
        // Render the pano after element is in the DOM
        setTimeout(() => {
            if (typeof renderStreetViewPano === 'function') {
                renderStreetViewPano(loc, svContainer);
            }
        }, 100);
    } else if (window._streetViewContainer) {
        outer.appendChild(window._streetViewContainer);
    }

    const wrap = document.createElement('div');
    wrap.className = 'records-section';

    const attom   = data.publicRecords?.attom   || null;
    const hist    = data.publicRecords?.history  || [];
    const links   = data.publicRecords?.links    || {};
    const address = data.publicRecords?.address  || data.geocoded?.display_name || '';

    // Build a quick stats line from ATTOM data
    const statParts = [];
    if (attom && !attom._error) {
        if (attom.bedrooms)   statParts.push(attom.bedrooms + ' bd');
        if (attom.bathrooms)  statParts.push(attom.bathrooms + ' ba');
        if (attom.gross_sqft) statParts.push(fmtNum(attom.gross_sqft) + ' sqft');
        if (attom.year_built) statParts.push('Built ' + attom.year_built);
        if (attom.lot_size_sqft) statParts.push(fmtNum(attom.lot_size_sqft) + ' sqft lot');
    }
    const statsLine = statParts.length
        ? `<p class="records-stats">${statParts.map(s => `<span>${esc(s)}</span>`).join('<span class="records-stats-sep">·</span>')}</p>`
        : '';

    wrap.innerHTML = `
        <div class="records-header">
            <div class="records-icon">📋</div>
            <div>
                <h2>${esc(address)}</h2>
                <p>Ownership, purchase history, loans &amp; public records</p>
                ${statsLine}
            </div>
        </div>`;

    const body = document.createElement('div');
    body.className = 'records-body';

    body.appendChild(createEl(buildOwnerBlock(attom)));
    if (attom && !attom._error) {
        const purchaseHtml = buildPurchaseBlock(attom);
        if (purchaseHtml) body.appendChild(createEl(purchaseHtml));
        const loansHtml = buildLoansBlock(attom);
        if (loansHtml) body.appendChild(createEl(loansHtml));
        const taxHtml = buildTaxBlock(attom);
        if (taxHtml) body.appendChild(createEl(taxHtml));
        const detailsHtml = buildPropertyDetailsBlock(attom);
        if (detailsHtml) body.appendChild(createEl(detailsHtml));
    }
    if (hist.length) body.appendChild(createEl(buildHistoryBlock(hist)));
    const linksHtml = buildLinksBlock(links);
    if (linksHtml) body.appendChild(createEl(linksHtml));

    wrap.appendChild(body);
    outer.appendChild(wrap);
    return outer;
}

function createEl(html) {
    const d = document.createElement('div');
    d.innerHTML = html;
    return d.firstElementChild || d;
}

function buildOwnerBlock(attom) {
    if (!attom || attom._error === 'NO_KEY') {
        return `<div class="attom-note">🔐 Add your <a href="https://api.developer.attomdata.com/" target="_blank" style="color:var(--gold)">ATTOM API key</a> to <code>config.php</code> to unlock owner data.</div>`;
    }
    if (attom._error) {
        return `<div class="attom-note" style="border-color:rgba(255,92,92,.3);background:rgba(255,92,92,.06);color:#fca5a5">⚠️ ${esc(attom._error)}</div>`;
    }

    const owners = [attom.owner1, attom.owner2, attom.owner3].filter(Boolean);
    if (!owners.length) {
        return `<div class="attom-note" style="color:var(--muted)">No owner information returned for this address.</div>`;
    }

    const occupiedBadge = attom.owner_occupied
        ? `<span class="owner-badge owner-occ">Owner Occupied</span>`
        : (attom.absentee_status ? `<span class="owner-badge owner-abs">Absentee Owner</span>` : '');

    const individuals = owners.filter(n => !n.toUpperCase().match(/TRUST|LLC|INC|CORP/));
    const entities    = owners.filter(n =>  n.toUpperCase().match(/TRUST|LLC|INC|CORP/));

    return `
        <div class="rec-group">
            <div class="rec-group-title">👤 Owner Information</div>
            <div class="owner-card">
                <div class="owner-avatar">👤</div>
                <div class="owner-info">
                    <div class="owner-names">
                        ${individuals.map(n => `<h3>${esc(n.trim())}</h3>`).join('')}
                        ${entities.map(n => `<p class="owner-entity">🏛️ ${esc(n.trim())}</p>`).join('')}
                    </div>
                    ${occupiedBadge}
                    ${attom.mailing_address ? `<p class="owner-mail">📫 ${esc(attom.mailing_address)}</p>` : ''}
                </div>
            </div>
        </div>`;
}

function buildPurchaseBlock(attom) {
    if (!attom || attom._error) return '';
    const rows = [];
    if (attom.last_sale_date)   rows.push(['Purchase Date',    fmtDate(attom.last_sale_date)]);
    if (attom.last_sale_price)  rows.push(['Purchase Price',   '$' + fmtNum(attom.last_sale_price)]);
    if (attom.last_doc_type)    rows.push(['Document Type',    attom.last_doc_type]);
    if (attom.last_trans_type)  rows.push(['Transaction Type', attom.last_trans_type]);
    if (attom.prior_sale_date)  rows.push(['Prior Sale Date',  fmtDate(attom.prior_sale_date)]);
    if (attom.prior_sale_price) rows.push(['Prior Sale Price', '$' + fmtNum(attom.prior_sale_price)]);
    if (!rows.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">🏷️ Purchase History</div>
            <div class="info-grid">${rows.map(([l,v]) => `
                <div class="info-cell">
                    <span class="info-cell-label">${esc(l)}</span>
                    <span class="info-cell-value highlight">${esc(String(v))}</span>
                </div>`).join('')}
            </div>
        </div>`;
}

function buildLoansBlock(attom) {
    if (!attom || !attom.loans?.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">🏦 Loans on Record</div>
            <div class="loans-list">${attom.loans.map(loan => `
                <div class="loan-card">
                    <div class="loan-pos">${esc(loan.position || 'Loan')}</div>
                    <div class="loan-amount">${loan.amount ? '$' + fmtNum(loan.amount) : '—'}</div>
                    <div class="loan-meta">
                        ${loan.lender    ? `<span>Lender: ${esc(loan.lender)}</span>` : ''}
                        ${loan.type      ? `<span>Type: ${esc(loan.type)}</span>` : ''}
                        ${loan.date      ? `<span>Recorded: ${esc(fmtDate(loan.date))}</span>` : ''}
                        ${loan.rate_type ? `<span>Rate: ${esc(loan.rate_type)}</span>` : ''}
                    </div>
                </div>`).join('')}
            </div>
        </div>`;
}

function buildTaxBlock(attom) {
    if (!attom || attom._error) return '';
    const rows = [];
    if (attom.apn)            rows.push(['APN / Parcel #',  attom.apn]);
    if (attom.assessed_total) rows.push(['Assessed Value',  '$' + fmtNum(attom.assessed_total)]);
    if (attom.assessed_land)  rows.push(['Land Value',      '$' + fmtNum(attom.assessed_land)]);
    if (attom.assessed_impr)  rows.push(['Improvement',     '$' + fmtNum(attom.assessed_impr)]);
    if (attom.tax_amount)     rows.push(['Annual Tax',      '$' + fmtNum(attom.tax_amount) + (attom.tax_year ? ' (' + attom.tax_year + ')' : '')]);
    if (!rows.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">💰 Assessment &amp; Tax</div>
            <div class="info-grid">${rows.map(([l,v]) => `
                <div class="info-cell">
                    <span class="info-cell-label">${esc(l)}</span>
                    <span class="info-cell-value">${esc(String(v))}</span>
                </div>`).join('')}
            </div>
        </div>`;
}

function buildPropertyDetailsBlock(attom) {
    if (!attom || attom._error) return '';
    const rows = [];
    if (attom.land_use)    rows.push(['Land Use',   attom.land_use]);
    if (attom.lot_sqft)    rows.push(['Lot Sq Ft',  fmtNum(attom.lot_sqft)]);
    if (attom.stories)     rows.push(['Stories',    attom.stories]);
    if (attom.bedrooms)    rows.push(['Bedrooms',   attom.bedrooms]);
    if (attom.bathrooms)   rows.push(['Bathrooms',  attom.bathrooms]);
    if (attom.year_built)  rows.push(['Year Built', attom.year_built]);
    if (attom.gross_sqft)  rows.push(['Gross Sq Ft',fmtNum(attom.gross_sqft)]);
    if (attom.apn)         rows.push(['APN',        attom.apn]);
    if (!rows.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">🏠 Property Details</div>
            <div class="info-grid">${rows.map(([l,v]) => `
                <div class="info-cell">
                    <span class="info-cell-label">${esc(l)}</span>
                    <span class="info-cell-value">${esc(String(v))}</span>
                </div>`).join('')}
            </div>
        </div>`;
}

function buildHistoryBlock(hist) {
    if (!hist?.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">📊 MLS History</div>
            <table class="history-table">
                <thead><tr><th>Status</th><th>Price</th><th>Close Date</th><th>DOM</th></tr></thead>
                <tbody>${hist.map(h => `
                    <tr>
                        <td>${esc(h.StandardStatus||'')}</td>
                        <td>${h.ClosePrice ? '$'+fmtNum(h.ClosePrice) : (h.ListPrice ? '$'+fmtNum(h.ListPrice) : '—')}</td>
                        <td>${h.CloseDate ? fmtDate(h.CloseDate) : '—'}</td>
                        <td style="font-size:.75rem;color:var(--muted)">${h.DaysOnMarket ?? '—'}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function buildLinksBlock(links) {
    if (!links || !Object.keys(links).length) return '';
    const entries = Object.entries(links).filter(([,v]) => v);
    if (!entries.length) return '';
    return `
        <div class="rec-group">
            <div class="rec-group-title">🔗 Public Records &amp; Resources</div>
            <div class="ext-links">${entries.map(([label, url]) => `
                <a href="${esc(url)}" target="_blank" rel="noopener" class="ext-link">
                    🔍 ${esc(label)}
                </a>`).join('')}
            </div>
        </div>`;
}
