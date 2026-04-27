<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

$title = appTitle();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eaf6 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .home-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 40px rgba(79,70,229,.15);
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .home-icon {
            width: 80px; height: 80px; border-radius: 24px;
            background: linear-gradient(135deg,#4f46e5,#7c3aed);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem; color: #fff;
        }
        .code-input {
            font-size: 2rem;
            letter-spacing: 6px;
            font-family: monospace;
            text-align: center;
            text-transform: uppercase;
            border: 2px solid #e9ecef;
            border-radius: 14px;
            padding: 14px 20px;
            width: 100%;
            transition: border-color .2s;
            background: #fafafa;
        }
        .code-input:focus {
            border-color: #4f46e5;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79,70,229,.1);
            outline: none;
        }
        .btn-join {
            background: linear-gradient(135deg,#4f46e5,#7c3aed);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: opacity .2s, transform .1s;
        }
        .btn-join:hover   { opacity: .92; color: #fff; }
        .btn-join:active  { transform: scale(.97); }
        .admin-link { font-size: 13px; color: #999; }
        .admin-link a { color: #999; text-decoration: none; }
        .admin-link a:hover { color: #4f46e5; }
    </style>
</head>
<body>
<div class="home-card">
    <div class="home-icon">
        <i class="bi bi-chat-square-text-fill"></i>
    </div>
    <h1 class="fw-bold mb-1" style="font-size:1.6rem;"><?= e($title) ?></h1>
    <p class="text-muted mb-4">Gib den Code deines Lehrers ein, um zur Wortwolke zu gelangen.</p>

    <form action="/join.php" method="GET">
        <input type="text" name="code" id="codeInput"
               class="code-input mb-4"
               placeholder="XXXXXX"
               maxlength="8"
               autofocus
               autocomplete="off"
               spellcheck="false">
        <button type="submit" class="btn btn-join">
            <i class="bi bi-arrow-right-circle me-2"></i>Beitreten
        </button>
    </form>

    <div class="admin-link mt-4">
        <a href="/admin/"><i class="bi bi-lock me-1"></i>Admin-Bereich</a>
    </div>
    <?php $imp = impressumLink(); if ($imp): ?>
    <div style="margin-top:20px;"><?= $imp ?></div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Code automatisch großschreiben
document.getElementById('codeInput').addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    this.setSelectionRange(pos, pos);
});
</script>
</body>
</html>
