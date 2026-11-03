<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::require();

$id      = (int) ($_GET['id'] ?? 0);
$mgr     = new WordCloudManager();
$session = $id ? $mgr->getSession($id) : null;

if (!$session) {
    setFlash('danger', 'Sitzung nicht gefunden.');
    header('Location: /admin/');
    exit;
}

$pdo = DB::get();

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $act = post('action');

    if ($act === 'delete_symbol') {
        $arasaacId = (int) post('arasaac_id');
        if ($arasaacId !== 0) {
            $pdo->prepare(
                "DELETE FROM wordcloud_votes WHERE session_id = :sid AND arasaac_id = :aid"
            )->execute([':sid' => $id, ':aid' => $arasaacId]);
            setFlash('success', 'Stimmen geloscht.');
        }
    }

    if ($act === 'delete_vote') {
        $arasaacId = (int) post('arasaac_id');
        $token     = post('token');
        if ($arasaacId !== 0 && $token !== '') {
            $pdo->prepare(
                "DELETE FROM wordcloud_votes WHERE session_id = :sid AND arasaac_id = :aid AND participant_token = :tok"
            )->execute([':sid' => $id, ':aid' => $arasaacId, ':tok' => $token]);
            setFlash('success', 'Stimme geloscht.');
        }
    }

    if ($act === 'reset_all') {
        $mgr->resetVotes($id);
        setFlash('success', 'Alle Stimmen zurueckgesetzt.');
    }

    header('Location: /admin/votes.php?id=' . $id);
    exit;
}

// Stimmen laden: pro Symbol aggregiert + Einzelstimmen
$symbols = $pdo->prepare(
    "SELECT arasaac_id, label, COUNT(*) AS vote_count
     FROM wordcloud_votes WHERE session_id = :sid
     GROUP BY arasaac_id, label ORDER BY vote_count DESC"
);
$symbols->execute([':sid' => $id]);
$symbols = $symbols->fetchAll();

$votes = $pdo->prepare(
    "SELECT arasaac_id, participant_token, voted_at
     FROM wordcloud_votes WHERE session_id = :sid
     ORDER BY arasaac_id, voted_at"
);
$votes->execute([':sid' => $id]);
$allVotes = $votes->fetchAll();

// Stimmen nach arasaac_id gruppieren
$votesBySymbol = [];
foreach ($allVotes as $v) {
    $votesBySymbol[$v['arasaac_id']][] = $v;
}

adminHead('Abstimmungen – ' . $session['title']);
adminNav('');
echo renderFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-0">
            <i class="bi bi-list-check text-indigo me-2"></i>Abstimmungen
        </h2>
        <div class="text-muted small mt-1">
            <?= e($session['title']) ?>
            <span class="badge bg-secondary ms-2 font-monospace"><?= e($session['session_code']) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zurück
        </a>
        <a href="/admin/export.php?type=votes&id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>CSV-Export
        </a>
        <?php if (!empty($symbols)): ?>
        <form method="POST" class="d-inline">
            <?= Auth::csrfInput() ?>
            <input type="hidden" name="action" value="reset_all">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Alle Stimmen dieser Sitzung loeschen?')">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Alle zurücksetzen
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($symbols)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
        Noch keine Stimmen vorhanden.
    </div>
</div>
<?php else: ?>

<div class="row g-3">
<?php
// Bild-URL-Map für eigene Symbole aufbauen
$customImgMap = [];
foreach ($session['predefined_symbols'] as $ps) {
    $psAid = (int)$ps['arasaac_id'];
    if ($psAid < 0 && !empty($ps['image_url'])) $customImgMap[$psAid] = $ps['image_url'];
}
?>
<?php foreach ($symbols as $sym):
    $aid         = (int) $sym['arasaac_id'];
    $symVotes    = $votesBySymbol[$aid] ?? [];
    $imgUrl      = $customImgMap[$aid] ?? WordCloudManager::ARASAAC_CDN . '/' . $aid . '/' . $aid . '_300.png';
?>
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <img src="<?= $imgUrl ?>" alt="" style="width:56px;height:56px;object-fit:contain;flex-shrink:0;">
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($sym['label']) ?></div>
                    <div class="text-muted small"><?= $sym['vote_count'] ?> Stimme<?= $sym['vote_count'] != 1 ? 'n' : '' ?></div>
                </div>
                <form method="POST" class="d-inline flex-shrink-0">
                    <?= Auth::csrfInput() ?>
                    <input type="hidden" name="action" value="delete_symbol">
                    <input type="hidden" name="arasaac_id" value="<?= $aid ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Alle <?= $sym['vote_count'] ?> Stimmen fur dieses Symbol loschen?')">
                        <i class="bi bi-trash me-1"></i>Alle Stimmen löschen
                    </button>
                </form>
                <button class="btn btn-sm btn-outline-secondary flex-shrink-0"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#votes-<?= $aid ?>">
                    <i class="bi bi-chevron-down me-1"></i>Einzelstimmen
                </button>
            </div>

            <div class="collapse mt-3" id="votes-<?= $aid ?>">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Teilnehmer-Token</th>
                                <th>Zeitpunkt</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($symVotes as $v): ?>
                        <tr>
                            <td class="font-monospace small text-muted">
                                <?= e(substr($v['participant_token'], 0, 12)) ?>…
                            </td>
                            <td class="small text-muted"><?= fmtDateTime($v['voted_at']) ?></td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <?= Auth::csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_vote">
                                    <input type="hidden" name="arasaac_id" value="<?= $aid ?>">
                                    <input type="hidden" name="token" value="<?= e($v['participant_token']) ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger"
                                            onclick="return confirm('Diese Stimme loschen?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<?php adminFoot(); ?>
