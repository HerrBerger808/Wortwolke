<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';
Auth::requireAdmin();

$pdo    = DB::get();
$errors = [];
$ok     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $act = post('action');

    if ($act === 'create') {
        $uname  = trim(post('username'));
        $pass   = $_POST['password']  ?? '';
        $pass2  = $_POST['password2'] ?? '';
        $isAdm  = isset($_POST['is_admin']) ? 1 : 0;

        if ($uname === '')            $errors[] = 'Benutzername darf nicht leer sein.';
        if (!preg_match('/^\w{2,32}$/', $uname)) $errors[] = 'Benutzername: 2–32 Zeichen, nur Buchstaben, Ziffern, _.';
        if (strlen($pass) < 8)       $errors[] = 'Passwort muss mindestens 8 Zeichen haben.';
        if ($pass !== $pass2)         $errors[] = 'Passwörter stimmen nicht überein.';

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "INSERT INTO wordcloud_users (username, password_hash, is_admin) VALUES (:u, :h, :a)"
                )->execute([
                    ':u' => $uname,
                    ':h' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]),
                    ':a' => $isAdm,
                ]);
                $ok = 'Benutzer "' . $uname . '" wurde angelegt.';
            } catch (\PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Benutzername "' . $uname . '" ist bereits vergeben.'
                    : 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }

    if ($act === 'delete') {
        $delId = (int) post('id');
        if ($delId === Auth::currentUserId()) {
            $errors[] = 'Sie können sich nicht selbst löschen.';
        } elseif ($delId > 0) {
            // Letzten Admin nicht löschen
            $row = $pdo->prepare("SELECT username, is_admin FROM wordcloud_users WHERE id = :id");
            $row->execute([':id' => $delId]);
            $target = $row->fetch();
            if ($target && $target['is_admin']) {
                $cnt = (int) $pdo->query("SELECT COUNT(*) FROM wordcloud_users WHERE is_admin = 1")->fetchColumn();
                if ($cnt <= 1) {
                    $errors[] = 'Der letzte Administrator kann nicht gelöscht werden.';
                }
            }
            if (empty($errors) && $target) {
                $pdo->prepare("DELETE FROM wordcloud_users WHERE id = :id")->execute([':id' => $delId]);
                $ok = 'Benutzer "' . $target['username'] . '" wurde gelöscht.';
            }
        }
    }

    if ($act === 'set_password') {
        $uid   = (int) post('id');
        $pass  = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (strlen($pass) < 8)  $errors[] = 'Passwort muss mindestens 8 Zeichen haben.';
        if ($pass !== $pass2)    $errors[] = 'Passwörter stimmen nicht überein.';
        if (empty($errors) && $uid > 0) {
            $pdo->prepare("UPDATE wordcloud_users SET password_hash = :h WHERE id = :id")
                ->execute([':h' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), ':id' => $uid]);
            $ok = 'Passwort wurde geändert.';
        }
    }
}

$users = $pdo->query("SELECT id, username, is_admin, created_at FROM wordcloud_users ORDER BY id")->fetchAll();

adminHead('Benutzer');
adminNav('/admin/users.php');
echo renderFlash();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">
        <i class="bi bi-people-fill text-indigo me-2"></i>Benutzerverwaltung
    </h2>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0 small">
        <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php if ($ok): ?>
<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-2"></i><?= e($ok) ?></div>
<?php endif; ?>

<?php if (defined('ADMIN_HASH') && empty($users)): ?>
<div class="alert alert-warning small">
    <i class="bi bi-info-circle me-2"></i>
    Sie sind derzeit über <code>config.php</code> angemeldet (Benutzername <strong>admin</strong>).
    Legen Sie hier einen Datenbankbenutzer an, um unabhängig von der Konfigurationsdatei zu sein.
</div>
<?php endif; ?>

<!-- Benutzerliste -->
<?php if (!empty($users)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-white">Vorhandene Benutzer</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Benutzername</th>
                    <th>Rolle</th>
                    <th>Angelegt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="fw-semibold">
                    <i class="bi bi-person-circle me-1 text-muted"></i><?= e($u['username']) ?>
                    <?php if ($u['id'] === Auth::currentUserId()): ?>
                        <span class="badge bg-indigo ms-1">Sie</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['is_admin']): ?>
                        <span class="badge bg-danger"><i class="bi bi-shield-fill me-1"></i>Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Benutzer</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= fmtDate($u['created_at']) ?></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" title="Passwort ändern"
                                onclick="openPwModal(<?= $u['id'] ?>, '<?= e(addslashes($u['username'])) ?>')">
                            <i class="bi bi-key"></i>
                        </button>
                        <?php if ($u['id'] !== Auth::currentUserId()): ?>
                        <form method="POST" class="d-inline">
                            <?= Auth::csrfInput() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger"
                                    onclick="return confirm('Benutzer &quot;<?= e(addslashes($u['username'])) ?>&quot; löschen?')"
                                    title="Löschen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Neuen Benutzer anlegen -->
<div class="card border-0 shadow-sm" style="max-width:500px;">
    <div class="card-header fw-semibold bg-white">
        <i class="bi bi-person-plus me-2 text-indigo"></i>Neuen Benutzer anlegen
    </div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Benutzername</label>
                <input type="text" name="username" class="form-control"
                       pattern="\w{2,32}" placeholder="z. B. lehrer1" autocomplete="off">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label fw-semibold small">Passwort</label>
                    <input type="password" name="password" class="form-control"
                           autocomplete="new-password" placeholder="Mind. 8 Zeichen">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold small">Wiederholen</label>
                    <input type="password" name="password2" class="form-control"
                           autocomplete="new-password">
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_admin" id="is_admin" class="form-check-input" value="1">
                <label class="form-check-label small" for="is_admin">
                    Administrator (darf Benutzer verwalten)
                </label>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Benutzer anlegen
            </button>
        </form>
    </div>
</div>

<!-- Passwort-Ändern-Modal -->
<div class="modal fade" id="pwModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">Passwort ändern</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= Auth::csrfInput() ?>
                <input type="hidden" name="action" value="set_password">
                <input type="hidden" name="id" id="pwUserId">
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">Benutzer: <strong id="pwUsername"></strong></p>
                    <div class="mb-2">
                        <input type="password" name="password" class="form-control"
                               autocomplete="new-password" placeholder="Neues Passwort (mind. 8 Zeichen)">
                    </div>
                    <div>
                        <input type="password" name="password2" class="form-control"
                               autocomplete="new-password" placeholder="Wiederholen">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPwModal(id, name) {
    document.getElementById('pwUserId').value  = id;
    document.getElementById('pwUsername').textContent = name;
    new bootstrap.Modal(document.getElementById('pwModal')).show();
}
</script>

<?php adminFoot(); ?>
