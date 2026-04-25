<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$errors  = [];
$session = ['title' => '', 'mode' => 'both', 'predefined_symbols' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();

    $session['title'] = post('title');
    $session['mode']  = post('mode', 'both');
    if (!in_array($session['mode'], ['symbols','search','both'])) $session['mode'] = 'both';

    $symbols = [];
    if ($session['mode'] !== 'search') {
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
        $session['predefined_symbols'] = $symbols;
    }

    if (empty($session['title'])) $errors[] = 'Bitte einen Titel eingeben.';
    if ($session['mode'] === 'symbols' && empty($symbols))
        $errors[] = 'Im Modus „Nur Symbole" muss mindestens ein Symbol hinzugefügt werden.';

    if (empty($errors)) {
        $mgr    = new WordCloudManager();
        $result = $mgr->createSession($session['title'], $session['mode'], $symbols);
        setFlash('success', 'Sitzung angelegt. Code: <strong class="font-monospace">'
            . htmlspecialchars($result['code']) . '</strong>');
        header('Location: /admin/');
        exit;
    }
}

$existingJs = json_encode(
    array_map(fn($s) => [
        'id'        => (int)$s['arasaac_id'],
        'label'     => $s['label'],
        'image_url' => WordCloudManager::imageUrl((int)$s['arasaac_id']),
    ], $session['predefined_symbols']),
    JSON_UNESCAPED_UNICODE
);

adminHead('Neue Sitzung');
adminNav('/admin/create.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-plus-circle-fill text-indigo me-2"></i>Neue Sitzung</h2>
    <a href="/admin/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
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
            <i class="bi bi-check2 me-2"></i>Sitzung erstellen
        </button>
        <a href="/admin/" class="btn btn-outline-secondary">Abbrechen</a>
    </div>
</form>

<?php
require __DIR__ . '/_session_form_js.php';
adminFoot();
?>
