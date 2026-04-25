<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';

// Bereits eingeloggt
if (Auth::check()) {
    header('Location: /admin/');
    exit;
}

$error    = '';
$redirect = get('redirect', '/admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (Auth::login($password)) {
        header('Location: ' . $redirect);
        exit;
    }
    $error = 'Falsches Passwort.';
    // Kurze Verzögerung gegen Brute-Force
    sleep(1);
}

$title = appTitle();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Login – <?= e($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f4f8; }
        .login-card { max-width: 420px; margin: 100px auto; }
        .login-header { background: linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff;
                        border-radius:16px 16px 0 0; padding:28px; text-align:center; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="card shadow-lg border-0" style="border-radius:16px;">
        <div class="login-header">
            <i class="bi bi-shield-lock-fill" style="font-size:2.5rem;"></i>
            <h3 class="mt-2 mb-0 fw-bold">Admin-Bereich</h3>
            <p class="mb-0 opacity-75 small"><?= e($title) ?></p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">
                <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
            </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Passwort</label>
                    <input type="password" name="password" class="form-control form-control-lg"
                           autofocus autocomplete="current-password" placeholder="Admin-Passwort">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-arrow-right-circle me-2"></i>Anmelden
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="/" class="text-muted small">← Zur Teilnehmer-Ansicht</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
