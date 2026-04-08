<?php
define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/includes/bootstrap.php';

$code    = strtoupper(preg_replace('/[^A-Z0-9]/', '', get('code', '')));
$mgr     = new WordCloudManager();
$session = $code ? $mgr->getSessionByCode($code) : null;

$appTitle = appTitle();
$mode     = $session['mode']   ?? '';
$isClosed = $session           && $session['status'] === 'closed';
$isActive = $session           && $session['status'] === 'active';

// Presets als JSON für JS
$presetsJson = $session
    ? json_encode(
        array_map(fn($s) => [
            'id'    => (int) $s['arasaac_id'],
            'label' => $s['label'],
        ], $session['predefined_symbols'] ?? []),
        JSON_UNESCAPED_UNICODE
    )
    : '[]';

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $session ? e($session['title']) . ' – ' : '' ?><?= e($appTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ---- Layout ---- */
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; background: #f0f2f8; font-family: system-ui, sans-serif; }

        /* ---- Top-Bar ---- */
        .topbar {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            flex-shrink: 0;
        }
        .topbar-title { font-size: 1.1rem; font-weight: 700; }
        .topbar-meta  { font-size: 12px; opacity: .8; }
        .topbar-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .code-badge   { background: rgba(255,255,255,.15); padding: 4px 12px;
                        border-radius: 20px; font-family: monospace; font-size: 1.2rem;
                        font-weight: 800; letter-spacing: 4px; }
        .live-pill    { display: flex; align-items: center; gap: 5px; font-size: 12px; opacity: .9; }
        .live-dot     { width: 7px; height: 7px; background: #4ade80; border-radius: 50%;
                        animation: blink 1.4s infinite; }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.2;} }

        /* ---- Suchleiste ---- */
        .searchbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 20px;
            flex-shrink: 0;
        }
        .searchbar .form-control { border-radius: 12px; }
        .search-drop {
            display: flex; flex-wrap: wrap; gap: 8px;
            max-height: 180px; overflow-y: auto;
            padding-top: 8px;
        }
        .search-item {
            display: flex; flex-direction: column; align-items: center;
            width: 74px; padding: 5px 4px; border: 2px solid #e5e7eb;
            border-radius: 10px; background: #fff; cursor: pointer;
            text-align: center; transition: border-color .12s, transform .1s;
            user-select: none;
        }
        .search-item:hover { border-color: #4f46e5; background: #f5f3ff; transform: translateY(-1px); }
        .search-item:active { transform: scale(.95); }
        .search-item img { width: 50px; height: 50px; object-fit: contain; }
        .search-item .kw { font-size: 10px; color: #555; margin-top: 2px;
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; }

        /* ---- Preset-Leiste (Modus symbols/both) ---- */
        .presetbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 20px;
            flex-shrink: 0;
        }
        .presetbar-label { font-size: 11px; font-weight: 600; text-transform: uppercase;
                           letter-spacing: 1px; color: #9ca3af; margin-bottom: 10px; }
        .preset-row { display: flex; flex-wrap: wrap; gap: 10px; }

        /* ---- Symbol-Karte (allgemein) ---- */
        .sym-card {
            display: flex; flex-direction: column; align-items: center;
            padding: 10px 8px; border-radius: 14px; border: 2px solid transparent;
            background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.07);
            cursor: pointer; user-select: none;
            transition: border-color .2s, box-shadow .2s, transform .15s;
            -webkit-tap-highlight-color: transparent;
        }
        .sym-card:hover  { border-color: #a5b4fc; box-shadow: 0 5px 16px rgba(79,70,229,.15);
                           transform: translateY(-2px); }
        .sym-card:active { transform: scale(.94); }
        .sym-card.voted  { border-color: #4f46e5; background: #ede9fe; box-shadow: 0 4px 12px rgba(79,70,229,.25); }
        .sym-card img    { object-fit: contain; display: block;
                           transition: width .45s ease, height .45s ease; }
        .sym-lbl { text-align: center; color: #374151; margin-top: 6px; line-height: 1.2;
                   transition: font-size .45s ease; max-width: 120px; word-break: break-word; }
        .sym-card.voted .sym-lbl { color: #4f46e5; font-weight: 600; }
        .sym-votes { font-size: 11px; color: #9ca3af; margin-top: 2px; }

        /* ---- Wolken-Bereich ---- */
        .cloud-area {
            flex: 1 1 auto; overflow-y: auto;
            padding: 20px;
            display: flex; flex-wrap: wrap;
            gap: 14px; justify-content: center; align-content: flex-start;
        }
        .cloud-empty { text-align: center; color: #9ca3af; width: 100%; padding: 50px 20px; }
        .cloud-empty i { font-size: 3.5rem; display: block; margin-bottom: 10px; }

        /* ---- Status-Footer ---- */
        .statusbar {
            background: #fff; border-top: 1px solid #e5e7eb;
            padding: 8px 20px; display: flex; align-items: center;
            justify-content: space-between; flex-shrink: 0;
            font-size: 12px; color: #6b7280;
        }

        /* ---- Statische Seiten (Fehler/Geschlossen) ---- */
        .static-page { flex: 1; display: flex; flex-direction: column;
                        align-items: center; justify-content: center;
                        text-align: center; padding: 40px; }
        .static-page i { font-size: 4rem; display: block; margin-bottom: 16px; }
    </style>
</head>
<body>

<?php if (!$code || !$session): ?>
<!-- Kein/Ungültiger Code -->
<div class="static-page">
    <i class="bi bi-<?= $code ? 'emoji-frown' : 'chat-square-text-fill text-indigo' ?>"></i>
    <?php if ($code): ?>
        <h3 class="fw-bold">Code nicht gefunden</h3>
        <p class="text-muted">Der Code «<?= e($code) ?>» ist ungültig.</p>
    <?php else: ?>
        <h3 class="fw-bold"><?= e($appTitle) ?></h3>
        <p class="text-muted">Bitte Code eingeben:</p>
    <?php endif; ?>
    <form action="/join.php" method="GET" class="d-flex gap-2 mt-2">
        <input type="text" name="code" class="form-control form-control-lg font-monospace text-center"
               placeholder="XXXXXX" maxlength="8" style="width:180px;letter-spacing:4px;" autofocus>
        <button type="submit" class="btn btn-primary btn-lg">Öffnen</button>
    </form>
    <a href="/" class="text-muted small mt-3">← Zurück</a>
</div>

<?php elseif ($isClosed): ?>
<!-- Geschlossene Sitzung – Ergebnis zeigen -->
<div class="topbar">
    <div>
        <div class="topbar-title"><?= e($session['title']) ?></div>
        <div class="topbar-meta">Sitzung beendet</div>
    </div>
    <div class="code-badge"><?= e($session['session_code']) ?></div>
</div>
<div class="static-page">
    <i class="bi bi-trophy-fill text-warning"></i>
    <h4 class="fw-bold">Sitzung beendet – Ergebnis</h4>
    <div id="finalCloud" class="d-flex flex-wrap gap-3 justify-content-center mt-3" style="max-width:700px;"></div>
</div>
<script>
fetch('/api/data.php?session_id=<?= (int)$session['id'] ?>')
    .then(r => r.json())
    .then(data => {
        const c = document.getElementById('finalCloud');
        if (!data.items?.length) { c.innerHTML = '<p class="text-muted">Keine Stimmen vorhanden.</p>'; return; }
        const mx = Math.max(...data.items.map(i => +i.vote_count), 1);
        data.items.forEach(item => {
            const sz = calcSize(item.vote_count, mx);
            const d  = document.createElement('div');
            d.style.cssText = 'display:flex;flex-direction:column;align-items:center;padding:8px;';
            d.innerHTML = `<img src="https://static.arasaac.org/pictograms/${item.arasaac_id}/${item.arasaac_id}_300.png"
                style="width:${sz.img}px;height:${sz.img}px;object-fit:contain;" alt="">
                <span style="font-size:${sz.font}px;color:#374151;margin-top:4px;">${esc(item.label)}</span>
                <span style="font-size:10px;color:#9ca3af;">${item.vote_count} ×</span>`;
            c.appendChild(d);
        });
    });
function calcSize(v,mx){ const r=mx>0?v/mx:0; return {img:Math.round(60+r*80),font:Math.round(11+r*7)}; }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

<?php else: /* === AKTIVE SITZUNG === */ ?>

<!-- Top-Bar -->
<div class="topbar">
    <div>
        <div class="topbar-title"><?= e($session['title']) ?></div>
        <div class="topbar-meta">
            <?php
            echo match($mode) {
                'symbols' => 'Vorgegebene Symbole',
                'search'  => 'Freie Symbolsuche',
                default   => 'Symbole + Suche',
            };
            ?>
        </div>
    </div>
    <div class="topbar-right">
        <div class="live-pill">
            <span class="live-dot"></span>
            <span id="liveCount">Live</span>
        </div>
        <div class="code-badge"><?= e($session['session_code']) ?></div>
    </div>
</div>

<?php if ($mode === 'search' || $mode === 'both'): ?>
<!-- Suchleiste -->
<div class="searchbar">
    <div class="d-flex gap-2">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-indigo"></i>
            </span>
            <input type="text" id="searchInput" class="form-control border-start-0 ps-0"
                   placeholder="Symbol suchen und zur Wolke hinzufügen …"
                   autocomplete="off" style="font-size:.95rem;">
        </div>
        <select id="searchLang" class="form-select" style="max-width:105px;">
            <option value="de">🇩🇪 DE</option>
            <option value="en">🇬🇧 EN</option>
            <option value="es">🇪🇸 ES</option>
            <option value="fr">🇫🇷 FR</option>
        </select>
    </div>
    <div id="searchSpinner" class="py-1 d-none">
        <div class="spinner-border spinner-border-sm text-indigo"></div>
    </div>
    <div id="searchDrop" class="search-drop"></div>
</div>
<?php endif; ?>

<?php if (($mode === 'symbols' || $mode === 'both') && !empty($session['predefined_symbols'])): ?>
<!-- Preset-Symbole -->
<div class="presetbar">
    <div class="presetbar-label">
        <i class="bi bi-images me-1"></i>Symbole – anklicken zum Abstimmen
    </div>
    <div class="preset-row" id="presetRow">
        <?php foreach ($session['predefined_symbols'] as $sym): ?>
        <?php $aid = (int)$sym['arasaac_id']; ?>
        <div class="sym-card" id="p<?= $aid ?>"
             data-aid="<?= $aid ?>" data-label="<?= e($sym['label']) ?>"
             onclick="toggleVote(<?= $aid ?>, <?= json_encode($sym['label']) ?>)">
            <img src="<?= WordCloudManager::ARASAAC_CDN ?>/<?= $aid ?>/<?= $aid ?>_300.png"
                 width="72" height="72" alt="<?= e($sym['label']) ?>" loading="lazy">
            <span class="sym-lbl" style="font-size:12px;"><?= e($sym['label']) ?></span>
            <span class="sym-votes" id="pv<?= $aid ?>"></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Wortwolke -->
<div class="cloud-area" id="cloudArea">
    <div class="cloud-empty" id="cloudEmpty">
        <i class="bi bi-cursor-fill"></i>
        <p>
            <?= match($mode) {
                'symbols' => 'Klicke auf ein Symbol oben, um abzustimmen.',
                'search'  => 'Suche nach einem Symbol und klicke darauf.',
                default   => 'Klicke auf ein Symbol oder suche nach einem neuen.',
            } ?>
        </p>
    </div>
</div>

<!-- Statuszeile -->
<div class="statusbar">
    <span id="myVotesInfo">Noch keine Stimme abgegeben.</span>
    <span id="lastUpdate" class="text-muted"></span>
</div>

<!-- Konfiguration & Runtime-JS -->
<script>
(function() {
    'use strict';

    const SESSION_ID = <?= (int)$session['id'] ?>;
    const MODE       = <?= json_encode($mode) ?>;
    const CDN        = 'https://static.arasaac.org/pictograms';
    const PRESETS    = <?= $presetsJson ?>;
    const POLL_MS    = <?= defined('POLL_MS') ? (int)POLL_MS : 3000 ?>;

    /* ---- Teilnehmer-Token ---- */
    let token = localStorage.getItem('wc_token_' + SESSION_ID);
    if (!token) {
        token = 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = crypto.getRandomValues(new Uint8Array(1))[0] & 0xf;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        localStorage.setItem('wc_token_' + SESSION_ID, token);
    }

    /* ---- Zustand ---- */
    let myVotes    = new Set();
    let searchTimer = null;

    /* ---- Suche ---- */
    const searchInput = document.getElementById('searchInput');
    const searchDrop  = document.getElementById('searchDrop');
    const searchSpin  = document.getElementById('searchSpinner');
    const searchLang  = document.getElementById('searchLang');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            searchDrop.innerHTML = '';
            if (q.length < 2) return;
            searchTimer = setTimeout(() => doSearch(q), 380);
        });
    }

    function doSearch(q) {
        if (searchSpin) searchSpin.classList.remove('d-none');
        const lang = searchLang?.value || 'de';
        fetch('/api/search.php?q=' + encodeURIComponent(q) + '&lang=' + lang)
            .then(r => r.json())
            .then(data => {
                if (searchSpin) searchSpin.classList.add('d-none');
                searchDrop.innerHTML = '';
                if (!data.length) {
                    searchDrop.innerHTML = '<small class="text-muted p-1">Keine Treffer.</small>';
                    return;
                }
                data.forEach(sym => {
                    const d = document.createElement('div');
                    d.className = 'search-item';
                    d.innerHTML = `<img src="${sym.image_url}" alt="" loading="lazy">
                        <span class="kw">${esc(sym.keywords[0] || '')}</span>`;
                    d.title = sym.keywords.slice(0, 4).join(', ');
                    d.addEventListener('click', () => {
                        toggleVote(sym.id, sym.keywords[0] || '');
                        if (searchInput) searchInput.value = '';
                        searchDrop.innerHTML = '';
                    });
                    searchDrop.appendChild(d);
                });
            })
            .catch(() => {
                if (searchSpin) searchSpin.classList.add('d-none');
                searchDrop.innerHTML = '<small class="text-danger p-1">Suche fehlgeschlagen.</small>';
            });
    }

    /* ---- Abstimmen ---- */
    function toggleVote(arasaacId, label) {
        fetch('/api/vote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: SESSION_ID, token, arasaac_id: arasaacId, label }),
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { if (res.error) alert(res.error); return; }
            res.voted ? myVotes.add(arasaacId) : myVotes.delete(arasaacId);
            updateVoteUI();
            pollCloud();
        })
        .catch(() => alert('Verbindungsfehler beim Abstimmen.'));
    }

    /* ---- Polling ---- */
    function pollCloud() {
        fetch('/api/data.php?session_id=' + SESSION_ID + '&token=' + encodeURIComponent(token))
            .then(r => r.json())
            .then(data => {
                if (data.status === 'closed') { location.reload(); return; }
                myVotes = new Set((data.my_votes || []).map(Number));
                renderCloud(data.items || []);
                updateVoteUI();
                updateLive(data.participants || 0);
                const el = document.getElementById('lastUpdate');
                if (el) el.textContent = 'Zuletzt: ' + new Date().toLocaleTimeString('de');
            })
            .catch(() => {});
    }

    /* ---- Cloud rendern ---- */
    function renderCloud(items) {
        const area  = document.getElementById('cloudArea');
        const empty = document.getElementById('cloudEmpty');

        if (MODE === 'symbols') {
            // Nur Preset-Karten aktualisieren – keine separate Wolke nötig
            const mx = Math.max(...items.map(i => +i.vote_count), 0);
            items.forEach(item => {
                const aid  = +item.arasaac_id;
                const card = document.getElementById('p' + aid);
                if (!card) return;
                const sz   = calcSize(item.vote_count, mx);
                applySize(card, sz);
                const v = card.querySelector('.sym-votes');
                if (v) v.textContent = item.vote_count > 0 ? item.vote_count + ' ×' : '';
            });
            // Karten ohne Stimme auf Standardgröße
            PRESETS.forEach(p => {
                if (!items.some(i => +i.arasaac_id === p.id)) {
                    const card = document.getElementById('p' + p.id);
                    if (card) applySize(card, calcSize(0, 1));
                }
            });
            empty.classList.add('d-none');
            return;
        }

        // such/both: Wolken-Karten im cloud-area
        if (!items.length) {
            empty.classList.remove('d-none');
            area.querySelectorAll('.sym-card').forEach(c => c.remove());
            return;
        }
        empty.classList.add('d-none');
        const mx = Math.max(...items.map(i => +i.vote_count), 1);

        items.forEach(item => {
            const aid  = +item.arasaac_id;
            const sz   = calcSize(item.vote_count, mx);
            let   card = document.getElementById('c' + aid);

            if (!card) {
                card = document.createElement('div');
                card.className = 'sym-card';
                card.id        = 'c' + aid;
                card.dataset.aid = aid;
                card.innerHTML = `
                    <img src="${CDN}/${aid}/${aid}_300.png" alt="" loading="lazy"
                         width="${sz.img}" height="${sz.img}" style="width:${sz.img}px;height:${sz.img}px;">
                    <span class="sym-lbl" style="font-size:${sz.font}px;">${esc(item.label)}</span>
                    <span class="sym-votes">${item.vote_count} ×</span>`;
                card.addEventListener('click', () => toggleVote(aid, item.label));
                area.appendChild(card);
            } else {
                applySize(card, sz);
                const lbl = card.querySelector('.sym-lbl');
                const vc  = card.querySelector('.sym-votes');
                if (lbl) lbl.style.fontSize = sz.font + 'px';
                if (vc)  vc.textContent     = item.vote_count + ' ×';
            }
            card.classList.toggle('voted', myVotes.has(aid));
        });

        // Karten ohne Stimme entfernen
        area.querySelectorAll('.sym-card').forEach(card => {
            if (!items.some(i => +i.arasaac_id === +card.dataset.aid)) card.remove();
        });
    }

    function applySize(card, sz) {
        const img = card.querySelector('img');
        const lbl = card.querySelector('.sym-lbl');
        if (img) { img.style.width = sz.img + 'px'; img.style.height = sz.img + 'px'; }
        if (lbl) lbl.style.fontSize = sz.font + 'px';
    }

    function updateVoteUI() {
        // Preset-Karten
        PRESETS.forEach(p => {
            const card = document.getElementById('p' + p.id);
            if (card) card.classList.toggle('voted', myVotes.has(p.id));
        });
        // Cloud-Karten
        document.querySelectorAll('[id^="c"]').forEach(card => {
            const aid = +card.dataset.aid;
            if (aid) card.classList.toggle('voted', myVotes.has(aid));
        });

        const n   = myVotes.size;
        const el  = document.getElementById('myVotesInfo');
        if (el) el.textContent = n === 0
            ? 'Noch keine Stimme – klicke auf ein Symbol.'
            : n === 1 ? '1 Symbol gewählt · Nochmals klicken = zurückziehen.'
                      : n + ' Symbole gewählt · Nochmals klicken = zurückziehen.';
    }

    function updateLive(n) {
        const el = document.getElementById('liveCount');
        if (el) el.textContent = n + (n === 1 ? ' Teilnehmer live' : ' Teilnehmer live');
    }

    function calcSize(votes, mx) {
        const r = mx > 0 ? +votes / mx : 0;
        return { img: Math.round(62 + r * 98), font: Math.round(11 + r * 9) };
    }

    function esc(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ---- Start ---- */
    pollCloud();
    setInterval(pollCloud, POLL_MS);
})();
</script>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
