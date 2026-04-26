<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$mgr      = new WordCloudManager();
$sessions = $mgr->getSessions(null, false);   // nur normale Sitzungen (kein Gast)
$active   = array_filter($sessions, fn($s) => $s['status'] === 'active');
$closed   = array_filter($sessions, fn($s) => $s['status'] === 'closed');
$guestCount = count($mgr->getSessions(null, true));

adminHead('Übersicht');
adminNav('/admin/');
echo renderFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-grid-fill text-indigo me-2"></i>Wortwolken-Sitzungen
    </h2>
    <div class="d-flex gap-2">
        <a href="/admin/export.php?type=sessions" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV-Export
        </a>
        <a href="/admin/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Neue Sitzung
        </a>
    </div>
</div>

<div class="alert alert-light border small py-2 mb-4">
    <i class="bi bi-info-circle text-primary me-2"></i>
    Teilnehmer öffnen <strong><?= e(($_SERVER['HTTP_HOST'] ?? 'ihre-schule.de')) ?>/</strong>
    und geben den 6-stelligen Code ein – oder direkt
    <strong>/join.php?code=XXXXXX</strong>
</div>

<?php if ($guestCount > 0): ?>
<div class="alert alert-warning border-0 py-2 mb-4 d-flex align-items-center gap-2">
    <i class="bi bi-person-plus-fill"></i>
    <span>
        <strong><?= $guestCount ?></strong> aktive Gastsitzung<?= $guestCount != 1 ? 'en' : '' ?> vorhanden.
        <a href="/admin/guests.php" class="alert-link ms-1">Gastsitzungen anzeigen →</a>
    </span>
</div>
<?php endif; ?>

<?php if (empty($sessions)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-chat-square-text display-1 text-muted opacity-25"></i>
        <h5 class="mt-3 text-muted">Noch keine Sitzungen vorhanden</h5>
        <a href="/admin/create.php" class="btn btn-primary mt-2">
            <i class="bi bi-plus-lg me-1"></i>Erste Sitzung anlegen
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Aktive Sitzungen -->
<?php if (!empty($active)): ?>
<h5 class="fw-semibold mb-3 d-flex align-items-center gap-2">
    <span class="badge bg-success rounded-pill">●</span>Aktive Sitzungen
</h5>
<div class="row g-3 mb-4">
<?php foreach ($active as $s): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 session-card">
            <div class="card-header d-flex align-items-center justify-content-between py-2 bg-white border-bottom">
                <span class="fw-semibold text-truncate"><?= e($s['title']) ?></span>
                <span class="badge bg-success ms-2 flex-shrink-0">Aktiv</span>
            </div>
            <div class="card-body pb-2">
                <!-- Code -->
                <div class="text-center mb-3">
                    <div class="text-muted small mb-1">Teilnehmer-Code</div>
                    <div class="code-display font-monospace"><?= e($s['session_code']) ?></div>
                    <div class="d-flex gap-1 justify-content-center mt-1 flex-wrap">
                        <button class="btn btn-xs btn-outline-secondary"
                                onclick="copyLink('<?= e($s['session_code']) ?>', this)">
                            <i class="bi bi-clipboard me-1"></i>Link kopieren
                        </button>
                        <button class="btn btn-xs btn-outline-secondary"
                                onclick="showQr('<?= e($s['session_code']) ?>')">
                            <i class="bi bi-qr-code me-1"></i>QR
                        </button>
                    </div>
                </div>
                <!-- Stats -->
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-val"><?= $s['participant_count'] ?></div>
                            <div class="stat-lbl">Teiln.</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-val"><?= $s['total_votes'] ?></div>
                            <div class="stat-lbl">Stimmen</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-val">
                                <?php
                                $ml = ['symbols'=>'<i class="bi bi-images"></i>','search'=>'<i class="bi bi-search"></i>','both'=>'<i class="bi bi-grid"></i>'];
                                echo $ml[$s['mode']] ?? e($s['mode']);
                                ?>
                            </div>
                            <div class="stat-lbl">Modus</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-top pt-0 pb-3 px-3">
                <div class="d-flex gap-2">
                    <a href="/join.php?code=<?= urlencode($s['session_code']) ?>" target="_blank"
                       class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-eye me-1"></i>Live-Ansicht
                    </a>
                    <a href="/admin/edit.php?id=<?= $s['id'] ?>"
                       class="btn btn-sm btn-outline-secondary" title="Bearbeiten">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="/admin/votes.php?id=<?= $s['id'] ?>"
                       class="btn btn-sm btn-outline-secondary" title="Abstimmungen verwalten">
                        <i class="bi bi-list-check"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-warning" title="Stimmen zurücksetzen"
                            onclick="doAction('reset', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" title="Sitzung schließen"
                            onclick="doAction('close', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                        <i class="bi bi-stop-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" title="Löschen"
                            onclick="doAction('delete', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="mt-2 text-muted" style="font-size:11px;">
                    <i class="bi bi-clock me-1"></i><?= fmtDateTime($s['created_at']) ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Geschlossene Sitzungen -->
<?php if (!empty($closed)): ?>
<h5 class="fw-semibold mb-3 d-flex align-items-center gap-2 mt-4">
    <span class="badge bg-secondary rounded-pill">●</span>Geschlossene Sitzungen
</h5>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Titel</th>
                    <th>Code</th>
                    <th>Modus</th>
                    <th class="text-end">Teiln.</th>
                    <th class="text-end">Stimmen</th>
                    <th>Erstellt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($closed as $s): ?>
            <tr>
                <td class="fw-semibold"><?= e($s['title']) ?></td>
                <td><code class="text-muted"><?= e($s['session_code']) ?></code></td>
                <td>
                    <?php
                    $badges = ['symbols'=>'secondary','search'=>'info','both'=>'primary'];
                    $labels = ['symbols'=>'Symbole','search'=>'Suche','both'=>'Beides'];
                    ?>
                    <span class="badge bg-<?= $badges[$s['mode']]??'secondary' ?>">
                        <?= $labels[$s['mode']]??e($s['mode']) ?>
                    </span>
                </td>
                <td class="text-end"><?= $s['participant_count'] ?></td>
                <td class="text-end"><?= $s['total_votes'] ?></td>
                <td class="text-muted small"><?= fmtDate($s['created_at']) ?></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/admin/votes.php?id=<?= $s['id'] ?>"
                           class="btn btn-outline-secondary" title="Abstimmungen verwalten">
                            <i class="bi bi-list-check"></i>
                        </a>
                        <button class="btn btn-outline-success" title="Wieder öffnen"
                                onclick="doAction('reopen', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                            <i class="bi bi-play-circle"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Löschen"
                                onclick="doAction('delete', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- QR-Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-qr-code me-2"></i>QR-Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-1">
                <img id="qrImg" src="" alt="QR-Code" style="width:220px;height:220px;">
                <div class="mt-2 text-muted small" id="qrUrl" style="word-break:break-all;font-size:11px;"></div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button class="btn btn-sm btn-outline-secondary" onclick="copyLink(qrCurrentCode, this)">
                    <i class="bi bi-clipboard me-1"></i>Link kopieren
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<form id="actionForm" method="POST" action="/admin/action.php" class="d-none">
    <?= Auth::csrfInput() ?>
    <input type="hidden" name="action" id="fAction">
    <input type="hidden" name="id"     id="fId">
</form>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script>
let qrCurrentCode = '';

function doAction(action, id, title) {
    const msgs = {
        close:  `Sitzung "${title}" schließen?`,
        reopen: `Sitzung "${title}" wieder öffnen?`,
        delete: `Sitzung "${title}" und alle Stimmen dauerhaft löschen?`,
        reset:  `Alle Stimmen der Sitzung "${title}" zurücksetzen?`,
    };
    if (!confirm(msgs[action] || 'Aktion ausführen?')) return;
    document.getElementById('fAction').value = action;
    document.getElementById('fId').value     = id;
    document.getElementById('actionForm').submit();
}

function copyLink(code, btn) {
    const url = location.origin + '/join.php?code=' + code;
    navigator.clipboard.writeText(url).then(() => {
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check me-1"></i>Kopiert!';
            setTimeout(() => { btn.innerHTML = orig; }, 1800);
        }
    }).catch(() => { prompt('Link zum Kopieren:', url); });
}

function showQr(code) {
    qrCurrentCode = code;
    const url = location.origin + '/join.php?code=' + code;
    document.getElementById('qrUrl').textContent = url;
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    document.getElementById('qrImg').src = qr.createDataURL(6, 0);
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}
</script>

<?php adminFoot(); ?>
