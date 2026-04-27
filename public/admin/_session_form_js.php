<?php
// JavaScript für den Symbol-Picker (shared zwischen create.php und edit.php)
// Benötigt $existingJs (JSON-Array der vorhandenen Symbole)
?>
<script>
const MAX_SYM = <?= WordCloudManager::MAX_SYMBOLS_ABS ?>;
function getMax() { return MAX_SYM; }
let symbols   = <?= $existingJs ?? '[]' ?>;
let searchTimer = null;

// ---- Initialisierung ----
renderSymbols();
syncModeVisibility();

document.querySelectorAll('input[name="mode"]').forEach(r =>
    r.addEventListener('change', syncModeVisibility)
);

function syncModeVisibility() {
    const mode = document.querySelector('input[name="mode"]:checked')?.value || 'both';
    document.getElementById('symbolSection').classList.toggle('dim', mode === 'search');
}

// ---- ARASAAC-Suche ----
document.getElementById('symSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    document.getElementById('symHint').classList.toggle('d-none', q.length >= 2);
    if (q.length < 2) { document.getElementById('symResults').innerHTML = ''; return; }
    searchTimer = setTimeout(() => runSearch(q), 380);
});

function runSearch(q) {
    const lang    = document.getElementById('symLang').value;
    const spinner = document.getElementById('symSpinner');
    const results = document.getElementById('symResults');
    spinner.classList.remove('d-none');
    results.innerHTML = '';

    fetch('/api/search.php?q=' + encodeURIComponent(q) + '&lang=' + lang)
        .then(r => r.json())
        .then(data => {
            spinner.classList.add('d-none');
            if (!data.length) {
                results.innerHTML = '<small class="text-muted">Keine Treffer.</small>';
                return;
            }
            data.forEach(sym => {
                const already = symbols.some(s => s.id === sym.id);
                const d = document.createElement('div');
                d.className = 'sym-result' + (already ? ' used' : '');
                d.title     = sym.keywords.join(', ');
                d.innerHTML = `<img src="${sym.image_url}" alt="" loading="lazy">
                               <div class="kw">${esc(sym.keywords[0] || '')}</div>`;
                if (!already) d.addEventListener('click', () => addSymbol(sym));
                results.appendChild(d);
            });
        })
        .catch(() => {
            spinner.classList.add('d-none');
            results.innerHTML = '<small class="text-danger">Suche fehlgeschlagen.</small>';
        });
}

// ---- Eigenes Bild hochladen ----
document.getElementById('symUploadBtn').addEventListener('click', function () {
    document.getElementById('symUploadInput').click();
});

document.getElementById('symUploadInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    if (symbols.length >= getMax()) {
        alert('Maximal ' + getMax() + ' Symbole pro Sitzung.');
        this.value = '';
        return;
    }

    const status = document.getElementById('symUploadStatus');
    status.textContent = 'Wird hochgeladen…';

    const fd = new FormData();
    fd.append('image', file);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch('/api/upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                status.textContent = 'Fehler: ' + data.error;
                return;
            }
            status.textContent = '';
            const baseName = file.name.replace(/\.[^.]+$/, '');
            addSymbol({ id: data.id, keywords: [baseName], image_url: data.image_url });
        })
        .catch(() => { status.textContent = 'Upload fehlgeschlagen.'; })
        .finally(() => { this.value = ''; });
});

// ---- Symbol hinzufügen / entfernen ----
function addSymbol(sym) {
    if (symbols.length >= getMax()) {
        alert('Maximal ' + getMax() + ' Symbole pro Sitzung.');
        return;
    }
    if (symbols.some(s => s.id === sym.id)) return;
    symbols.push({ id: sym.id, label: sym.keywords[0] || '', image_url: sym.image_url || '' });
    renderSymbols();
    refreshResultsUsed();
}

function removeSymbol(id) {
    const sym = symbols.find(s => s.id === id);
    symbols = symbols.filter(s => s.id !== id);
    renderSymbols();
    refreshResultsUsed();
    if (sym && id < 0 && sym.image_url) {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        const sid  = typeof SESSION_EDIT_ID !== 'undefined' ? SESSION_EDIT_ID : 0;
        fetch('/api/remove_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_url: sym.image_url, session_id: sid, csrf_token: csrf }),
        }).catch(() => {});
    }
}

function renderSymbols() {
    const area  = document.getElementById('symSelected');
    const empty = document.getElementById('symEmpty');
    document.getElementById('symCount').textContent = symbols.length + ' / ' + getMax();

    area.querySelectorAll('.sym-card-admin').forEach(el => el.remove());
    empty.classList.toggle('d-none', symbols.length > 0);

    symbols.forEach((sym, idx) => {
        const imgSrc = sym.image_url
            ? sym.image_url
            : `https://static.arasaac.org/pictograms/${sym.id}/${sym.id}_300.png`;
        const d = document.createElement('div');
        d.className = 'sym-card-admin';
        d.innerHTML = `
            <button type="button" class="del" onclick="removeSymbol(${sym.id})">&times;</button>
            <img src="${imgSrc}" alt="" loading="lazy">
            <input type="hidden" name="symbol_id[]"        value="${sym.id}">
            <input type="hidden" name="symbol_image_url[]" value="${esc(sym.image_url || '')}">
            <input type="text"   name="symbol_label[]" value="${esc(sym.label)}"
                   maxlength="80" placeholder="Beschriftung"
                   oninput="symbols[${idx}].label = this.value">`;
        area.appendChild(d);
    });
}

function refreshResultsUsed() {
    document.querySelectorAll('.sym-result').forEach(el => {
        const img = el.querySelector('img');
        if (!img) return;
        const m = img.src.match(/\/(\d+)\/\d+_300\.png/);
        if (!m)  return;
        el.classList.toggle('used', symbols.some(s => s.id === parseInt(m[1])));
    });
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
