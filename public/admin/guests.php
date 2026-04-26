<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::requireAdmin();

$mgr      = new WordCloudManager();
$sessions = $mgr->getSessions(null, true);   // nur Gastsitzungen
$active   = array_filter($sessions, fn($s) => $s['status'] === 'active');
$closed   = array_filter($sessions, fn($s) => $s['status'] === 'closed');

$guestEnabled   = $mgr->getSetting('guest_sessions_enabled', '0') === '1';
$totalHours     = max(1, (int) $mgr->getSetting('guest_session_hours', '24'));
$retentionHours = max(0, (int) $mgr->getSetting('guest_retention_hours', '0'));
$days         = intdiv($totalHours, 24);
$hrs          = $totalHours % 24;
$expiryLabel  = ($days > 0 ? $days . ' Tag' . ($days != 1 ? 'e' : '') . ' ' : '')
              . ($hrs > 0 ? $hrs . ' Stunde' . ($hrs != 1 ? 'n' : '') : '');
if (!$expiryLabel) $expiryLabel = $totalHours . ' Stunden';

adminHead('Gastsitzungen');
adminNav('/admin/guests.php');
echo renderFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-person-plus-fill text-success me-2"></i>Gastsitzungen
    </h2>
    <div class="d-flex gap-2">
        <a href="/admin/export.php?type=sessions" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV-Export
        </a>
        <a href="/admin/settings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i>Einstellungen
        </a>
    </div>
</div>

<!-- Status-Banner -->
<div class="alert alert-<?= $guestEnabled ? 'success' : 'secondary' ?> border-0 small py-2 mb-4 d-flex gap-2 align-items-center">
    <i class="bi bi-<?= $guestEnabled ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
    <span>
        Gastsitzungen sind <strong><?= $guestEnabled ? 'aktiviert' : 'deaktiviert' ?></strong>.
        <?php if ($guestEnabled): ?>
            Ablaufzeit: <strong><?= e($expiryLabel) ?></strong> ·
            Gastseite: <a href="/guest.php" target="_blank">/guest.php</a>
        <?php endif; ?>
        <a href="/admin/settings.php" class="ms-2">Einstellungen ändern →</a>
    </span>
</div>

<?php if (empty($sessions)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-plus display-1 text-muted opacity-25"></i>
        <h5 class="mt-3 text-muted">Keine Gastsitzungen vorhanden</h5>
        <?php if ($guestEnabled): ?>
        <a href="/guest.php" target="_blank" class="btn btn-outline-success mt-2">
            <i class="bi bi-box-arrow-up-right me-1"></i>Zur Gastseite
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Aktive Gastsitzungen -->
<?php if (!empty($active)): ?>
<h5 class="fw-semibold mb-3 d-flex align-items-center gap-2">
    <span class="badge bg-success rounded-pill">●</span>Aktive Gastsitzungen
</h5>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Titel</th>
                    <th>Code</th>
                    <th>Modus</th>
                    <th class="text-end">Teiln.</th>
                    <th class="text-end">Stimmen</th>
                    <th>Läuft ab</th>
                    <th>Erstellt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($active as $s): ?>
            <?php
                $expired  = $s['expires_at'] && strtotime($s['expires_at']) < time();
                $expClass = $expired ? 'text-danger' : 'text-muted';
                $remaining = $s['expires_at'] ? ceil((strtotime($s['expires_at']) - time()) / 3600) : null;
            ?>
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
                <td class="<?= $expClass ?> small">
                    <?php if ($s['expires_at']): ?>
                        <?= date('d.m.Y H:i', strtotime($s['expires_at'])) ?>
                        <?php if ($remaining !== null && $remaining > 0): ?>
                        <br><span style="font-size:10px;">(noch <?= $remaining ?> h)</span>
                        <?php endif; ?>
                    <?php else: ?>–<?php endif; ?>
                </td>
                <td class="text-muted small"><?= fmtDateTime($s['created_at']) ?></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/join.php?code=<?= urlencode($s['session_code']) ?>" target="_blank"
                           class="btn btn-outline-primary" title="Live-Ansicht">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="/admin/votes.php?id=<?= $s['id'] ?>"
                           class="btn btn-outline-secondary" title="Stimmen">
                            <i class="bi bi-list-check"></i>
                        </a>
                        <button class="btn btn-outline-warning" title="Stimmen zurücksetzen"
                                onclick="doAction('reset', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Sitzung schließen"
                                onclick="doAction('close', <?= $s['id'] ?>, '<?= e(addslashes($s['title'])) ?>')">
                            <i class="bi bi-stop-circle"></i>
                        </button>
                        <button class="btn btn-danger" title="Löschen"
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

<!-- Geschlossene Gastsitzungen -->
<?php if (!empty($closed)): ?>
<h5 class="fw-semibold mb-3 d-flex align-items-center gap-2 mt-4">
    <span class="badge bg-secondary rounded-pill">●</span>Geschlossene Gastsitzungen
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
                    <th>Abgelaufen</th>
                    <th>Löschen am</th>
                    <th>Erstellt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($closed as $s): ?>
            <?php
                $expiredAt = $s['expires_at'] ? strtotime($s['expires_at']) : null;
                $deleteAt  = ($expiredAt && $retentionHours > 0)
                    ? $expiredAt + $retentionHours * 3600
                    : $expiredAt;
            ?>
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
                <td class="text-muted small">
                    <?= $expiredAt ? date('d.m.Y H:i', $expiredAt) : '–' ?>
                </td>
                <td class="small <?= ($deleteAt && $deleteAt < time()) ? 'text-danger' : 'text-muted' ?>">
                    <?php if ($deleteAt): ?>
                        <?= date('d.m.Y H:i', $deleteAt) ?>
                        <?php if ($deleteAt > time()): ?>
                        <br><span style="font-size:10px;">(noch <?= ceil(($deleteAt - time()) / 3600) ?> h)</span>
                        <?php endif; ?>
                    <?php else: ?>–<?php endif; ?>
                </td>
                <td class="text-muted small"><?= fmtDate($s['created_at']) ?></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/admin/votes.php?id=<?= $s['id'] ?>"
                           class="btn btn-outline-secondary" title="Stimmen">
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

<form id="actionForm" method="POST" action="/admin/action.php" class="d-none">
    <?= Auth::csrfInput() ?>
    <input type="hidden" name="action" id="fAction">
    <input type="hidden" name="id"     id="fId">
</form>

<script>
function doAction(action, id, title) {
    const msgs = {
        close:  `Gastsitzung "${title}" schließen?`,
        reopen: `Gastsitzung "${title}" wieder öffnen?`,
        delete: `Gastsitzung "${title}" und alle Stimmen dauerhaft löschen?`,
        reset:  `Alle Stimmen der Gastsitzung "${title}" zurücksetzen?`,
    };
    if (!confirm(msgs[action] || 'Aktion ausführen?')) return;
    document.getElementById('fAction').value = action;
    document.getElementById('fId').value     = id;
    document.getElementById('actionForm').submit();
}
</script>

<?php adminFoot(); ?>
