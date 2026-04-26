<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$mgr   = new WordCloudManager();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();

    $guestEnabled   = isset($_POST['guest_enabled']) ? '1' : '0';
    $days           = max(0, min(90, (int) ($_POST['guest_days']   ?? 1)));
    $hours          = max(0, min(23, (int) ($_POST['guest_hours']  ?? 0)));
    $totalHours     = max(1, $days * 24 + $hours);
    $retDays        = max(0, min(365, (int) ($_POST['ret_days']    ?? 0)));
    $retHours       = max(0, min(23,  (int) ($_POST['ret_hours']   ?? 0)));
    $retTotalHours  = $retDays * 24 + $retHours;
    $guestMaxActive = max(0, (int) ($_POST['guest_max_active'] ?? 0));
    $displayMode    = in_array($_POST['display_mode'] ?? '', ['cloud','list']) ? $_POST['display_mode'] : 'cloud';

    $mgr->setSetting('guest_sessions_enabled',  $guestEnabled);
    $mgr->setSetting('guest_session_hours',     (string) $totalHours);
    $mgr->setSetting('guest_retention_hours',   (string) $retTotalHours);
    $mgr->setSetting('guest_max_active',        (string) $guestMaxActive);
    $mgr->setSetting('cloud_display_mode',      $displayMode);
    $saved = true;
}

$guestEnabled    = $mgr->getSetting('guest_sessions_enabled', '0') === '1';
$totalHours      = max(1, (int) $mgr->getSetting('guest_session_hours', '24'));
$currentDays     = intdiv($totalHours, 24);
$currentHours    = $totalHours % 24;
$retTotalHours   = max(0, (int) $mgr->getSetting('guest_retention_hours', '0'));
$currentRetDays  = intdiv($retTotalHours, 24);
$currentRetHours = $retTotalHours % 24;
$guestMaxActive  = max(0, (int) $mgr->getSetting('guest_max_active', '0'));
$displayMode     = $mgr->getSetting('cloud_display_mode', 'cloud');
if (!in_array($displayMode, ['cloud','list'])) $displayMode = 'cloud';

adminHead('Einstellungen');
adminNav('/admin/settings.php');
echo renderFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-gear-fill text-indigo me-2"></i>Einstellungen
    </h2>
</div>

<?php if ($saved): ?>
<div class="alert alert-success alert-dismissible">
    <i class="bi bi-check-circle me-2"></i>Einstellungen gespeichert.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" class="row g-4">
    <?= Auth::csrfInput() ?>

    <!-- Gastsitzungen -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-white">
                <i class="bi bi-person-plus-fill me-2 text-success"></i>Gastsitzungen
            </div>
            <div class="card-body">

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="guest_enabled" id="guestEnabled"
                           <?= $guestEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="guestEnabled">
                        Gastsitzungen aktivieren
                    </label>
                    <div class="text-muted small mt-1">
                        Wenn aktiv, können Besucher unter <strong>/guest.php</strong> eigene
                        Wortwolken anlegen (nur ARASAAC-Symbole, kein Bild-Upload).
                    </div>
                </div>

                <!-- Ablaufzeit -->
                <label class="form-label fw-semibold">Ablaufzeit (Sitzung wird geschlossen nach)</label>
                <div class="d-flex gap-2 align-items-center flex-wrap mb-1" style="max-width:360px;">
                    <div class="input-group" style="width:160px;">
                        <input type="number" name="guest_days" id="guestDays"
                               class="form-control" min="0" max="90"
                               value="<?= $currentDays ?>" oninput="updatePreview()">
                        <span class="input-group-text">Tage</span>
                    </div>
                    <div class="input-group" style="width:160px;">
                        <input type="number" name="guest_hours" id="guestHours"
                               class="form-control" min="0" max="23"
                               value="<?= $currentHours ?>" oninput="updatePreview()">
                        <span class="input-group-text">Stunden</span>
                    </div>
                </div>
                <div class="text-muted small mt-1 mb-3" id="timePreview">
                    <?= $currentDays > 0 ? $currentDays . ' Tag' . ($currentDays != 1 ? 'e' : '') . ' ' : '' ?>
                    <?= $currentHours > 0 ? $currentHours . ' Stunde' . ($currentHours != 1 ? 'n' : '') : '' ?>
                    = <?= $totalHours ?> Stunde<?= $totalHours != 1 ? 'n' : '' ?> gesamt (max. 90 Tage)
                </div>

                <!-- Aufbewahrungsfrist -->
                <label class="form-label fw-semibold">Aufbewahrungsfrist (Daten bleiben nach Ablauf noch erhalten)</label>
                <div class="d-flex gap-2 align-items-center flex-wrap mb-1" style="max-width:360px;">
                    <div class="input-group" style="width:160px;">
                        <input type="number" name="ret_days" id="retDays"
                               class="form-control" min="0" max="365"
                               value="<?= $currentRetDays ?>" oninput="updateRetPreview()">
                        <span class="input-group-text">Tage</span>
                    </div>
                    <div class="input-group" style="width:160px;">
                        <input type="number" name="ret_hours" id="retHours"
                               class="form-control" min="0" max="23"
                               value="<?= $currentRetHours ?>" oninput="updateRetPreview()">
                        <span class="input-group-text">Stunden</span>
                    </div>
                </div>
                <div class="text-muted small mt-1 mb-3" id="retPreview">
                    <?php if ($retTotalHours > 0): ?>
                        Sitzungen werden <?= $currentRetDays > 0 ? $currentRetDays . ' Tag' . ($currentRetDays != 1 ? 'e' : '') . ' ' : '' ?><?= $currentRetHours > 0 ? $currentRetHours . ' Stunde' . ($currentRetHours != 1 ? 'n' : '') : '' ?> nach Ablauf gelöscht.
                    <?php else: ?>
                        0 = sofort löschen nach Ablauf
                    <?php endif; ?>
                </div>

                <!-- Max. Gastsitzungen -->
                <label class="form-label fw-semibold" for="guestMaxActive">
                    Max. gleichzeitig aktive Gastsitzungen (Limit für fremde Wortwolken)
                </label>
                <div class="input-group mb-1" style="max-width:200px;">
                    <input type="number" name="guest_max_active" id="guestMaxActive"
                           class="form-control" min="0" max="9999"
                           value="<?= $guestMaxActive ?>">
                    <span class="input-group-text">Stück</span>
                </div>
                <div class="text-muted small mb-3">0 = unbegrenzt</div>

                <hr class="my-3">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Gastsitzungen sind unter
                    <a href="/admin/guests.php">/admin/guests.php</a> einsehbar.
                    Gäste erhalten einen persönlichen Admin-Link (ohne Login)
                    und einen Teilnehmer-Link. Kein Bild-Upload für Gäste.
                </div>
            </div>
        </div>
    </div>

    <!-- Darstellungsoptionen -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-white">
                <i class="bi bi-grid-fill me-2 text-indigo"></i>Darstellungsmodus (Wortwolke)
            </div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="display_mode"
                           id="modeCloud" value="cloud"
                           <?= $displayMode === 'cloud' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="modeCloud">
                        <i class="bi bi-cloud-fill text-indigo me-1"></i>
                        <strong>Wolke</strong> – Symbole spiralförmig, Größe nach Stimmenzahl, zentriert
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="display_mode"
                           id="modeList" value="list"
                           <?= $displayMode === 'list' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="modeList">
                        <i class="bi bi-bar-chart-fill text-indigo me-1"></i>
                        <strong>Reihe</strong> – Symbole von groß nach klein, nebeneinander angeordnet
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-2"></i>Einstellungen speichern
        </button>
    </div>
</form>

<script>
function updatePreview() {
    const days  = Math.max(0, Math.min(90,  parseInt(document.getElementById('guestDays').value)  || 0));
    const hours = Math.max(0, Math.min(23,  parseInt(document.getElementById('guestHours').value) || 0));
    const total = Math.max(1, days * 24 + hours);
    let txt = '';
    if (days > 0)  txt += days  + (days  === 1 ? ' Tag '    : ' Tage ');
    if (hours > 0) txt += hours + (hours === 1 ? ' Stunde ' : ' Stunden ');
    if (!txt) txt = '0 ';
    document.getElementById('timePreview').textContent =
        txt.trim() + ' = ' + total + ' Stunde' + (total !== 1 ? 'n' : '') + ' gesamt (max. 90 Tage)';
}

function updateRetPreview() {
    const days  = Math.max(0, Math.min(365, parseInt(document.getElementById('retDays').value)  || 0));
    const hours = Math.max(0, Math.min(23,  parseInt(document.getElementById('retHours').value) || 0));
    const total = days * 24 + hours;
    const el    = document.getElementById('retPreview');
    if (total === 0) {
        el.textContent = '0 = sofort löschen nach Ablauf';
    } else {
        let txt = '';
        if (days > 0)  txt += days  + (days  === 1 ? ' Tag '    : ' Tage ');
        if (hours > 0) txt += hours + (hours === 1 ? ' Stunde ' : ' Stunden ');
        el.textContent = 'Sitzungen werden ' + txt.trim() + ' nach Ablauf gelöscht.';
    }
}
</script>

<?php adminFoot(); ?>
