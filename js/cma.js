/* ============================================================
   cma.js — CMA Email Creator (thumbnail comp selector + AI)
   ============================================================ */

let cmaStep1Data  = null;
let cmaCompList   = [];
let cmaGenerating = false;

// ── Agent info persistence (localStorage) ────────────────────────
function getSavedAgentInfo() {
    try {
        const saved = localStorage.getItem('cma_agent_info');
        return saved ? JSON.parse(saved) : null;
    } catch(_) { return null; }
}
function saveAgentInfo(name, phone, email) {
    try {
        localStorage.setItem('cma_agent_info', JSON.stringify({ name, phone, email }));
    } catch(_) {}
}
function _cmaAgentName() {
    const s = getSavedAgentInfo();
    return s?.name || (typeof AGENT_NAME !== 'undefined' ? AGENT_NAME : 'Your Name');
}
function _cmaAgentPhone() {
    const s = getSavedAgentInfo();
    return s?.phone || (typeof AGENT_PHONE !== 'undefined' ? AGENT_PHONE : 'Your Phone');
}
function _cmaAgentEmail() {
    const s = getSavedAgentInfo();
    return s?.email || (typeof AGENT_EMAIL !== 'undefined' ? AGENT_EMAIL : 'Your Email');
}

// ── CMA Button ───────────────────────────────────────────────────
function buildCmaButton() {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'margin-bottom:20px;';
    wrap.innerHTML = `<button id="cmaBtn" class="btn-cma"><span class="cma-btn-icon">📊</span> Create CMA Email</button>`;
    wrap.querySelector('#cmaBtn').addEventListener('click', openCmaModal);
    return wrap;
}

// ── Open Modal ───────────────────────────────────────────────────
function openCmaModal() {
    document.getElementById('cmaModal')?.remove();

    const allFiltered = applyFilters(appData?.properties || []);
    const selected = allFiltered.filter(p => selectedHomes.has(getPropKey(p)));
    if (!selected.length) { showError('No properties selected — check some listings first.'); return; }

    // Sort by price descending
    cmaCompList = selected.map((p, i) => ({ ...p, _cmaIncluded: true, _cmaIdx: i }));
    cmaCompList.sort((a, b) => {
        const pa = parseFloat(a.ClosePrice || a.ListPrice || 0);
        const pb = parseFloat(b.ClosePrice || b.ListPrice || 0);
        return pb - pa;
    });

    const modal = document.createElement('div');
    modal.id = 'cmaModal';
    modal.className = 'cma-modal-overlay';
    modal.innerHTML = buildStep1Html();
    document.body.appendChild(modal);

    wireStep1Events();
}

// ── Step 1 HTML ──────────────────────────────────────────────────
function buildStep1Html() {
    const count = cmaCompList.filter(p => p._cmaIncluded).length;
    const total = cmaCompList.length;

    // Subject property summary from ATTOM
    const attom = appData?.publicRecords?.attom;
    const subjectAddr = appData?.searchedAddress || '';
    let subjectSummary = '';
    if (attom && !attom._error) {
        const parts = [];
        if (attom.bedrooms) parts.push(attom.bedrooms + ' bd');
        if (attom.bathrooms) parts.push(attom.bathrooms + ' ba');
        if (attom.gross_sqft) parts.push(fmtNum(attom.gross_sqft) + ' sqft');
        if (attom.year_built) parts.push('Built ' + attom.year_built);
        if (attom.lot_size_sqft) parts.push(fmtNum(attom.lot_size_sqft) + ' sqft lot');
        subjectSummary = parts.join(' · ');
    }

    return `
    <div class="cma-modal cma-modal-wide">
        <div class="cma-modal-header">
            <div>
                <h2 class="cma-modal-title">📊 Create CMA Email</h2>
                <p class="cma-modal-sub"><span id="cmaCompCount">${count}</span> of ${total} comp${total===1?'':'s'} selected &nbsp;·&nbsp; Step 1 of 2</p>
            </div>
            <button class="cma-modal-close" id="cmaClose">✕</button>
        </div>
        <div class="cma-modal-body">

            ${subjectAddr ? `
            <div class="cma-subject-card">
                <div class="cma-subject-label">Subject Property</div>
                <div class="cma-subject-addr">${esc(subjectAddr)}</div>
                ${subjectSummary ? `<div class="cma-subject-stats">${esc(subjectSummary)}</div>` : ''}
            </div>` : ''}

            <div class="cma-field-group">
                <label class="cma-label">Recipient</label>
                <div class="cma-field-row">
                    <div class="cma-field-group">
                        <input type="text" id="cmaFirstName" class="cma-input" placeholder="First name" autocomplete="off">
                    </div>
                    <div class="cma-field-group">
                        <input type="text" id="cmaLastName" class="cma-input" placeholder="Last name" autocomplete="off">
                    </div>
                </div>
                <input type="text" id="cmaEmail" class="cma-input" placeholder="Email address" autocomplete="off">
            </div>

            <hr class="cma-divider">

            <div class="cma-field-group">
                <label class="cma-label">
                    From (Your Info)
                    <button type="button" class="cma-edit-btn" id="cmaEditAgentBtn">✏️ Edit</button>
                </label>
                <div class="cma-agent-display" id="cmaAgentDisplay">
                    <div class="cma-agent-line" id="cmaAgentNameDisplay">${esc(_cmaAgentName())}</div>
                    <div class="cma-agent-line muted" id="cmaAgentPhoneDisplay">${esc(_cmaAgentPhone())}</div>
                    <div class="cma-agent-line muted" id="cmaAgentEmailDisplay">${esc(_cmaAgentEmail())}</div>
                </div>
                <div id="cmaAgentFields" style="display:none">
                    <div class="cma-field-row" style="margin-bottom:10px">
                        <div class="cma-field-group">
                            <input type="text" id="cmaAgentName" class="cma-input" placeholder="Your name" value="${esc(_cmaAgentName())}" autocomplete="off">
                        </div>
                        <div class="cma-field-group">
                            <input type="text" id="cmaAgentPhone" class="cma-input" placeholder="Your phone" value="${esc(_cmaAgentPhone())}" autocomplete="off">
                        </div>
                    </div>
                    <input type="text" id="cmaAgentEmail" class="cma-input" placeholder="Your email"
                           value="${esc(_cmaAgentEmail())}" style="margin-bottom:10px" autocomplete="off">
                </div>
            </div>

            <hr class="cma-divider">

            <div class="cma-field-group">
                <label class="cma-label">
                    Personal Message / AI Instructions
                    <span class="cma-ai-badge">✨ AI Enhanced</span>
                </label>
                <p class="cma-hint">Add your thoughts about the property, market, or client. AI will weave these naturally into a professional narrative along with all property and neighborhood data.</p>
                <textarea id="cmaAgentNotes" class="cma-textarea"
                          placeholder="e.g. Great school district, seller is motivated, perfect for a growing family, client is relocating from out of state..."></textarea>
            </div>

            <hr class="cma-divider">

            <div class="cma-field-group">
                <label class="cma-label">Comparable Properties</label>
                <p class="cma-hint">Sorted by price (highest first). Uncheck any you want to exclude.</p>
                <div class="cma-comp-select-all">
                    <label class="cma-comp-sa-label">
                        <input type="checkbox" id="cmaSelectAll" checked>
                        <span class="cma-comp-sa-box"></span>
                        <span>Select All</span>
                    </label>
                    <span class="cma-comp-sa-count" id="cmaCompCountBadge">${count} selected</span>
                </div>
                <div class="cma-comp-grid" id="cmaCompGrid">
                    ${cmaCompList.map((p, i) => buildCompThumb(p, i)).join('')}
                </div>
            </div>

            ${total > 30 ? `
            <div class="cma-limit-warning">
                <strong>Heads up:</strong> You have <strong>${total}</strong> comps.
                Only the first <strong>30</strong> (by price) will be included in the email.
            </div>` : ''}
        </div>
        <div class="cma-modal-footer">
            <button class="cma-btn-cancel" id="cmaCancelBtn">Cancel</button>
            <button class="cma-btn-preview" id="cmaNextBtn">
                <span id="cmaNextLabel">👁 &nbsp;Preview &amp; Generate AI Narrative</span>
                <span id="cmaNextSpinner" class="cma-spinner" style="display:none"></span>
            </button>
        </div>
    </div>`;
}

function buildCompThumb(p, idx) {
    const isClosed = p.StandardStatus === 'Closed';
    const price = isClosed ? (p.ClosePrice || p.ListPrice || 0) : (p.ListPrice || 0);
    const addr = [p.StreetNumber, p.StreetName].filter(Boolean).join(' ');
    const city = p.City || '';
    const photo = p._photo || '';
    const beds = p.BedroomsTotal != null ? p.BedroomsTotal : '—';
    const baths = p.BathroomsTotalInteger != null ? p.BathroomsTotalInteger : '—';
    const sqft = p.LivingArea ? fmtNum(p.LivingArea) : '—';
    const status = p.StandardStatus || 'Unknown';
    const statusCls = 'b-' + status.replace(/ /g, '-');

    return `
    <div class="cma-comp-thumb ${p._cmaIncluded ? '' : 'excluded'}" data-cma-idx="${idx}">
        <label class="cma-comp-cb-wrap">
            <input type="checkbox" class="cma-comp-cb" data-cma-idx="${idx}" ${p._cmaIncluded ? 'checked' : ''}>
            <span class="cma-comp-cb-box"></span>
        </label>
        <div class="cma-comp-photo">
            ${photo ? `<img src="${esc(photo)}" alt="" loading="lazy">` : `<div class="cma-comp-nophoto">🏠</div>`}
        </div>
        <div class="cma-comp-info">
            <div class="cma-comp-price">${price ? '$' + fmtNum(price) : '—'}</div>
            <div class="cma-comp-addr">${esc(addr)}</div>
            <div class="cma-comp-addr">${esc(city)}</div>
            <div class="cma-comp-meta">${beds} bd · ${baths} ba · ${sqft} sqft</div>
            <span class="cma-comp-status ${esc(statusCls)}">${esc(status)}</span>
        </div>
    </div>`;
}

// ── Step 1 Events ────────────────────────────────────────────────
function wireStep1Events() {
    document.getElementById('cmaClose').addEventListener('click', () => document.getElementById('cmaModal').remove());
    document.getElementById('cmaCancelBtn').addEventListener('click', () => document.getElementById('cmaModal').remove());

    // Agent edit toggle
    document.getElementById('cmaEditAgentBtn').addEventListener('click', () => {
        const display = document.getElementById('cmaAgentDisplay');
        const fields  = document.getElementById('cmaAgentFields');
        const btn     = document.getElementById('cmaEditAgentBtn');
        const editing = fields.style.display !== 'none';
        if (editing) {
            const newName  = document.getElementById('cmaAgentName')?.value  || 'Your Name';
            const newPhone = document.getElementById('cmaAgentPhone')?.value || 'Your Phone';
            const newEmail = document.getElementById('cmaAgentEmail')?.value || 'Your Email';
            document.getElementById('cmaAgentNameDisplay').textContent  = newName;
            document.getElementById('cmaAgentPhoneDisplay').textContent = newPhone;
            document.getElementById('cmaAgentEmailDisplay').textContent = newEmail;
            saveAgentInfo(newName, newPhone, newEmail);
            display.style.display = '';
            fields.style.display  = 'none';
            btn.textContent = '✏️ Edit';
        } else {
            display.style.display = 'none';
            fields.style.display  = '';
            btn.textContent = '✓ Done';
            document.getElementById('cmaAgentName')?.focus();
        }
    });

    // Select All toggle
    document.getElementById('cmaSelectAll').addEventListener('change', function() {
        const checked = this.checked;
        cmaCompList.forEach(p => p._cmaIncluded = checked);
        document.querySelectorAll('.cma-comp-cb').forEach(cb => {
            cb.checked = checked;
            cb.closest('.cma-comp-thumb').classList.toggle('excluded', !checked);
        });
        updateCmaCompCount();
    });

    // Individual comp checkboxes
    document.querySelectorAll('.cma-comp-cb').forEach(cb => {
        cb.addEventListener('change', function() {
            const idx = parseInt(this.dataset.cmaIdx);
            cmaCompList[idx]._cmaIncluded = this.checked;
            this.closest('.cma-comp-thumb').classList.toggle('excluded', !this.checked);
            updateCmaCompCount();
        });
    });

    // Next button
    document.getElementById('cmaNextBtn').addEventListener('click', () => goToStep2());
}

function updateCmaCompCount() {
    const count = cmaCompList.filter(p => p._cmaIncluded).length;
    const countEl = document.getElementById('cmaCompCount');
    const badgeEl = document.getElementById('cmaCompCountBadge');
    if (countEl) countEl.textContent = count;
    if (badgeEl) badgeEl.textContent = count + ' selected';

    // Sync Select All
    const selectAll = document.getElementById('cmaSelectAll');
    if (selectAll) selectAll.checked = count === cmaCompList.length;
}

// ── Go to Step 2 ─────────────────────────────────────────────────
async function goToStep2() {
    const emailRaw = document.getElementById('cmaEmail')?.value.trim();
    if (!emailRaw) { alert('Please enter the recipient email address.'); return; }

    const readField = (id, fallback) => {
        const el = document.getElementById(id);
        if (!el) return fallback || '';
        return (el.value !== undefined ? el.value : el.textContent || '').trim() || fallback || '';
    };

    cmaStep1Data = {
        firstName:  readField('cmaFirstName', ''),
        lastName:   readField('cmaLastName', ''),
        email:      emailRaw,
        agentName:  readField('cmaAgentName',  readField('cmaAgentNameDisplay',  '')),
        agentEmail: readField('cmaAgentEmail', readField('cmaAgentEmailDisplay', '')),
        agentPhone: readField('cmaAgentPhone', readField('cmaAgentPhoneDisplay', '')),
        notes:      readField('cmaAgentNotes', ''),
    };

    document.getElementById('cmaNextLabel').style.display   = 'none';
    document.getElementById('cmaNextSpinner').style.display  = 'inline-block';
    document.getElementById('cmaNextBtn').disabled           = true;

    try {
        const included  = cmaCompList.filter(p => p._cmaIncluded).slice(0, 30);
        const slimProps = included.map(p => ({
            StandardStatus: p.StandardStatus, ListPrice: p.ListPrice, ClosePrice: p.ClosePrice,
            CloseDate: p.CloseDate, StreetNumber: p.StreetNumber, StreetName: p.StreetName,
            City: p.City, StateOrProvince: p.StateOrProvince, PostalCode: p.PostalCode,
            BedroomsTotal: p.BedroomsTotal, BathroomsTotalInteger: p.BathroomsTotalInteger,
            LivingArea: p.LivingArea, YearBuilt: p.YearBuilt,
            DaysOnMarket: p.DaysOnMarket || p.CumulativeDaysOnMarket,
            _distance: p._distance, _photo: p._photo,
        }));

        const res = await fetch('cma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                recipients: [], properties: slimProps,
                agent_notes: cmaStep1Data.notes,
                agent_name: cmaStep1Data.agentName,
                agent_email: cmaStep1Data.agentEmail,
                agent_phone: cmaStep1Data.agentPhone,
                subject_address: appData?.searchedAddress || '',
                subject_attom: appData?.publicRecords?.attom || null,
                recipient_first: cmaStep1Data.firstName,
                recipient_last: cmaStep1Data.lastName,
                site_url: window.location.href.split('?')[0],
                preview_only: true,
            }),
        });

        const httpStatus = res.status;
        const rawText    = await res.text();
        let data;
        try { data = JSON.parse(rawText); }
        catch(_) {
            const preview = rawText.trim().slice(0,500) || `(empty — HTTP ${httpStatus})`;
            showStepError(`HTTP ${httpStatus} — ${preview.replace(/</g,'&lt;')}`);
            resetNextBtn();
            return;
        }

        if (!data.success) {
            showStepError(data.error || 'Preview failed');
            resetNextBtn();
            return;
        }

        const narrative = data.narrative || '';
        document.getElementById('cmaModal').innerHTML = buildStep2Html(narrative);
        wireStep2Events();

    } catch(err) {
        showStepError('Network error: ' + err.message);
        resetNextBtn();
    }
}

function showStepError(msg) {
    const body = document.querySelector('.cma-modal-body');
    if (!body) return;
    let errDiv = body.querySelector('.cma-step-error');
    if (!errDiv) {
        errDiv = document.createElement('div');
        errDiv.className = 'cma-step-error';
        errDiv.style.cssText = 'margin-top:14px;padding:12px 14px;background:rgba(255,92,92,.08);border:1px solid rgba(255,92,92,.3);border-radius:8px;font-size:.78rem;color:#fca5a5;line-height:1.5;word-break:break-all;';
        body.appendChild(errDiv);
    }
    errDiv.innerHTML = '<strong>Error:</strong> ' + msg;
}

function resetNextBtn() {
    const lbl = document.getElementById('cmaNextLabel');
    const spn = document.getElementById('cmaNextSpinner');
    const btn = document.getElementById('cmaNextBtn');
    if (lbl) lbl.style.display = 'inline';
    if (spn) spn.style.display = 'none';
    if (btn) btn.disabled = false;
}

// ── Step 2 HTML ──────────────────────────────────────────────────
function buildStep2Html(narrative) {
    narrative = narrative || '';
    const recipientName = [cmaStep1Data?.firstName, cmaStep1Data?.lastName].filter(Boolean).join(' ');
    const defaultSubject = 'Your CMA — ' + (appData?.searchedAddress || 'Property Analysis');
    const includedCount = cmaCompList.filter(p => p._cmaIncluded).length;
    return `
    <div class="cma-modal cma-modal-wide">
        <div class="cma-modal-header">
            <div>
                <h2 class="cma-modal-title">📋 Preview &amp; Edit</h2>
                <p class="cma-modal-sub">${includedCount} comp${includedCount===1?'':'s'} included${recipientName ? ' · To: ' + esc(recipientName) : ''} &nbsp;·&nbsp; Step 2 of 2</p>
            </div>
            <button class="cma-modal-close" id="cmaClose2">✕</button>
        </div>
        <div class="cma-modal-body">
            <div class="cma-field-group">
                <label class="cma-label">Email Subject</label>
                <input type="text" id="cmaSubjectLine" class="cma-input"
                       value="${esc(defaultSubject)}" autocomplete="off" spellcheck="false">
            </div>
            <div class="cma-field-group" style="margin-bottom:0">
                <label class="cma-label">
                    ✨ AI Market Narrative
                    <span style="font-size:.7rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;">click to edit</span>
                </label>
                <div id="cmaNarrativeEditor" class="cma-narrative-editor" contenteditable="true" spellcheck="true">${escForEditor(narrative)}</div>
            </div>
        </div>
        <div class="cma-modal-review-bar">
            <label class="cma-review-check">
                <input type="checkbox" id="cmaReviewedChk">
                <span class="cma-review-box"></span>
                <span>I've reviewed the content — ready to send</span>
            </label>
        </div>
        <div class="cma-modal-footer">
            <button class="cma-btn-cancel" id="cmaBackBtn">← Back</button>
            <button class="cma-btn-send" id="cmaSendFinalBtn" disabled>
                <span id="cmaSendFinalLabel">✉️ &nbsp;Send CMA Email</span>
                <span id="cmaSendFinalSpinner" class="cma-spinner" style="display:none"></span>
            </button>
        </div>
    </div>`;
}

function escForEditor(text) {
    if (!text) return '<p></p>';
    return String(text).split('\n\n').filter(p => p.trim())
        .map(p => '<p>' + esc(p.trim()) + '</p>').join('');
}

function wireStep2Events() {
    document.getElementById('cmaClose2').addEventListener('click', () => document.getElementById('cmaModal').remove());
    document.getElementById('cmaBackBtn').addEventListener('click', () => {
        document.getElementById('cmaModal').innerHTML = buildStep1Html();
        wireStep1Events();
        // Restore form fields
        if (cmaStep1Data) {
            const fn = document.getElementById('cmaFirstName');
            const ln = document.getElementById('cmaLastName');
            const em = document.getElementById('cmaEmail');
            const nt = document.getElementById('cmaAgentNotes');
            if (fn) fn.value = cmaStep1Data.firstName;
            if (ln) ln.value = cmaStep1Data.lastName;
            if (em) em.value = cmaStep1Data.email;
            if (nt) nt.value = cmaStep1Data.notes;
        }
    });

    document.getElementById('cmaReviewedChk').addEventListener('change', function() {
        document.getElementById('cmaSendFinalBtn').disabled = !this.checked;
    });

    document.getElementById('cmaSendFinalBtn').addEventListener('click', () => sendFinalCma());
}

// ── Send Final ───────────────────────────────────────────────────
async function sendFinalCma() {
    const included = cmaCompList.filter(p => p._cmaIncluded).slice(0, 30);
    if (!included.length) { alert('Please include at least one comp.'); return; }

    const editorEl = document.getElementById('cmaNarrativeEditor');
    const editedNarrative = editorEl ? (editorEl.innerText || editorEl.textContent || '') : '';

    if (!cmaStep1Data) { alert('Session expired — close and reopen the CMA modal.'); return; }

    const recipientName = [cmaStep1Data.firstName, cmaStep1Data.lastName].filter(Boolean).join(' ');
    const recipients = [{
        name: recipientName,
        email: cmaStep1Data.email,
        first_name: cmaStep1Data.firstName,
        last_name: cmaStep1Data.lastName,
    }];

    document.getElementById('cmaSendFinalLabel').style.display   = 'none';
    document.getElementById('cmaSendFinalSpinner').style.display  = 'inline-block';
    document.getElementById('cmaSendFinalBtn').disabled           = true;

    const slimFinal = included.map(p => ({
        StandardStatus: p.StandardStatus, ListPrice: p.ListPrice, ClosePrice: p.ClosePrice,
        CloseDate: p.CloseDate, StreetNumber: p.StreetNumber, StreetName: p.StreetName,
        City: p.City, StateOrProvince: p.StateOrProvince, PostalCode: p.PostalCode,
        BedroomsTotal: p.BedroomsTotal, BathroomsTotalInteger: p.BathroomsTotalInteger,
        LivingArea: p.LivingArea, YearBuilt: p.YearBuilt,
        DaysOnMarket: p.DaysOnMarket || p.CumulativeDaysOnMarket,
        _photo: p._photo, _distance: p._distance,
        PublicRemarks: p.PublicRemarks ? p.PublicRemarks.slice(0,300) : '',
    }));

    try {
        const res  = await fetch('cma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                recipients, properties: slimFinal,
                edited_narrative: editedNarrative,
                email_subject: document.getElementById('cmaSubjectLine')?.value.trim() || '',
                agent_notes: cmaStep1Data.notes,
                agent_name: cmaStep1Data.agentName,
                agent_email: cmaStep1Data.agentEmail,
                agent_phone: cmaStep1Data.agentPhone,
                subject_address: appData?.searchedAddress || '',
                subject_attom: appData?.publicRecords?.attom || null,
                recipient_first: cmaStep1Data.firstName,
                recipient_last: cmaStep1Data.lastName,
                site_url: window.location.href.split('?')[0],
            }),
        });

        const data = await res.json();
        document.getElementById('cmaSendFinalLabel').style.display   = 'inline';
        document.getElementById('cmaSendFinalSpinner').style.display  = 'none';
        document.getElementById('cmaSendFinalBtn').disabled           = false;

        if (data.success) {
            document.getElementById('cmaModal').innerHTML = `
                <div class="cma-modal">
                    <div class="cma-modal-header">
                        <h2 class="cma-modal-title">✅ CMA Email Sent!</h2>
                        <button class="cma-modal-close" onclick="document.getElementById('cmaModal').remove()">✕</button>
                    </div>
                    <div class="cma-modal-body" style="text-align:center;padding:40px 28px;">
                        <div style="font-size:3rem;margin-bottom:16px;">📬</div>
                        <p style="font-size:1rem;color:var(--text);font-weight:600;margin-bottom:8px;">CMA Email delivered!</p>
                        <p style="font-size:.85rem;color:var(--muted);line-height:1.6;">${esc(data.message)}</p>
                        <button class="btn-search" style="margin-top:24px;width:auto;padding:12px 32px;"
                                onclick="document.getElementById('cmaModal').remove()">Done</button>
                    </div>
                </div>`;
        } else {
            const errMsg = data.message || data.error || 'Unknown error';
            const errDetails = data.errors?.length ? '\n\nDetails:\n' + data.errors.join('\n') : '';
            alert('Send failed: ' + errMsg + errDetails);
        }
    } catch(err) {
        alert('Error: ' + err.message);
        document.getElementById('cmaSendFinalLabel').style.display   = 'inline';
        document.getElementById('cmaSendFinalSpinner').style.display  = 'none';
        document.getElementById('cmaSendFinalBtn').disabled           = false;
    }
}
