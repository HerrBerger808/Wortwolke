<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$mgr    = new WordCloudManager();
$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();

    $guestEnabled = isset($_POST['guest_enabled']) ? '1' : '0';
    $guestHours   = max(1, min(720, (int) ($_POST['guest_hours'] ?? 24)));

    $mgr->setSetting('guest_sessions_enabled', $guestEnabled);
    $mgr->setSetting('guest_session_hours',    (string) $guestHours);
    $saved = true;
}

$guestEnabled = $mgr->getSetting('guest_sessions_enabled', '0') === '1';
$guestHours   = (int) $mgr->getSetting('guest_session_hours', '24');

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
                <div class="form-check form-switch mb-3">
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

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="guestHours">
                        Ablaufzeit (Stunden)
                    </label>
                    <div class="input-group" style="max-width:240px;">
                        <input type="number" name="guest_hours" id="guestHours"
                               class="form-control" min="1" max="720"
                               value="<?= $guestHours ?>">
                        <span class="input-group-text">Stunden</span>
                    </div>
                    <div class="text-muted small mt-1">
                        Nach Ablauf wird die Gastsitzung automatisch gelöscht (1–720 h).
                        Aktuell: <?= $guestHours ?> h
                        (= <?= round($guestHours / 24, 1) ?> Tag<?= $guestHours >= 48 ? 'e' : '' ?>)
                    </div>
                </div>

                <hr class="my-3">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Gastsitzungen werden in der Admin-Übersicht als
                    <span class="badge bg-warning text-dark">Gast</span>-Badge markiert.
                    Gäste erhalten einen persönlichen Admin-Link (ohne Login) und
                    einen Teilnehmer-Link. Kein Bild-Upload für Gäste.
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

<?php adminFoot(); ?>
