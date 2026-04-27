<?php
/**
 * Admin-Layout-Hilfsfunktionen
 * Einbinden mit: require __DIR__ . '/layout.php';
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
    $links = [
        ['/admin/',            'grid-fill',        'Übersicht'],
        ['/admin/create.php', 'plus-circle-fill', 'Neue Sitzung'],
    ];
    if (Auth::isAdmin()) {
        $links[] = ['/admin/guests.php',   'person-plus-fill', 'Gastsitzungen'];
        $links[] = ['/admin/users.php',    'people-fill',      'Benutzer'];
        $links[] = ['/admin/settings.php', 'gear-fill',        'Einstellungen'];
    }
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
                <span class="text-white-50 small d-none d-lg-inline">
                    <i class="bi bi-person-circle me-1"></i>' . e(Auth::currentUsername()) . '
                </span>
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
    $imp = impressumLink('color:#6b7280;font-size:11px;text-decoration:none;');
    echo '</div><!-- /admin-content -->'
       . ($imp ? '<footer style="text-align:center;padding:10px 20px;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb;">' . $imp . '</footer>' : '')
       . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>'
       . '</body></html>';
}
