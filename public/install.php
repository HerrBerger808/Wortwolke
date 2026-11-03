<?php
/**
 * ARASAAC Wortwolke – Installations-Assistent
 * Unterstützt MySQL/MariaDB und SQLite.
 */
define('APP_ROOT', dirname(__DIR__));

if (file_exists(APP_ROOT . '/config.php')) {
    header('Location: /admin/');
    exit;
}

require_once APP_ROOT . '/includes/db.php';

$step   = 1;
$dbType = $_POST['db_type'] ?? 'mysql';
$errors = [];
$ok     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    $step   = (int) $_POST['step'];
    $dbType = in_array($_POST['db_type'] ?? '', ['mysql', 'sqlite']) ? $_POST['db_type'] : 'mysql';

    if ($step === 2) {
        $appTitle   = trim($_POST['app_title']  ?? 'ARASAAC Wortwolke');
        $adminPass  = $_POST['admin_pass']  ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';

        if (empty($adminPass))          $errors[] = 'Admin-Passwort eingeben.';
        if (strlen($adminPass) < 8)     $errors[] = 'Admin-Passwort muss mindestens 8 Zeichen haben.';
        if ($adminPass !== $adminPass2) $errors[] = 'Passwörter stimmen nicht überein.';

        if ($dbType === 'mysql') {
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = (int) ($_POST['db_port'] ?? 3306);
            $dbName = trim($_POST['db_name'] ?? 'wordcloud');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = trim($_POST['db_pass'] ?? '');
            if (empty($dbUser)) $errors[] = 'Datenbankbenutzer eingeben.';
            if (empty($errors)) {
                $test = DB::testConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
                if (!$test['success']) $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . $test['error'];
            }
        } else {
            $dbPath = trim($_POST['db_path'] ?? (APP_ROOT . '/data/wordcloud.sqlite'));
            if (empty($dbPath)) $dbPath = APP_ROOT . '/data/wordcloud.sqlite';
            // Prevent placing the database inside the web root (public/)
            $publicReal = realpath(APP_ROOT . '/public') ?: (APP_ROOT . '/public');
            $dbReal     = realpath(dirname($dbPath)) ?: dirname($dbPath);
            if (str_starts_with($dbReal . '/', $publicReal . '/')) {
                $errors[] = 'Die SQLite-Datei darf nicht im Web-Root (public/) liegen.';
            }
            if (empty($errors)) {
                $test = DB::testSQLite($dbPath);
                if (!$test['success']) $errors[] = 'SQLite-Datei nicht anlegbar: ' . $test['error'];
            }
        }

        if (empty($errors)) {
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            if ($dbType === 'sqlite') {
                $content = "<?php\n"
                    . "define('DB_TYPE',   'sqlite');\n"
                    . "define('DB_PATH',   " . var_export($dbPath,    true) . ");\n"
                    . "define('ADMIN_HASH'," . var_export($hash,      true) . ");\n"
                    . "define('APP_TITLE', " . var_export($appTitle,  true) . ");\n"
                    . "define('APP_LANG',  'de');\n"
                    . "define('POLL_MS',   3000);\n";
            } else {
                $content = "<?php\n"
                    . "define('DB_HOST',    " . var_export($dbHost,   true) . ");\n"
                    . "define('DB_PORT',    " . $dbPort . ");\n"
                    . "define('DB_NAME',    " . var_export($dbName,   true) . ");\n"
                    . "define('DB_USER',    " . var_export($dbUser,   true) . ");\n"
                    . "define('DB_PASS',    " . var_export($dbPass,   true) . ");\n"
                    . "define('DB_CHARSET', 'utf8mb4');\n"
                    . "define('ADMIN_HASH', " . var_export($hash,     true) . ");\n"
                    . "define('APP_TITLE',  " . var_export($appTitle, true) . ");\n"
                    . "define('APP_LANG',   'de');\n"
                    . "define('POLL_MS',    3000);\n";
            }

            if (file_put_contents(APP_ROOT . '/config.php', $content) === false) {
                $errors[] = 'config.php konnte nicht geschrieben werden. Bitte Schreibrechte prüfen.';
            } else {
                require_once APP_ROOT . '/config.php';
                try {
                    DB::createSchema();
                    DB::migrateSchema();
                    $ok = true;
                } catch (RuntimeException $e) {
                    $errors[] = 'Datenbankschema fehlgeschlagen: ' . $e->getMessage();
                    @unlink(APP_ROOT . '/config.php');
                }
            }
        }
        if (!empty($errors)) $step = 1;
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation – ARASAAC Wortwolke</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f4f8; }
        .install-card { max-width: 620px; margin: 40px auto; }
        .install-header { background: linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border-radius: 16px 16px 0 0; padding: 32px; text-align: center; }
        .install-header i { font-size: 3rem; }
        .db-opt { cursor:pointer; border-radius:10px; padding:14px 16px; border:2px solid #e9ecef; transition:border-color .15s,background .15s; }
        .db-opt:has(input:checked) { border-color:#4f46e5; background:#f5f3ff; }
    </style>
</head>
<body>
<div class="install-card">
<div class="card shadow-lg border-0" style="border-radius:16px;">
    <div class="install-header">
        <i class="bi bi-chat-square-text-fill"></i>
        <h2 class="mt-2 mb-0 fw-bold">ARASAAC Wortwolke</h2>
        <p class="mb-0 opacity-75">Installations-Assistent</p>
    </div>
    <div class="card-body p-4">

    <?php if ($ok): ?>
        <div class="text-center py-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
            <h4 class="mt-3 fw-bold">Installation abgeschlossen!</h4>
            <p class="text-muted">Datenbank und Konfiguration wurden erfolgreich eingerichtet.</p>
            <a href="/admin/" class="btn btn-primary btn-lg mt-2">
                <i class="bi bi-arrow-right-circle me-2"></i>Zum Admin-Bereich
            </a>
        </div>
    <?php else: ?>
        <h5 class="fw-semibold mb-1">Einrichtung</h5>
        <p class="text-muted small mb-4">Datenbanktyp, Zugangsdaten und Admin-Passwort festlegen.</p>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 small">
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="installForm">
            <input type="hidden" name="step" value="2">

            <!-- DB-Typ -->
            <h6 class="fw-semibold text-muted mb-2">Datenbanktyp</h6>
            <div class="row g-2 mb-4">
                <div class="col-6">
                    <label class="db-opt d-flex align-items-start gap-2 w-100">
                        <input type="radio" name="db_type" value="mysql" class="mt-1"
                               <?= $dbType !== 'sqlite' ? 'checked' : '' ?>>
                        <div>
                            <div class="fw-semibold"><i class="bi bi-database-fill text-primary me-1"></i>MySQL / MariaDB</div>
                            <small class="text-muted">Empfohlen für Produktivbetrieb. Separater Datenbankserver erforderlich.</small>
                        </div>
                    </label>
                </div>
                <div class="col-6">
                    <label class="db-opt d-flex align-items-start gap-2 w-100">
                        <input type="radio" name="db_type" value="sqlite"
                               <?= $dbType === 'sqlite' ? 'checked' : '' ?>>
                        <div>
                            <div class="fw-semibold"><i class="bi bi-file-earmark-binary text-success me-1"></i>SQLite</div>
                            <small class="text-muted">Einfachste Option – kein separater Datenbankserver nötig. Ideal für kleine Installationen.</small>
                        </div>
                    </label>
                </div>
            </div>

            <!-- MySQL-Felder -->
            <div id="mysqlFields" <?= $dbType === 'sqlite' ? 'style="display:none"' : '' ?>>
                <h6 class="fw-semibold text-muted mb-2">Datenbankverbindung</h6>
                <div class="row g-2 mb-2">
                    <div class="col-8">
                        <label class="form-label small fw-semibold">Host</label>
                        <input type="text" name="db_host" class="form-control"
                               value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Port</label>
                        <input type="number" name="db_port" class="form-control"
                               value="<?= (int) ($_POST['db_port'] ?? 3306) ?>">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Datenbankname</label>
                        <input type="text" name="db_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['db_name'] ?? 'wordcloud') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Benutzer</label>
                        <input type="text" name="db_user" class="form-control"
                               value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Passwort</label>
                        <input type="password" name="db_pass" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-lightbulb me-1"></i>Datenbankbenutzer anlegen:<br>
                    <code>CREATE USER 'wordcloud_user'@'localhost' IDENTIFIED BY 'passwort';<br>
                    GRANT ALL ON wordcloud.* TO 'wordcloud_user'@'localhost';<br>
                    FLUSH PRIVILEGES;</code>
                </div>
            </div>

            <!-- SQLite-Felder -->
            <div id="sqliteFields" <?= $dbType !== 'sqlite' ? 'style="display:none"' : '' ?>>
                <h6 class="fw-semibold text-muted mb-2">Datenbankdatei</h6>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Dateipfad (absolut, außerhalb public/)</label>
                    <input type="text" name="db_path" class="form-control font-monospace"
                           value="<?= htmlspecialchars($_POST['db_path'] ?? (APP_ROOT . '/data/wordcloud.sqlite')) ?>">
                    <div class="text-muted small mt-1">
                        Das Verzeichnis wird automatisch angelegt. Stelle sicher, dass der Web-Server Schreibrechte hat.
                    </div>
                </div>
                <div class="alert alert-success py-2 small mb-3">
                    <i class="bi bi-check-circle me-1"></i>Kein separater Datenbankserver nötig – die Datei wird automatisch erstellt.
                </div>
            </div>

            <!-- App + Admin -->
            <h6 class="fw-semibold text-muted mb-2">Anwendung</h6>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Anwendungstitel</label>
                <input type="text" name="app_title" class="form-control"
                       value="<?= htmlspecialchars($_POST['app_title'] ?? 'ARASAAC Wortwolke') ?>">
            </div>

            <h6 class="fw-semibold text-muted mb-2">Admin-Zugang</h6>
            <div class="row g-2 mb-4">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Passwort</label>
                    <input type="password" name="admin_pass" class="form-control"
                           autocomplete="new-password" placeholder="Mind. 8 Zeichen">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Wiederholen</label>
                    <input type="password" name="admin_pass2" class="form-control" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-play-circle me-2"></i>Installation starten
            </button>
        </form>
    <?php endif; ?>

    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const radios = document.querySelectorAll('input[name="db_type"]');
    const mysql  = document.getElementById('mysqlFields');
    const sqlite = document.getElementById('sqliteFields');
    function toggle() {
        const v = document.querySelector('input[name="db_type"]:checked')?.value;
        if (mysql)  mysql.style.display  = v === 'sqlite' ? 'none' : '';
        if (sqlite) sqlite.style.display = v !== 'sqlite' ? 'none' : '';
    }
    radios.forEach(r => r.addEventListener('change', toggle));
})();
</script>
</body>
</html>
