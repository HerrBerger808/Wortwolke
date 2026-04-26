<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$mgr     = new WordCloudManager();
$id      = (int) get('id');
$session = $mgr->getSession($id);

if (!$session) {
    setFlash('danger', 'Sitzung nicht gefunden.');
    header('Location: /admin/');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();

    $session['title'] = post('title');
    $session['mode']  = post('mode', 'both');
    if (!in_array($session['mode'], ['symbols','search','both'])) $session['mode'] = 'both';

    $symbols = [];
    if ($session['mode'] !== 'search') {
        $ids       = $_POST['symbol_id']        ?? [];
        $labels    = $_POST['symbol_label']     ?? [];
        $imageUrls = $_POST['symbol_image_url'] ?? [];
        foreach ($ids as $i => $rawId) {
            $rid      = (int) $rawId;
            $label    = trim($labels[$i] ?? '');
            $imageUrl = trim($imageUrls[$i] ?? '');
            if ($rid !== 0 && $label !== '') {
                $sym = ['arasaac_id' => $rid, 'label' => $label];
                if ($rid < 0 && preg_match('#^/uploads/img_[a-f0-9_.]+\.(jpg|png|gif|webp)$#', $imageUrl)) {
                    $sym['image_url'] = $imageUrl;
                }
                $symbols[] = $sym;
            }
            if (count($symbols) >= WordCloudManager::MAX_SYMBOLS) break;
        }
        $session['predefined_symbols'] = $symbols;
    }

    if (empty($session['title'])) $errors[] = 'Bitte einen Titel eingeben.';
    if ($session['mode'] === 'symbols' && empty($symbols))
        $errors[] = 'Im Modus "Nur Symbole" muss mindestens ein Symbol hinzugefuegt werden.';

    if (empty($errors)) {
        $mgr->updateSession($id, $session['title'], $session['mode'], $symbols);
        setFlash('success', 'Sitzung aktualisiert.');
        header('Location: /admin/');
        exit;
    }
}

$existingJs = json_encode(
    array_map(fn($s) => [
        'id'        => (int)$s['arasaac_id'],
        'label'     => $s['label'],
        'image_url' => (int)$s['arasaac_id'] < 0
            ? ($s['image_url'] ?? '')
            : WordCloudManager::imageUrl((int)$s['arasaac_id']),
    ], $session['predefined_symbols']),
    JSON_UNESCAPED_UNICODE
);

adminHead('Sitzung bearbeiten');
adminNav('');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-pencil-fill text-indigo me-2"></i>Sitzung bearbeiten</h2>
    <a href="/admin/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
</div>

<div class="alert alert-secondary py-2 mb-3 small">
    <i class="bi bi-key me-2"></i>Code: <strong class="font-monospace"><?= e($session['session_code']) ?></strong>
    &nbsp;·&nbsp;
    Status: <span class="badge bg-<?= $session['status']==='active'?'success':'secondary' ?>">
        <?= $session['status']==='active'?'Aktiv':'Geschlossen' ?>
    </span>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="mainForm">
    <?= Auth::csrfInput() ?>
    <?php include __DIR__ . '/_session_form.php'; ?>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-2"></i>Speichern
        </button>
        <a href="/admin/" class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</form>

<?php
require __DIR__ . '/_session_form_js.php';
adminFoot();
?>
