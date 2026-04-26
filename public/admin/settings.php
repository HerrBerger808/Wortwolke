<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$mgr   = new WordCloudManager();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();

    $guestEnabled = isset($_POST['guest_enabled']) ? '1' : '0';
    $days         = max(0, min(90, (int) ($_POST['guest_days']  ?? 1)));
    $hours        = max(0, min(23, (int) ($_POST['guest_hours'] ?? 0)));
    $totalHours   = max(1, $days * 24 + $hours);

    $mgr->setSetting('guest_sessions_enabled', $guestEnabled);
    $mgr->setSetting('guest_session_hours',    (string) $totalHours);
    $saved = true;
}

$guestEnabled = $mgr->getSetting('guest_sessions_enabled', '0') === '1';
$totalHours   = max(1, (int) $mgr->getSetting('guest_session_hours', '24'));
$currentDays  = intdiv($totalHours, 24);
$currentHours = $totalHours % 24;

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

                <label class="form-label fw-semibold">Ablaufzeit</label>
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
                <div class="text-muted small mt-1" id="timePreview">
                    <?= $currentDays > 0 ? $currentDays . ' Tag' . ($currentDays != 1 ? 'e' : '') . ' ' : '' ?>
                    <?= $currentHours > 0 ? $currentHours . ' Stunde' . ($currentHours != 1 ? 'n' : '') : '' ?>
                    = <?= $totalHours ?> Stunde<?= $totalHours != 1 ? 'n' : '' ?> gesamt (max. 90 Tage)
                </div>

                <hr class="my-3">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Gastsitzungen sind unter
                    <a href="/admin/guests.php">/admin/guests.php</a> einsehbar
                    und werden nach Ablauf automatisch gelöscht.
                    Gäste erhalten einen persönlichen Admin-Link (ohne Login)
                    und einen Teilnehmer-Link. Kein Bild-Upload für Gäste.
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
    const days  = Math.max(0, Math.min(90, parseInt(document.getElementById('guestDays').value)  || 0));
    const hours = Math.max(0, Math.min(23, parseInt(document.getElementById('guestHours').value) || 0));
    const total = Math.max(1, days * 24 + hours);
    let txt = '';
    if (days > 0)  txt += days  + (days  === 1 ? ' Tag '    : ' Tage ');
    if (hours > 0) txt += hours + (hours === 1 ? ' Stunde ' : ' Stunden ');
    if (!txt) txt = '0 ';
    document.getElementById('timePreview').textContent =
        txt.trim() + ' = ' + total + ' Stunde' + (total !== 1 ? 'n' : '') + ' gesamt (max. 90 Tage)';
}
</script>

<?php adminFoot(); ?>
