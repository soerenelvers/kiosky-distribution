(() => {
    'use strict';
    const $ = s => document.querySelector(s), token = location.hash.slice(1), endpoint = new URL(new URLSearchParams(location.search).get('api') || '/api', location.origin);
    let model = null, items = [], editing = null, busy = false, requestId = crypto.randomUUID();
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function message(text, error = false) { $('#customer-status').textContent = text; $('#customer-status').dataset.error = String(error); }
    function url(path) { if (endpoint.origin !== location.origin)
        throw new Error('Der API-Endpunkt muss zu dieser Website gehören.'); if (endpoint.pathname === '/api') {
        const u = new URL(path, location.origin);
        return u;
    } const u = new URL(endpoint); u.searchParams.set('path', path); return u; }
    async function api(path, method = 'GET', body) { const r = await fetch(url(path), { method, credentials: 'omit', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-Customer-Token': token }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) }); const data = await r.json(); if (!r.ok)
        throw new Error(data.error?.message || 'Die Anfrage konnte nicht verarbeitet werden.'); return data; }
    function local(iso) { return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso)).replace(' ', 'T'); }
    function instant(value) { if (!/^\d{4}-\d\d-\d\dT\d\d:\d\d$/.test(value))
        throw new Error('Bitte Datum und Uhrzeit angeben.'); const wanted = Date.parse(value + 'Z'); let at = wanted; for (let n = 0; n < 4; n++) {
        const mapped = Date.parse(local(new Date(at).toISOString()) + 'Z');
        at += wanted - mapped;
    } if (local(new Date(at).toISOString()) !== value)
        throw new Error('Diese Ortszeit existiert wegen der Zeitumstellung nicht.'); return new Date(at).toISOString(); }
    function drawFiles() {
        const order = new Map(items.map((item, n) => [item.uploadId, n])), files = [...model.uploads].sort((a, b) => (order.get(a.id) ?? 999) - (order.get(b.id) ?? 999));
        $('#customer-file-usage').textContent = `${files.length} von 20 Dateien · ${(files.reduce((n, f) => n + f.size, 0) / 1000000).toFixed(1)} von 100 MB`;
        $('#customer-upload-list').innerHTML = files.map(f => { const selected = items.find(i => i.uploadId === f.id); return `<article class="file-card"><label class="file-choice"><input type="checkbox" data-file="${esc(f.id)}" ${selected ? 'checked' : ''}><span>${esc(f.name)}<small>${f.status === 'approved' ? 'Datei bereits freigegeben' : 'Wartet auf Freigabe'} · ${(f.size / 1000000).toFixed(1)} MB</small></span></label><div class="file-controls">${selected ? `<label>Anzeigedauer in Sekunden<input data-duration="${esc(f.id)}" type="number" min="3" max="600" value="${selected.durationSeconds}" required></label><button type="button" class="secondary" data-move="${esc(f.id)}" data-step="-1" aria-label="${esc(f.name)} nach oben" ${order.get(f.id) === 0 ? 'disabled' : ''}>↑</button><button type="button" class="secondary" data-move="${esc(f.id)}" data-step="1" aria-label="${esc(f.name)} nach unten" ${order.get(f.id) === items.length - 1 ? 'disabled' : ''}>↓</button>` : ''}${f.status !== 'removed' ? `<button type="button" class="secondary" data-preview="${esc(f.id)}">Vorschau</button>` : ''}</div></article>`; }).join('') || '<p>Noch keine Dateien hochgeladen.</p>';
    }
    function draw() { const i = model.invitation; $('#customer-upload-limits').textContent = `PNG, JPG, GIF, WebP, MP4, MOV oder WebM. Maximal ${(model.limits.fileBytes / 1000000).toFixed(1)} MB je Datei, 20 Dateien und 100 MB insgesamt. Bilder: höchstens 16 Megapixel und 8192 Pixel je Seite. Alle Inhalte benötigen eine Mitarbeiterfreigabe.`; $('#customer-context').textContent = `${i.customerName} · ${model.event.title} · Bearbeitung bis ${local(i.editUntil).replace('T', ' ')} Uhr (Europe/Berlin)`; $('#customer-workspace').hidden = false; const previous = [...document.querySelectorAll('[data-target]:checked')].map(n => n.value); $('#customer-targets').innerHTML = model.displays.map(d => `<label><input data-target type="checkbox" value="${esc(d.id)}" ${previous.includes(d.id) ? 'checked' : ''}>${esc(d.name)}</label>`).join(''); drawFiles(); $('#customer-proposals').innerHTML = model.proposals.map(p => `<article class="proposal-card"><h3>${esc(p.name)}</h3><p><strong>${esc(({ pending: 'Wartet auf Freigabe', approved: 'Freigegeben', rejected: 'Abgelehnt' }[p.status]) || p.status)}</strong> · ${esc(local(p.startsAt).replace('T', ' '))} bis ${esc(local(p.endsAt).replace('T', ' '))} Uhr</p><p>${esc(p.displayIds.map(id => model.displays.find(d => d.id === id)?.name || 'Nicht mehr verfügbar').join(', '))}</p>${p.note ? `<p>${esc(p.note)}</p>` : ''}<button type="button" class="secondary" data-edit="${esc(p.id)}">${p.status === 'pending' ? 'Vorschlag bearbeiten' : 'Als neuen Vorschlag verwenden'}</button></article>`).join('') || '<p>Noch kein Kanalvorschlag eingereicht.</p>'; }
    async function refresh() { model = await api('/api/customer-portal'); draw(); }
    function fresh() { editing = null; requestId = crypto.randomUUID(); $('#customer-proposal-id').value = ''; $('#customer-channel-name').value = ''; items = []; $('#customer-start').value = local(new Date(Math.max(Date.now() + 60000, Date.parse(model.invitation.startsAt))).toISOString()); $('#customer-end').value = local(model.invitation.endsAt); document.querySelectorAll('[data-target]').forEach(n => n.checked = model.displays.length === 1); drawFiles(); }
    function defaultDuration(file) { if (!file.type.startsWith('video/'))
        return Promise.resolve(12); return new Promise(resolve => { const video = document.createElement('video'), url = URL.createObjectURL(file); let done = false; const finish = value => { if (done)
        return; done = true; clearTimeout(timer); video.removeAttribute('src'); video.load(); URL.revokeObjectURL(url); resolve(value); }; const timer = setTimeout(() => finish(12), 5000); video.preload = 'metadata'; video.onloadedmetadata = () => finish(Number.isFinite(video.duration) ? Math.max(3, Math.min(600, Math.ceil(video.duration))) : 12); video.onerror = () => finish(12); video.src = url; }); }
    $('#customer-files').addEventListener('change', async (event) => { if (busy)
        return; const files = [...event.target.files]; if (!files.length)
        return; busy = true; event.target.disabled = true; const progress = $('#customer-upload-progress'); progress.hidden = false; progress.value = 0; try {
        if (model.uploads.length + files.length > 20)
            throw new Error('Maximal 20 Dateien je Freigabe.');
        for (let n = 0; n < files.length; n++) {
            const file = files[n];
            if (file.size > model.limits.fileBytes)
                throw new Error(file.name + `: maximal ${(model.limits.fileBytes / 1000000).toFixed(1)} MB je Datei auf diesem Server.`);
            message(`Datei ${n + 1} von ${files.length} wird hochgeladen …`);
            const data = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = () => reject(new Error('Datei konnte nicht gelesen werden.')); reader.readAsDataURL(file); });
            const { upload } = await api('/api/customer-portal/uploads', 'POST', { name: file.name, data, requestId: crypto.randomUUID() });
            if (!items.some(x => x.uploadId === upload.id))
                items.push({ uploadId: upload.id, durationSeconds: await defaultDuration(file) });
            progress.value = (n + 1) / files.length * 100;
        }
        await refresh();
        message('Dateien hochgeladen. Bitte Kanal, Displays und Ausspielzeit als Vorschlag einreichen.');
    }
    catch (error) {
        await refresh().catch(() => { });
        message(error.message, true);
    }
    finally {
        busy = false;
        event.target.disabled = false;
        event.target.value = '';
        progress.hidden = true;
    } });
    $('#customer-upload-list').addEventListener('change', event => { const f = event.target.dataset.file; if (f) {
        if (event.target.checked)
            items.push({ uploadId: f, durationSeconds: 12 });
        else
            items = items.filter(i => i.uploadId !== f);
        drawFiles();
    } const id = event.target.dataset.duration; if (id) {
        const i = items.find(i => i.uploadId === id);
        if (i)
            i.durationSeconds = Number(event.target.value);
    } });
    $('#customer-upload-list').addEventListener('click', async (event) => { const move = event.target.closest('[data-move]'); if (move) {
        const at = items.findIndex(i => i.uploadId === move.dataset.move), next = at + Number(move.dataset.step);
        if (next >= 0 && next < items.length)
            [items[at], items[next]] = [items[next], items[at]];
        drawFiles();
    } const preview = event.target.closest('[data-preview]'); if (preview)
        try {
            const data = await api('/api/customer-portal/files/' + encodeURIComponent(preview.dataset.preview));
            showPreview(data);
        }
        catch (error) {
            message(error.message, true);
        } });
    function showPreview(data) { const host = $('#customer-preview-content'); host.querySelector('video')?.pause(); host.querySelectorAll('[data-customer-blob]').forEach(n => URL.revokeObjectURL(n.dataset.customerBlob)); host.replaceChildren(); const media = document.createElement(data.mime.startsWith('video/') ? 'video' : 'img'); if (media.tagName === 'VIDEO') {
        media.controls = true;
        media.preload = 'metadata';
    }
    else
        media.alt = 'Hochgeladener Inhalt'; const encoded = data.data.split(',')[1], bytes = Uint8Array.from(atob(encoded), c => c.charCodeAt(0)); media.dataset.customerBlob = URL.createObjectURL(new Blob([bytes], { type: data.mime })); media.src = media.dataset.customerBlob; host.append(media); $('#customer-preview').showModal(); }
    $('#customer-preview-close').addEventListener('click', () => $('#customer-preview').close());
    $('#customer-preview').addEventListener('close', () => { $('#customer-preview-content video')?.pause(); $('#customer-preview-content').querySelectorAll('[data-customer-blob]').forEach(n => URL.revokeObjectURL(n.dataset.customerBlob)); $('#customer-preview-content').replaceChildren(); });
    $('#customer-proposal-form').addEventListener('submit', async (event) => { event.preventDefault(); if (busy)
        return; busy = true; const button = event.target.querySelector('button[type=submit]'); button.disabled = true; try {
        const body = { name: $('#customer-channel-name').value, displayIds: [...document.querySelectorAll('[data-target]:checked')].map(n => n.value), startsAt: instant($('#customer-start').value), endsAt: instant($('#customer-end').value), items, requestId, expectedRevision: editing?.revision };
        await api('/api/customer-portal/proposals' + (editing ? '/' + encodeURIComponent(editing.id) : ''), editing ? 'PUT' : 'POST', body);
        await refresh();
        fresh();
        message('Ihr Vorschlag wurde eingereicht. Er wird erst nach Freigabe ausgespielt.');
    }
    catch (error) {
        message(error.message, true);
    }
    finally {
        busy = false;
        button.disabled = false;
    } });
    $('#customer-proposals').addEventListener('click', event => { const b = event.target.closest('[data-edit]'); if (!b)
        return; const p = model.proposals.find(p => p.id === b.dataset.edit); editing = p.status === 'pending' ? p : null; requestId = crypto.randomUUID(); $('#customer-channel-name').value = p.name; $('#customer-start').value = local(p.startsAt); $('#customer-end').value = local(p.endsAt); items = p.items.map(x => ({ ...x })); document.querySelectorAll('[data-target]').forEach(n => n.checked = p.displayIds.includes(n.value)); drawFiles(); $('#customer-channel-name').focus(); });
    $('#customer-new-proposal').addEventListener('click', fresh);
    $('#customer-refresh').addEventListener('click', async () => { try {
        await refresh();
        message('Status aktualisiert.');
    }
    catch (error) {
        message(error.message, true);
    } });
    if (!/^[a-f0-9]{64}$/.test(token)) {
        message('Bitte den vollständigen Kundenlink vom Mitarbeiter öffnen.', true);
        return;
    }
    refresh().then(() => { fresh(); message('Freigabe aktiv. Inhalte und Ausspielwünsche können eingereicht werden.'); }).catch(error => message(error.message, true));
})();
