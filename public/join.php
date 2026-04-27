<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

$code    = strtoupper(preg_replace('/[^A-Z0-9]/', '', get('code', '')));
$mgr     = new WordCloudManager();
$session = $code ? $mgr->getSessionByCode($code) : null;

$appTitle = appTitle();
$mode     = $session['mode']   ?? '';
$isClosed = $session           && $session['status'] === 'closed';
$isActive = $session           && $session['status'] === 'active';

$displayMode = 'cloud';
if ($session) {
    $dm = $session['display_mode'] ?? 'cloud';
    $displayMode = in_array($dm, ['cloud', 'list', 'umfrage']) ? $dm : 'cloud';
}

// Presets als JSON für JS (image_url für eigene Bilder mitgeben)
$presetsJson = $session
    ? json_encode(
        array_map(fn($s) => [
            'id'        => (int) $s['arasaac_id'],
            'label'     => $s['label'],
            'image_url' => $s['image_url'] ?? null,
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
            padding: 10px 8px; border-radius: 14px; border: 2px solid #e5e7eb;
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

        /* ---- Umfrage-Darstellung ---- */
        .survey-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; margin-bottom: 8px;
            background: #fff; border-radius: 12px; border: 2px solid #e5e7eb;
            cursor: pointer; user-select: none;
            transition: border-color .2s, background .2s;
            -webkit-tap-highlight-color: transparent;
        }
        .survey-row:hover  { border-color: #a5b4fc; background: #f5f3ff; }
        .survey-row:active { transform: scale(.99); }
        .survey-row.voted  { border-color: #4f46e5; background: #ede9fe; }
        .survey-rank { font-size: 1.5rem; font-weight: 800; min-width: 44px;
                       text-align: center; color: #6b7280; flex-shrink: 0; }
        .survey-rank.top1 { color: #d97706; }
        .survey-rank.top2 { color: #9ca3af; }
        .survey-rank.top3 { color: #92400e; }
        .survey-img  { width: 54px; height: 54px; object-fit: contain; flex-shrink: 0; }
        .survey-info { flex: 1; min-width: 0; }
        .survey-label { font-weight: 600; font-size: .95rem; color: #374151; margin-bottom: 5px;
                        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .survey-row.voted .survey-label { color: #4f46e5; }
        .survey-bar-wrap { background: #e5e7eb; border-radius: 4px; height: 10px; }
        .survey-bar { background: #4f46e5; border-radius: 4px; height: 10px;
                      transition: width .6s ease; min-width: 4px; }
        .survey-votes { text-align: right; font-size: 1.3rem; font-weight: 700;
                        color: #374151; min-width: 52px; flex-shrink: 0; line-height: 1.1; }
        .survey-votes-lbl { display: block; font-size: 10px; font-weight: 400; color: #9ca3af; }

        /* ---- Wolken-Bereich ---- */
        .cloud-area {
            flex: 1 1 auto; overflow: hidden;
            position: relative;
        }
        #cloudCanvas {
            position: absolute; left: 0; top: 0;
            transform-origin: 0 0;
        }
        .cloud-empty { text-align: center; color: #9ca3af;
                       position: absolute; top: 50%; left: 50%;
                       transform: translate(-50%,-50%); width: 80%; pointer-events: none; }
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
    <div class="d-flex gap-3 mt-4 align-items-center flex-wrap justify-content-center">
        <button id="exportPngClosed" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>PNG exportieren
        </button>
        <button onclick="document.getElementById('arasaacModalClosed').style.display='flex'"
                style="background:none;border:none;font-size:12px;color:#9ca3af;cursor:pointer;">
            © ARASAAC – Lizenz &amp; Symbole
        </button>
    </div>
</div>

<!-- ARASAAC-Modal (geschlossene Sitzung) -->
<div id="arasaacModalClosed" style="
        display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1001;
        align-items:center;justify-content:center;"
     onclick="this.style.display='none'">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:560px;width:92%;
                max-height:85vh;overflow-y:auto;"
         onclick="event.stopPropagation()">
        <h5 style="font-weight:700;margin-bottom:4px;">
            <i class="bi bi-info-circle-fill text-primary me-2"></i>ARASAAC-Symbole
        </h5>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">
            Piktogramme von <a href="https://arasaac.org" target="_blank" rel="noopener">ARASAAC</a>
            unter <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noopener">CC BY-NC-SA 4.0</a>.
            Autor: Sergio Palao · © Government of Aragón (Spain)
        </p>
        <div id="arasaacClosedList" style="display:flex;flex-wrap:wrap;gap:12px;"></div>
        <button onclick="document.getElementById('arasaacModalClosed').style.display='none'"
                style="margin-top:20px;background:#f3f4f6;border:none;border-radius:8px;
                       padding:8px 20px;cursor:pointer;">Schließen</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
let closedItems = [];
fetch('/api/data.php?session_id=<?= (int)$session['id'] ?>')
    .then(r => r.json())
    .then(data => {
        closedItems = data.items || [];
        const c = document.getElementById('finalCloud');
        if (!closedItems.length) { c.innerHTML = '<p class="text-muted">Keine Stimmen vorhanden.</p>'; return; }
        const mx = Math.max(...closedItems.map(i => +i.vote_count), 1);
        closedItems.forEach(item => {
            const sz     = calcSize(item.vote_count, mx);
            const imgSrc = item.image_url
                || `https://static.arasaac.org/pictograms/${item.arasaac_id}/${item.arasaac_id}_300.png`;
            const d = document.createElement('div');
            d.style.cssText = 'display:flex;flex-direction:column;align-items:center;padding:8px;';
            d.innerHTML = `<img src="${imgSrc}"
                style="width:${sz.img}px;height:${sz.img}px;object-fit:contain;" alt="">
                <span style="font-size:${sz.font}px;color:#374151;margin-top:4px;">${esc(item.label)}</span>
                <span style="font-size:10px;color:#9ca3af;">${item.vote_count} ×</span>`;
            c.appendChild(d);
        });
    });

document.getElementById('exportPngClosed').addEventListener('click', function() {
    const area = document.getElementById('finalCloud');
    html2canvas(area, { backgroundColor: '#ffffff', useCORS: true }).then(canvas => {
        const a = document.createElement('a');
        a.href = canvas.toDataURL('image/png');
        a.download = 'ergebnis.png';
        a.click();
    });
});

document.querySelector('[onclick*="arasaacModalClosed"]').addEventListener('click', function() {
    const list = document.getElementById('arasaacClosedList');
    list.innerHTML = '';
    const seen = new Set();
    closedItems.forEach(item => {
        const aid = +item.arasaac_id;
        if (aid <= 0 || seen.has(aid)) return;
        seen.add(aid);
        const d = document.createElement('div');
        d.style.cssText = 'display:flex;flex-direction:column;align-items:center;width:72px;text-align:center;';
        d.innerHTML = `<img src="https://static.arasaac.org/pictograms/${aid}/${aid}_300.png"
            style="width:52px;height:52px;object-fit:contain;" loading="lazy" alt="">
            <span style="font-size:10px;color:#374151;margin-top:4px;word-break:break-word;">${esc(item.label)}</span>`;
        list.appendChild(d);
    });
    if (!seen.size) list.innerHTML = '<p style="color:#9ca3af;font-size:13px;">Keine ARASAAC-Symbole.</p>';
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
        <button onclick="document.getElementById('qrOverlay').style.display='flex'"
                style="background:rgba(255,255,255,.15);border:none;border-radius:8px;
                       color:#fff;padding:5px 10px;cursor:pointer;font-size:13px;"
                title="QR-Code & Link anzeigen">
            <i class="bi bi-qr-code"></i>
        </button>
    </div>
</div>

<!-- QR-/Link-Overlay -->
<div id="qrOverlay" style="
        display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
        align-items:center;justify-content:center;flex-direction:column;gap:16px;"
     onclick="this.style.display='none'">
    <div style="background:#fff;border-radius:16px;padding:28px 32px;text-align:center;max-width:340px;width:90%;"
         onclick="event.stopPropagation()">
        <div style="font-size:13px;color:#6b7280;margin-bottom:4px;">Teilnehmer-Link</div>
        <div style="font-size:15px;font-weight:600;word-break:break-all;color:#4f46e5;margin-bottom:16px;"
             id="qrUrlText"></div>
        <img id="qrImgLive" src="" alt="QR-Code" style="width:220px;height:220px;display:block;margin:0 auto 16px;">
        <div style="font-size:11px;color:#9ca3af;">Zum Schließen irgendwo klicken</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script>
(function(){
    const joinUrl = <?= json_encode('https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/join.php?code=' . $session['session_code']) ?>;
    document.getElementById('qrUrlText').textContent = joinUrl;
    const qr = qrcode(0, 'M');
    qr.addData(joinUrl);
    qr.make();
    document.getElementById('qrImgLive').src = qr.createDataURL(6, 0);
})();
</script>

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
        <?php
            $aid    = (int)$sym['arasaac_id'];
            $imgUrl = !empty($sym['image_url'])
                ? $sym['image_url']
                : WordCloudManager::ARASAAC_CDN . '/' . $aid . '/' . $aid . '_300.png';
        ?>
        <div class="sym-card" id="p<?= $aid ?>"
             data-aid="<?= $aid ?>" data-label="<?= e($sym['label']) ?>">
            <img src="<?= e($imgUrl) ?>"
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
    <div id="cloudCanvas"></div>
</div>

<!-- Statuszeile -->
<div class="statusbar">
    <span id="myVotesInfo">Noch keine Stimme abgegeben.</span>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span id="lastUpdate" class="text-muted"></span>
        <div class="btn-group">
            <button id="zoomOutBtn" class="btn btn-xs btn-outline-secondary py-0 px-2"
                    style="font-size:11px;" title="Verkleinern">
                <i class="bi bi-zoom-out"></i>
            </button>
            <button id="zoomFitBtn" class="btn btn-xs btn-outline-secondary py-0 px-2"
                    style="font-size:11px;" title="Einpassen">
                <i class="bi bi-fullscreen"></i>
            </button>
            <button id="zoomInBtn" class="btn btn-xs btn-outline-secondary py-0 px-2"
                    style="font-size:11px;" title="Vergrößern">
                <i class="bi bi-zoom-in"></i>
            </button>
        </div>
        <button id="exportPngBtn" class="btn btn-xs btn-outline-secondary py-0 px-2"
                style="font-size:11px;" title="Wolke als PNG speichern">
            <i class="bi bi-download me-1"></i>PNG
        </button>
        <button onclick="document.getElementById('arasaacModal').style.display='flex'"
                style="background:none;border:none;padding:0;font-size:11px;color:#9ca3af;cursor:pointer;"
                title="ARASAAC-Symbole – Lizenzinfo anzeigen">
            © ARASAAC
        </button>
    </div>
</div>

<!-- ARASAAC-Lizenz-Modal -->
<div id="arasaacModal" style="
        display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1001;
        align-items:center;justify-content:center;"
     onclick="this.style.display='none'">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:560px;width:92%;
                max-height:85vh;overflow-y:auto;"
         onclick="event.stopPropagation()">
        <h5 style="font-weight:700;margin-bottom:4px;">
            <i class="bi bi-info-circle-fill text-primary me-2"></i>ARASAAC-Symbole
        </h5>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">
            Die Piktogramme stammen von
            <a href="https://arasaac.org" target="_blank" rel="noopener">ARASAAC</a>
            (Aragonese Portal of Augmentative and Alternative Communication) und werden unter der Lizenz
            <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noopener">
                CC BY-NC-SA 4.0</a> bereitgestellt.<br>
            Autor: Sergio Palao · © Government of Aragón (Spain)
        </p>
        <div id="arasaacSymbolList" style="display:flex;flex-wrap:wrap;gap:12px;"></div>
        <button onclick="document.getElementById('arasaacModal').style.display='none'"
                style="margin-top:20px;background:#f3f4f6;border:none;border-radius:8px;
                       padding:8px 20px;cursor:pointer;font-size:14px;">
            Schließen
        </button>
    </div>
</div>

<!-- Konfiguration & Runtime-JS -->
<script>
(function() {
    'use strict';

    const SESSION_ID   = <?= (int)$session['id'] ?>;
    const MODE         = <?= json_encode($mode) ?>;
    const CDN          = 'https://static.arasaac.org/pictograms';
    const PRESETS      = <?= $presetsJson ?>;
    const POLL_MS      = <?= defined('POLL_MS') ? (int)POLL_MS : 3000 ?>;
    const DISPLAY_MODE = <?= json_encode($displayMode) ?>;

    let currentZoom = 1.0;

    function autoFit() {
        const area   = document.getElementById('cloudArea');
        const canvas = document.getElementById('cloudCanvas');
        if (!canvas) return;
        const areaW = area.clientWidth  || 600;
        const areaH = area.clientHeight || 400;

        if (DISPLAY_MODE === 'list' || DISPLAY_MODE === 'umfrage') {
            const cntW = canvas.offsetWidth  || areaW;
            const cntH = canvas.offsetHeight || areaH;
            if (!cntH) return;
            const base  = Math.min(areaW / (cntW + 4), areaH / (cntH + 4));
            const scale = Math.max(0.15, Math.min(base * currentZoom, 4));
            const tx    = Math.round((areaW - cntW * scale) / 2);
            const ty    = Math.round((areaH - cntH * scale) / 2);
            canvas.style.transform = `translate(${tx}px,${ty}px) scale(${scale})`;
            return;
        }

        // Cloud-Modus
        if (!cloudLayout.size) return;
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        cloudLayout.forEach((pos, aid) => {
            const c = document.getElementById('c' + aid);
            if (!c) return;
            minX = Math.min(minX, pos.x);
            minY = Math.min(minY, pos.y);
            maxX = Math.max(maxX, pos.x + (c.offsetWidth  || 100));
            maxY = Math.max(maxY, pos.y + (c.offsetHeight || 120));
        });
        if (!isFinite(minX)) return;

        const PAD   = 16;
        const cntW  = (maxX - minX) + 2 * PAD;
        const cntH  = (maxY - minY) + 2 * PAD;
        const base  = Math.min(areaW / cntW, areaH / cntH);
        const scale = Math.max(0.15, Math.min(base * currentZoom, 4));
        const tx    = Math.round(areaW / 2 - (maxX + minX) / 2 * scale);
        const ty    = Math.round(areaH / 2 - (maxY + minY) / 2 * scale);
        canvas.style.transform = `translate(${tx}px,${ty}px) scale(${scale})`;
    }

    // Mapping eigener Bild-IDs (negativ) → URL
    const imageUrlMap = {};
    PRESETS.forEach(p => { if (p.image_url) imageUrlMap[p.id] = p.image_url; });
    function getImageUrl(aid) {
        return imageUrlMap[aid] || `${CDN}/${aid}/${aid}_300.png`;
    }

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
                lastCloudItems = data.items || [];
                renderCloud(lastCloudItems);
                updateVoteUI();
                updateLive(data.participants || 0);
                const el = document.getElementById('lastUpdate');
                if (el) el.textContent = 'Zuletzt: ' + new Date().toLocaleTimeString('de');
            })
            .catch(() => {});
    }

    /* ---- Positionen persistent (neu platzieren nur bei neuen Karten) ---- */
    const cloudLayout = new Map();

    /* ---- Reihen-Darstellung (von groß nach klein) ---- */
    function renderList(items) {
        const area   = document.getElementById('cloudArea');
        const canvas = document.getElementById('cloudCanvas');
        const empty  = document.getElementById('cloudEmpty');

        if (!items.length) {
            empty.classList.remove('d-none');
            canvas.innerHTML = '';
            canvas.style.cssText = 'position:absolute;left:0;top:0;transform-origin:0 0;';
            return;
        }
        empty.classList.add('d-none');

        const areaW = area.clientWidth || 600;
        canvas.style.cssText =
            `position:absolute;left:0;top:0;transform-origin:0 0;width:${areaW}px;` +
            `display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:center;` +
            `gap:10px;padding:16px;box-sizing:border-box;`;

        const mx     = Math.max(...items.map(i => +i.vote_count), 1);
        const sorted = [...items].sort((a, b) => b.vote_count - a.vote_count);

        canvas.innerHTML = '';
        sorted.forEach(item => {
            const aid  = +item.arasaac_id;
            const sz   = calcSize(item.vote_count, mx);
            const card = document.createElement('div');
            card.className   = 'sym-card';
            card.id          = 'c' + aid;
            card.dataset.aid = String(aid);
            card.innerHTML   = `
                <img src="${getImageUrl(aid)}" alt="" loading="lazy"
                     style="width:${sz.img}px;height:${sz.img}px;object-fit:contain;">
                <span class="sym-lbl" style="font-size:${sz.font}px;">${esc(item.label)}</span>
                <span class="sym-votes">${item.vote_count} ×</span>`;
            card.classList.toggle('voted', myVotes.has(aid));
            card.addEventListener('click', () => toggleVote(aid, item.label));
            canvas.appendChild(card);
        });

        requestAnimationFrame(autoFit);
    }

    /* ---- Umfrage-Darstellung (Rangliste) ---- */
    function renderSurvey(items) {
        const area   = document.getElementById('cloudArea');
        const canvas = document.getElementById('cloudCanvas');
        const empty  = document.getElementById('cloudEmpty');

        if (!items.length) {
            empty.classList.remove('d-none');
            canvas.innerHTML = '';
            canvas.style.cssText = 'position:absolute;left:0;top:0;transform-origin:0 0;';
            return;
        }
        empty.classList.add('d-none');

        const areaW  = area.clientWidth || 600;
        canvas.style.cssText =
            `position:absolute;left:0;top:0;transform-origin:0 0;width:${areaW}px;` +
            `padding:12px 16px;box-sizing:border-box;`;

        const mx     = Math.max(...items.map(i => +i.vote_count), 1);
        const sorted = [...items].sort((a, b) => b.vote_count - a.vote_count);
        const medals = ['🥇', '🥈', '🥉'];

        canvas.innerHTML = '';
        sorted.forEach((item, idx) => {
            const aid   = +item.arasaac_id;
            const votes = +item.vote_count;
            const pct   = mx > 0 ? Math.round(votes / mx * 100) : 0;
            const rank  = idx + 1;
            const topCls = rank <= 3 ? ' top' + rank : '';

            const row = document.createElement('div');
            row.id          = 'c' + aid;
            row.dataset.aid = String(aid);
            row.className   = 'survey-row' + (myVotes.has(aid) ? ' voted' : '');
            row.innerHTML   =
                `<div class="survey-rank${topCls}">${rank <= 3 ? medals[rank - 1] : rank + '.'}</div>` +
                `<img src="${getImageUrl(aid)}" class="survey-img" alt="" loading="lazy">` +
                `<div class="survey-info">` +
                    `<div class="survey-label">${esc(item.label)}</div>` +
                    `<div class="survey-bar-wrap"><div class="survey-bar" style="width:${pct}%"></div></div>` +
                `</div>` +
                `<div class="survey-votes">${votes}<span class="survey-votes-lbl">Stimmen</span></div>`;
            row.addEventListener('click', () => toggleVote(aid, item.label));
            canvas.appendChild(row);
        });

        requestAnimationFrame(autoFit);
    }

    /* ---- Cloud rendern (Dispatcher) ---- */
    function renderCloud(items) {
        if (DISPLAY_MODE === 'list')    { renderList(items);   return; }
        if (DISPLAY_MODE === 'umfrage') { renderSurvey(items); return; }
        const area   = document.getElementById('cloudArea');
        const canvas = document.getElementById('cloudCanvas');
        const empty  = document.getElementById('cloudEmpty');

        // Preset-Karten: nur Stimmenzahl aktualisieren, Größe bleibt fest
        if (MODE === 'symbols' || MODE === 'both') {
            items.forEach(item => {
                const v = document.getElementById('pv' + item.arasaac_id);
                if (v) v.textContent = +item.vote_count > 0 ? item.vote_count + ' ×' : '';
            });
            PRESETS.forEach(p => {
                if (!items.some(i => +i.arasaac_id === p.id)) {
                    const v = document.getElementById('pv' + p.id);
                    if (v) v.textContent = '';
                }
            });
        }

        if (!items.length) {
            empty.classList.remove('d-none');
            canvas.querySelectorAll('.sym-card').forEach(c => c.remove());
            cloudLayout.clear();
            canvas.style.transform = '';
            return;
        }
        empty.classList.add('d-none');

        const mx     = Math.max(...items.map(i => +i.vote_count), 1);
        const sorted = [...items].sort((a, b) => b.vote_count - a.vote_count);
        const newAids = [];

        sorted.forEach(item => {
            const aid = +item.arasaac_id;
            const sz  = calcSize(item.vote_count, mx);
            let card  = document.getElementById('c' + aid);

            if (!card) {
                card = document.createElement('div');
                card.className   = 'sym-card';
                card.id          = 'c' + aid;
                card.dataset.aid = String(aid);
                card.style.cssText = 'position:absolute;visibility:hidden;';
                card.innerHTML = `
                    <img src="${getImageUrl(aid)}" alt="" loading="lazy"
                         style="width:${sz.img}px;height:${sz.img}px;object-fit:contain;">
                    <span class="sym-lbl" style="font-size:${sz.font}px;">${esc(item.label)}</span>
                    <span class="sym-votes">${item.vote_count} ×</span>`;
                card.addEventListener('click', () => toggleVote(aid, item.label));
                canvas.appendChild(card);
                newAids.push(aid);
            } else {
                applySize(card, sz);
                const lbl = card.querySelector('.sym-lbl');
                const vc  = card.querySelector('.sym-votes');
                if (lbl) lbl.style.fontSize = sz.font + 'px';
                if (vc)  vc.textContent     = item.vote_count + ' ×';
                if (cloudLayout.has(aid)) {
                    const pos = cloudLayout.get(aid);
                    card.style.left = pos.x + 'px';
                    card.style.top  = pos.y + 'px';
                    card.style.visibility = 'visible';
                }
            }
            card.classList.toggle('voted', myVotes.has(aid));
        });

        // Karten ohne Stimme entfernen
        canvas.querySelectorAll('.sym-card').forEach(card => {
            const aid = +card.dataset.aid;
            if (!items.some(i => +i.arasaac_id === aid)) {
                card.remove();
                cloudLayout.delete(aid);
            }
        });

        if (newAids.length) {
            requestAnimationFrame(() => { placeItems(sorted, newAids); autoFit(); });
        } else {
            // Keine neuen Karten, aber Größen könnten sich geändert haben
            requestAnimationFrame(autoFit);
        }
    }

    /* ---- Neue Karten spiralförmig platzieren (größte zuerst = Mitte) ---- */
    function placeItems(sorted, newAids) {
        const area  = document.getElementById('cloudArea');
        const areaW = area.clientWidth  || 600;
        const areaH = area.clientHeight || 400;
        const cx    = Math.round(areaW / 2);
        const cy    = Math.round(areaH / 2);
        const pad   = 12;

        // Bereits platzierte Karten aufnehmen
        const placed = [];
        cloudLayout.forEach((pos, aid) => {
            const c = document.getElementById('c' + aid);
            if (c) placed.push({x: pos.x, y: pos.y, w: c.offsetWidth, h: c.offsetHeight});
        });

        sorted.forEach(item => {
            const aid  = +item.arasaac_id;
            if (!newAids.includes(aid)) return;

            const card = document.getElementById('c' + aid);
            if (!card) return;

            const w  = card.offsetWidth  || 100;
            const h  = card.offsetHeight || 120;
            let tx, ty, ok = false;

            if (placed.length === 0) {
                // Erstes (größtes) Symbol: Mitte
                tx = cx - Math.round(w / 2);
                ty = cy - Math.round(h / 2);
                ok = true;
            } else {
                for (let r = 70; r < 1200 && !ok; r += 18) {
                    const steps = Math.max(8, Math.ceil(2 * Math.PI * r / 55));
                    for (let s = 0; s < steps && !ok; s++) {
                        const angle = 2 * Math.PI * s / steps;
                        tx = cx + Math.round(r * Math.cos(angle)) - Math.round(w / 2);
                        ty = cy + Math.round(r * Math.sin(angle)) - Math.round(h / 2);
                        if (!placed.some(p =>
                            tx < p.x + p.w + pad && tx + w + pad > p.x &&
                            ty < p.y + p.h + pad && ty + h + pad > p.y
                        )) ok = true;
                    }
                }
                if (!ok) { tx = cx + placed.length * 10; ty = cy + placed.length * 10; }
            }

            placed.push({x: tx, y: ty, w, h});
            cloudLayout.set(aid, {x: tx, y: ty});
            card.style.left = Math.max(0, tx) + 'px';
            card.style.top  = Math.max(0, ty) + 'px';
            card.style.visibility = 'visible';
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

    /* ---- Preset-Karten: Click-Handler ---- */
    document.querySelectorAll('#presetRow .sym-card').forEach(card => {
        card.addEventListener('click', () => {
            toggleVote(+card.dataset.aid, card.dataset.label);
        });
    });

    /* ---- ARASAAC-Symbolliste im Modal ---- */
    let lastCloudItems = [];
    document.getElementById('arasaacModal')?.addEventListener('click', function(e) {
        if (e.target !== this) return;
    });
    document.querySelector('[onclick*="arasaacModal"]')?.addEventListener('click', function() {
        const list = document.getElementById('arasaacSymbolList');
        if (!list) return;
        list.innerHTML = '';
        // ARASAAC-Symbole = positive IDs aus PRESETS + Cloud
        const seen = new Set();
        const add = (id, label) => {
            if (id <= 0 || seen.has(id)) return;
            seen.add(id);
            const d = document.createElement('div');
            d.style.cssText = 'display:flex;flex-direction:column;align-items:center;width:72px;text-align:center;';
            d.innerHTML = `<img src="${CDN}/${id}/${id}_300.png"
                style="width:52px;height:52px;object-fit:contain;" loading="lazy" alt="">
                <span style="font-size:10px;color:#374151;margin-top:4px;word-break:break-word;">${esc(label)}</span>`;
            list.appendChild(d);
        };
        PRESETS.forEach(p => add(p.id, p.label));
        lastCloudItems.forEach(i => add(+i.arasaac_id, i.label));
        if (!seen.size) list.innerHTML = '<p style="color:#9ca3af;font-size:13px;">Keine ARASAAC-Symbole in dieser Sitzung.</p>';
    });

    /* ---- PNG-Export ---- */
    document.getElementById('exportPngBtn')?.addEventListener('click', function() {
        const area = document.getElementById('cloudArea');
        if (!area) return;
        if (typeof html2canvas === 'undefined') {
            alert('html2canvas nicht geladen.');
            return;
        }
        html2canvas(area, { backgroundColor: '#f0f2f8', useCORS: true }).then(canvas => {
            const a = document.createElement('a');
            a.href     = canvas.toDataURL('image/png');
            a.download = 'wortwolke.png';
            a.click();
        });
    });

    /* ---- Zoom-Steuerung ---- */
    document.getElementById('zoomInBtn')?.addEventListener('click', () => {
        currentZoom = Math.min(currentZoom * 1.25, 4);
        autoFit();
    });
    document.getElementById('zoomOutBtn')?.addEventListener('click', () => {
        currentZoom = Math.max(currentZoom * 0.8, 0.15);
        autoFit();
    });
    document.getElementById('zoomFitBtn')?.addEventListener('click', () => {
        currentZoom = 1.0;
        autoFit();
    });
    window.addEventListener('resize', () => {
        if (DISPLAY_MODE === 'list' || DISPLAY_MODE === 'umfrage') {
            if (lastCloudItems.length) renderCloud(lastCloudItems);
        } else if (cloudLayout.size) {
            autoFit();
        }
    });

    /* ---- Start ---- */
    pollCloud();
    setInterval(pollCloud, POLL_MS);
})();
</script>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
