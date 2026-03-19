/* ============================================================
   cma.js — Quick CMA modal (two-step flow)
   ============================================================ */

let cmaStep1Data  = null;
let cmaCompList   = [];
let cmaCompSort   = 'dist';
let cmaGenerating = false;

// ── CMA Button ───────────────────────────────────────────────────
function buildCmaButton() {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'margin-bottom:20px;';
    wrap.innerHTML = `<button id="cmaBtn" class="btn-cma"><span class="cma-btn-icon">📊</span> Create Quick CMA</button>`;
    wrap.querySelector('#cmaBtn').addEventListener('click', openCmaModal);
    return wrap;
}

// ── Open Modal ───────────────────────────────────────────────────
function openCmaModal() {
    document.getElementById('cmaModal')?.remove();

    const allFiltered = applyFilters(appData?.properties || []);
    if (!allFiltered.length) { showError('No properties to include — adjust your filters first.'); return; }

    cmaCompList = allFiltered.map((p, i) => ({ ...p, _cmaIncluded: true, _cmaIdx: i }));

    // Inherit page sort
    const sortMap = { newest:'dist', price:'price_lo', distance:'dist', dom:'dist', sqft:'sqft', year:'dist' };
    cmaCompSort = sortMap[currentSort] || 'dist';

    const modal = document.createElement('div');
    modal.id = 'cmaModal';
    modal.className = 'cma-modal-overlay';
    modal.innerHTML = buildStep1Html(cmaCompList.length);
    document.body.appendChild(modal);

    document.getElementById('cmaClose').addEventListener('click',    () => modal.remove());
    document.getElementById('cmaCancelBtn').addEventListener('click', () => modal.remove());
    // No outside-click dismiss — user may scroll/resize

    // Agent edit toggle
    document.getElementById('cmaEditAgentBtn').addEventListener('click', () => {
        const display = document.getElementById('cmaAgentDisplay');
        const fields  = document.getElementById('cmaAgentFields');
        const btn     = document.getElementById('cmaEditAgentBtn');
        const editing = fields.style.display !== 'none';
        if (editing) {
            document.getElementById('cmaAgentNameDisplay').textContent  = document.getElementById('cmaAgentName')?.value  || 'Your Name';
            document.getElementById('cmaAgentPhoneDisplay').textContent = document.getElementById('cmaAgentPhone')?.value || 'Your Phone';
            document.getElementById('cmaAgentEmailDisplay').textContent = document.getElementById('cmaAgentEmail')?.value || 'Your Email';
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

    document.getElementById('cmaNextBtn').addEventListener('click', () => goToStep2());
}

// ── Step 1 HTML ──────────────────────────────────────────────────
function buildStep1Html(count) {
    return `
    <div class="cma-modal">
        <div class="cma-modal-header">
            <div>
                <h2 class="cma-modal-title">📊 Quick CMA</h2>
                <p class="cma-modal-sub">${count} comp${count===1?'':'s'} in &amp; around the subject address &nbsp;·&nbsp; Step 1 of 2</p>
            </div>
            <button class="cma-modal-close" id="cmaClose">✕</button>
        </div>
        <div class="cma-modal-body">
            <div class="cma-field-group">
                <label class="cma-label">Client Name(s)</label>
                <p class="cma-hint">Separate multiple names with commas</p>
                <input type="text" id="cmaNames" class="cma-input" placeholder="e.g. John, Sarah" autocomplete="new-password">
            </div>
            <div class="cma-field-group">
                <label class="cma-label">Client Email(s)</label>
                <p class="cma-hint">Separate multiple emails with commas</p>
                <input type="text" id="cmaEmails" class="cma-input" placeholder="e.g. john@email.com, sarah@email.com" autocomplete="new-password">
            </div>
            <hr class="cma-divider">
            <div class="cma-field-group">
                <label class="cma-label">
                    From (Your Info)
                    <button type="button" class="cma-edit-btn" id="cmaEditAgentBtn">✏️ Edit</button>
                </label>
                <div class="cma-agent-display" id="cmaAgentDisplay">
                    <div class="cma-agent-line" id="cmaAgentNameDisplay">Your Name</div>
                    <div class="cma-agent-line muted" id="cmaAgentPhoneDisplay">Your Phone</div>
                    <div class="cma-agent-line muted" id="cmaAgentEmailDisplay">Your Email</div>
                </div>
                <div id="cmaAgentFields" style="display:none">
                    <div class="cma-field-row" style="margin-bottom:10px">
                        <div class="cma-field-group">
                            <input type="text" id="cmaAgentName" class="cma-input" placeholder="Your name"
                                   autocomplete="off" data-lpignore="true">
                        </div>
                        <div class="cma-field-group">
                            <input type="text" id="cmaAgentPhone" class="cma-input" placeholder="Your phone"
                                   autocomplete="off" data-lpignore="true">
                        </div>
                    </div>
                    <input type="text" id="cmaAgentEmail" class="cma-input" placeholder="Your email"
                           style="margin-bottom:10px" autocomplete="off" data-lpignore="true">
                </div>
            </div>
            <hr class="cma-divider">
            <div class="cma-field-group">
                <label class="cma-label">
                    Your Notes
                    <span class="cma-ai-badge">✨ AI Enhanced</span>
                </label>
                <p class="cma-hint">Your thoughts about the area, market, or client. AI weaves these in naturally — not verbatim.</p>
                <textarea id="cmaAgentNotes" class="cma-textarea"
                          placeholder="e.g. Great school district, competitive market, client relocating from out of state..."></textarea>
            </div>
        </div>
        ${count > 30 ? `
            <div class="cma-limit-warning">
                <strong>Heads up:</strong> You currently have <strong>${count}</strong> homes selected.
                Only the first <strong>30</strong> (based on your current sort order) will be included in the email.
                Consider narrowing your filters or adjusting the search radius to be more selective.
            </div>` : ''}
        </div>
        <div class="cma-modal-footer">
            <button class="cma-btn-cancel" id="cmaCancelBtn">Cancel</button>
            <button class="cma-btn-preview" id="cmaNextBtn">
                <span id="cmaNextLabel">👁 &nbsp;Preview Before Sending</span>
                <span id="cmaNextSpinner" class="cma-spinner" style="display:none"></span>
            </button>
        </div>
    </div>`;
}

// ── Go to Step 2 ─────────────────────────────────────────────────
async function goToStep2() {
    const emailsRaw = document.getElementById('cmaEmails')?.value.trim();
    if (!emailsRaw) { alert('Please enter at least one client email.'); return; }

    const readField = (id, fallback) => {
        const el = document.getElementById(id);
        if (!el) return fallback || '';
        return (el.value !== undefined ? el.value : el.textContent || '').trim() || fallback || '';
    };

    cmaStep1Data = {
        names:      readField('cmaNames', ''),
        emails:     emailsRaw,
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
            _distance: p._distance,
        }));

        const res     = await fetch('cma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                recipients: [], properties: slimProps,
                agent_notes: cmaStep1Data.notes, agent_name: cmaStep1Data.agentName,
                agent_email: cmaStep1Data.agentEmail, agent_phone: cmaStep1Data.agentPhone,
                subject_address: appData?.searchedAddress || '',
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
    const defaultSubject = 'Your Quick CMA — ' + (appData?.searchedAddress || 'Property Analysis');
    const includedCount = cmaCompList.filter(p => p._cmaIncluded).length;
    return `
    <div class="cma-modal cma-modal-wide">
        <div class="cma-modal-header">
            <div>
                <h2 class="cma-modal-title">📋 Preview &amp; Edit</h2>
                <p class="cma-modal-sub">${includedCount} comp${includedCount===1?'':'s'} included &nbsp;·&nbsp; Step 2 of 2</p>
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
                    ✨ Market Narrative
                    <span style="font-size:.7rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;">click to edit</span>
                </label>
                <div id="cmaNarrativeEditor" class="cma-narrative-editor" contenteditable="true" spellcheck="true">${escForEditor(narrative)}</div>
            </div>
        </div>
        <div class="cma-modal-review-bar">
            <label class="cma-review-check">
                <input type="checkbox" id="cmaReviewedChk">
                <span class="cma-review-box"></span>
                <span>I've reviewed the subject and message — ready to send</span>
            </label>
        </div>
        <div class="cma-modal-footer">
            <button class="cma-btn-cancel" id="cmaBackBtn">← Back</button>
            <button class="cma-btn-send" id="cmaSendFinalBtn" disabled>
                <span id="cmaSendFinalLabel">✉️ &nbsp;Send Quick CMA</span>
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
    document.getElementById('cmaClose2').addEventListener('click',   () => document.getElementById('cmaModal').remove());
    document.getElementById('cmaBackBtn').addEventListener('click',  () => {
        document.getElementById('cmaModal').innerHTML = buildStep1Html(cmaCompList.length);
        if (cmaStep1Data) {
            document.getElementById('cmaNames').value  = cmaStep1Data.names;
            document.getElementById('cmaEmails').value = cmaStep1Data.emails;
            document.getElementById('cmaAgentNotes').value = cmaStep1Data.notes;
        }
        document.getElementById('cmaClose').addEventListener('click',    () => document.getElementById('cmaModal').remove());
        document.getElementById('cmaCancelBtn').addEventListener('click', () => document.getElementById('cmaModal').remove());
        document.getElementById('cmaEditAgentBtn').addEventListener('click', () => {});
        document.getElementById('cmaNextBtn').addEventListener('click',   () => goToStep2());
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

    const editorEl       = document.getElementById('cmaNarrativeEditor');
    const editedNarrative = editorEl ? (editorEl.innerText || editorEl.textContent || '') : '';

    if (!cmaStep1Data) { alert('Session expired — close and reopen the CMA modal.'); return; }
    const names  = (cmaStep1Data.names  || '').split(',').map(s => s.trim()).filter(Boolean);
    const emails = (cmaStep1Data.emails || '').split(',').map(s => s.trim()).filter(Boolean);
    const recipients = emails.map((email, i) => ({ name: names[i] || names[0] || '', email }));

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
                email_subject:    document.getElementById('cmaSubjectLine')?.value.trim() || '',
                agent_notes:      cmaStep1Data.notes,
                agent_name:       cmaStep1Data.agentName,
                agent_email:      cmaStep1Data.agentEmail,
                agent_phone:      cmaStep1Data.agentPhone,
                subject_address:  appData?.searchedAddress || '',
                site_url:         window.location.href.split('?')[0],
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
                        <h2 class="cma-modal-title">✅ CMA Sent!</h2>
                        <button class="cma-modal-close" onclick="document.getElementById('cmaModal').remove()">✕</button>
                    </div>
                    <div class="cma-modal-body" style="text-align:center;padding:40px 28px;">
                        <div style="font-size:3rem;margin-bottom:16px;">📬</div>
                        <p style="font-size:1rem;color:var(--text);font-weight:600;margin-bottom:8px;">Quick CMA delivered!</p>
                        <p style="font-size:.85rem;color:var(--muted);line-height:1.6;">${esc(data.message)}</p>
                        <button class="btn-search" style="margin-top:24px;width:auto;padding:12px 32px;"
                                onclick="document.getElementById('cmaModal').remove()">Done</button>
                    </div>
                </div>`;
        } else {
            alert('Send failed: ' + (data.message || data.error));
        }
    } catch(err) {
        alert('Error: ' + err.message);
        document.getElementById('cmaSendFinalLabel').style.display   = 'inline';
        document.getElementById('cmaSendFinalSpinner').style.display  = 'none';
        document.getElementById('cmaSendFinalBtn').disabled           = false;
    }
}
