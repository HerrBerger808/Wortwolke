<?php
/**
 * Admin-Layout-Hilfsfunktionen
 * Einbinden mit: require APP_ROOT . '/admin/layout.php';
 */

function adminHead(string $pageTitle): void
{
    $title = appTitle();
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($pageTitle) . ' – ' . e($title) . '</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin-body">
';
}

function adminNav(string $currentPath = ''): void
{
    $title  = appTitle();
    $links  = [
        ['/admin/',         'grid-fill',            'Übersicht'],
        ['/admin/create.php','plus-circle-fill',    'Neue Sitzung'],
    ];
    echo '<nav class="admin-nav navbar navbar-expand-lg navbar-dark bg-indigo">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="/admin/">
            <i class="bi bi-chat-square-text-fill"></i>' . e($title) . '
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">';
    foreach ($links as [$href, $icon, $label]) {
        $active = (strpos($currentPath, $href) !== false && $href !== '/admin/' || $currentPath === $href) ? 'active' : '';
        if ($href === '/admin/' && ($currentPath === '/admin/' || $currentPath === '/admin/index.php')) $active = 'active';
        echo '<li class="nav-item">
            <a class="nav-link ' . $active . '" href="' . $href . '">
                <i class="bi bi-' . $icon . ' me-1"></i>' . $label . '
            </a></li>';
    }
    echo '</ul>
            <div class="d-flex align-items-center gap-3">
                <a href="/" target="_blank" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-eye me-1"></i>Teilnehmer-Ansicht
                </a>
                <a href="/admin/logout.php" class="btn btn-sm btn-light text-danger fw-semibold"
                   onclick="return confirm(\'Wirklich abmelden?\')">
                    <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                </a>
            </div>
        </div>
    </div>
</nav>
<div class="admin-content container-fluid px-4 py-4">
';
}

function adminFoot(): void
{
    echo '</div><!-- /admin-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
}
