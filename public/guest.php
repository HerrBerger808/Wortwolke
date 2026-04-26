<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

$mgr            = new WordCloudManager();
$guestEnabled   = $mgr->getSetting('guest_sessions_enabled', '0') === '1';
$guestHours     = max(1, (int) $mgr->getSetting('guest_session_hours', '24'));
$guestMaxActive = max(0, (int) $mgr->getSetting('guest_max_active', '0'));
$appTitle       = appTitle();

$errors  = [];
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $guestEnabled) {
    // CSRF manuell (kein Auth::requireCsrf, da kein Admin-Login)
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['guest_csrf'] ?? '', $csrf)) {
        $errors[] = 'Sicherheitsfehler – bitte Seite neu laden.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $mode  = $_POST['mode'] ?? 'both';
        if (!in_array($mode, ['symbols', 'search', 'both'])) $mode = 'both';

        $symbols = [];
        if ($mode !== 'search') {
            $ids    = $_POST['symbol_id']    ?? [];
            $labels = $_POST['symbol_label'] ?? [];
            foreach ($ids as $i => $rawId) {
                $id    = (int) $rawId;
                $label = trim($labels[$i] ?? '');
                if ($id > 0 && $label !== '') {
                    $symbols[] = ['arasaac_id' => $id, 'label' => $label];
                }
                if (count($symbols) >= WordCloudManager::MAX_SYMBOLS) break;
            }
        }

        if ($title === '') $errors[] = 'Bitte einen Titel eingeben.';
        if ($mode === 'symbols' && empty($symbols))
            $errors[] = 'Im Modus „Nur Symbole" muss mindestens ein Symbol hinzugefügt werden.';

        if (empty($errors) && $guestMaxActive > 0) {
            if ($mgr->countActiveGuestSessions() >= $guestMaxActive) {
                $errors[] = 'Derzeit sind keine weiteren Gastsitzungen möglich (Maximum von '
                    . $guestMaxActive . ' aktiven Sitzungen erreicht). Bitte später versuchen.';
            }
        }

        if (empty($errors)) {
            $result  = $mgr->createGuestSession($title, $mode, $symbols, $guestHours);
            $created = $result;
        }
    }
}

// CSRF-Token für das Formular generieren
if (empty($_SESSION['guest_csrf'])) {
    $_SESSION['guest_csrf'] = bin2hex(random_bytes(32));
}

$existingJs = '[]';
if (!$created && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSymbols = [];
    $ids    = $_POST['symbol_id']    ?? [];
    $labels = $_POST['symbol_label'] ?? [];
    foreach ($ids as $i => $rawId) {
        $id    = (int) $rawId;
        $label = trim($labels[$i] ?? '');
        if ($id > 0 && $label !== '') {
            $postedSymbols[] = [
                'id'        => $id,
                'label'     => $label,
                'image_url' => WordCloudManager::imageUrl($id),
            ];
        }
    }
    $existingJs = json_encode($postedSymbols, JSON_UNESCAPED_UNICODE);
}

$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = 'https://' . $host;
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gast-Sitzung erstellen – <?= e($appTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-indigo">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-chat-square-text-fill me-2"></i><?= e($appTitle) ?>
        </a>
        <a href="/" class="btn btn-sm btn-outline-light">
            <i class="bi bi-house me-1"></i>Startseite
        </a>
    </div>
</nav>

<div class="container py-5" style="max-width:860px;">

<?php if (!$guestEnabled): ?>
    <div class="text-center py-5">
        <i class="bi bi-lock-fill display-1 text-muted opacity-25 d-block mb-3"></i>
        <h3 class="fw-bold">Gastsitzungen deaktiviert</h3>
        <p class="text-muted">Diese Funktion ist derzeit nicht verfügbar.</p>
        <a href="/" class="btn btn-primary mt-2">Zurück zur Startseite</a>
    </div>

<?php elseif ($created): ?>
    <!-- Erfolg: Links anzeigen -->
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle-fill text-success display-3 d-block mb-3"></i>
            <h3 class="fw-bold">Sitzung erstellt!</h3>
            <p class="text-muted mb-4">
                Deine Gastsitzung läuft automatisch nach
                <strong><?= $guestHours ?> Stunde<?= $guestHours != 1 ? 'n' : '' ?></strong> ab.
            </p>

            <div class="row g-3 justify-content-center mb-4">
                <div class="col-12 col-md-6">
                    <div class="card border-success">
                        <div class="card-body text-start">
                            <div class="fw-bold mb-1"><i class="bi bi-people-fill text-success me-2"></i>Teilnehmer-Link</div>
                            <div class="font-monospace small text-break mb-2" id="participantUrl">
                                <?= e($baseUrl . '/join.php?code=' . $created['code']) ?>
                            </div>
                            <button class="btn btn-sm btn-outline-success" onclick="copyText('participantUrl', this)">
                                <i class="bi bi-clipboard me-1"></i>Kopieren
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card border-warning">
                        <div class="card-body text-start">
                            <div class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Dein Admin-Link</div>
                            <div class="font-monospace small text-break mb-2" id="adminUrl">
                                <?= e($baseUrl . '/guest-admin.php?token=' . $created['token']) ?>
                            </div>
                            <button class="btn btn-sm btn-outline-warning" onclick="copyText('adminUrl', this)">
                                <i class="bi bi-clipboard me-1"></i>Kopieren
                            </button>
                            <div class="text-muted mt-2" style="font-size:11px;">
                                <i class="bi bi-exclamation-triangle me-1"></i>Diesen Link sicher aufbewahren – er wird nur einmal angezeigt!
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="text-muted small mb-2">Teilnehmer-Code</div>
                <div class="display-6 fw-bold font-monospace text-indigo letter-spacing-wide">
                    <?= e($created['code']) ?>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="<?= e('/join.php?code=' . $created['code']) ?>" target="_blank"
                   class="btn btn-primary">
                    <i class="bi bi-eye me-1"></i>Zur Sitzung
                </a>
                <a href="<?= e('/guest-admin.php?token=' . $created['token']) ?>"
                   class="btn btn-warning text-dark">
                    <i class="bi bi-shield-lock me-1"></i>Sitzung verwalten
                </a>
                <a href="/guest.php" class="btn btn-outline-secondary">
                    <i class="bi bi-plus me-1"></i>Neue Sitzung
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="mb-4">
        <h2 class="fw-bold"><i class="bi bi-person-plus-fill text-success me-2"></i>Gast-Sitzung erstellen</h2>
        <p class="text-muted">
            Erstelle eine eigene Wortwolken-Sitzung ohne Anmeldung.
            Du erhältst einen Admin-Link und einen Teilnehmer-Link.
            Die Sitzung wird nach <strong><?= $guestHours ?> Stunde<?= $guestHours != 1 ? 'n' : '' ?></strong> automatisch gelöscht.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="guestForm">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['guest_csrf']) ?>">

        <?php
        // Formular-Partials wiederverwenden
        $session = [
            'title'              => trim($_POST['title'] ?? ''),
            'mode'               => $_POST['mode'] ?? 'both',
            'predefined_symbols' => [],
        ];
        include __DIR__ . '/admin/_session_form.php';
        ?>

        <div class="mt-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-check2 me-2"></i>Sitzung erstellen
            </button>
            <a href="/" class="btn btn-outline-secondary btn-lg">Abbrechen</a>
        </div>
    </form>

    <?php
    // Upload-Button im Gast-Formular verstecken (kein Bild-Upload für Gäste)
    ?>
    <style>#symUploadBtn, #symUploadInput, #symUploadStatus,
           label[for="symUploadInput"] { display:none !important; }
    </style>

    <?php include __DIR__ . '/admin/_session_form_js.php'; ?>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyText(id, btn) {
    const text = document.getElementById(id).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Kopiert!';
        setTimeout(() => { btn.innerHTML = orig; }, 1800);
    }).catch(() => { prompt('Bitte manuell kopieren:', text); });
}
</script>
</body>
</html>
