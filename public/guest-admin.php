<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mgr      = new WordCloudManager();
$appTitle = appTitle();

if (!$token) {
    header('Location: /guest.php');
    exit;
}

$session = $mgr->getSessionByGuestToken($token);

if (!$session) {
    $err = 'Sitzung nicht gefunden oder abgelaufen.';
}

// Aktionen
$flash = '';
if ($session && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['guest_csrf_admin_' . substr($token, 0, 8)] ?? '', $csrf)) {
        $flash = '<div class="alert alert-danger">Sicherheitsfehler.</div>';
    } else {
        $action = $_POST['action'] ?? '';
        match ($action) {
            'close'  => $mgr->closeSession((int)$session['id']),
            'reopen' => $mgr->reopenSession((int)$session['id']),
            'reset'  => $mgr->resetVotes((int)$session['id']),
            default  => null,
        };
        if ($action) {
            header('Location: /guest-admin.php?token=' . urlencode($token));
            exit;
        }
    }
    // Reload session after action
    $session = $mgr->getSessionByGuestToken($token);
}

// CSRF für Gast-Admin
$csrfKey = 'guest_csrf_admin_' . substr($token, 0, 8);
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfKey];

$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = 'https://' . $host;

$cloudData   = $session ? $mgr->getCloudData((int)$session['id']) : [];
$participants = $session ? $mgr->activeParticipants((int)$session['id']) : 0;
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gast-Admin – <?= e($appTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-indigo">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold">
            <i class="bi bi-shield-lock-fill me-2"></i>Gast-Admin
        </span>
        <a href="/" class="btn btn-sm btn-outline-light">
            <i class="bi bi-house me-1"></i>Startseite
        </a>
    </div>
</nav>

<div class="container py-4" style="max-width:760px;">

<?php if (!empty($err)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <i class="bi bi-emoji-frown display-1 text-muted opacity-25 d-block mb-3"></i>
        <h4 class="fw-bold"><?= e($err) ?></h4>
        <a href="/guest.php" class="btn btn-primary mt-3">Neue Sitzung erstellen</a>
    </div>
<?php else: ?>

<?= $flash ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($session['title']) ?></h2>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge bg-<?= $session['status']==='active'?'success':'secondary' ?> fs-6 font-monospace px-3">
                <?= e($session['session_code']) ?>
            </span>
            <span class="badge bg-<?= $session['status']==='active'?'success':'secondary' ?>">
                <?= $session['status']==='active' ? 'Aktiv' : 'Geschlossen' ?>
            </span>
            <span class="badge bg-warning text-dark">Gast</span>
            <?php if ($session['expires_at']): ?>
            <span class="text-muted small">
                <i class="bi bi-clock me-1"></i>Läuft ab: <?= date('d.m.Y H:i', strtotime($session['expires_at'])) ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= e('/join.php?code=' . $session['session_code']) ?>" target="_blank"
       class="btn btn-primary">
        <i class="bi bi-eye me-1"></i>Zur Live-Ansicht
    </a>
</div>

<!-- Links -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-people-fill text-success me-1"></i>Teilnehmer-Link</div>
                <div class="font-monospace small text-break mb-2" id="pUrl">
                    <?= e($baseUrl . '/join.php?code=' . $session['session_code']) ?>
                </div>
                <button class="btn btn-sm btn-outline-success" onclick="copyText('pUrl',this)">
                    <i class="bi bi-clipboard me-1"></i>Kopieren
                </button>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card border-warning h-100">
            <div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-shield-lock text-warning me-1"></i>Dein Admin-Link</div>
                <div class="font-monospace small text-break mb-2" id="aUrl">
                    <?= e($baseUrl . '/guest-admin.php?token=' . $token) ?>
                </div>
                <button class="btn btn-sm btn-outline-warning" onclick="copyText('aUrl',this)">
                    <i class="bi bi-clipboard me-1"></i>Kopieren
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistik -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-indigo"><?= $participants ?></div>
            <div class="text-muted small">Aktive Teiln.</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-indigo"><?= array_sum(array_column($cloudData, 'vote_count')) ?></div>
            <div class="text-muted small">Stimmen</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-indigo"><?= count($cloudData) ?></div>
            <div class="text-muted small">Symbole</div>
        </div>
    </div>
</div>

<!-- Aktionen -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-white">Aktionen</div>
    <div class="card-body d-flex gap-2 flex-wrap">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?php if ($session['status'] === 'active'): ?>
            <input type="hidden" name="action" value="close">
            <button type="submit" class="btn btn-warning"
                    onclick="return confirm('Sitzung schließen?')">
                <i class="bi bi-stop-circle me-1"></i>Sitzung schließen
            </button>
            <?php else: ?>
            <input type="hidden" name="action" value="reopen">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-play-circle me-1"></i>Wieder öffnen
            </button>
            <?php endif; ?>
        </form>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-outline-danger"
                    onclick="return confirm('Alle Stimmen zurücksetzen?')">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Stimmen zurücksetzen
            </button>
        </form>
    </div>
</div>

<!-- Aktuelle Ergebnisse -->
<?php if (!empty($cloudData)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold bg-white">Aktuelle Ergebnisse</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Symbol</th>
                        <th>Bezeichnung</th>
                        <th class="text-end">Stimmen</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Bild-URL für custom Symbole nachschlagen
                $customUrls = [];
                foreach ($session['predefined_symbols'] as $sym) {
                    $aid = (int)$sym['arasaac_id'];
                    if ($aid < 0 && !empty($sym['image_url'])) $customUrls[$aid] = $sym['image_url'];
                }
                foreach ($cloudData as $row):
                    $aid    = (int)$row['arasaac_id'];
                    $imgUrl = $customUrls[$aid] ?? WordCloudManager::ARASAAC_CDN . '/' . $aid . '/' . $aid . '_300.png';
                ?>
                <tr>
                    <td><img src="<?= e($imgUrl) ?>" style="width:40px;height:40px;object-fit:contain;" alt=""></td>
                    <td><?= e($row['label']) ?></td>
                    <td class="text-end fw-bold"><?= $row['vote_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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
    }).catch(() => { prompt('Manuell kopieren:', text); });
}
</script>
</body>
</html>
