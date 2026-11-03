<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
Auth::require();
Auth::requireCsrf();

$action = post('action');
$id     = (int) post('id');

if (!$id || !in_array($action, ['close','reopen','delete','reset'], true)) {
    setFlash('danger', 'Ungültige Anfrage.');
    header('Location: /admin/');
    exit;
}

$mgr     = new WordCloudManager();
$session = $mgr->getSession($id);

if (!$session) {
    setFlash('danger', 'Sitzung nicht gefunden.');
    header('Location: /admin/');
    exit;
}

// Lehrer dürfen nur eigene, nicht-Gast-Sitzungen verwalten
if (!Auth::isAdmin()) {
    if (!empty($session['is_guest'])) {
        setFlash('danger', 'Keine Berechtigung.');
        header('Location: /admin/');
        exit;
    }
    if ((int)($session['created_by'] ?? 0) !== Auth::currentUserId()) {
        setFlash('danger', 'Keine Berechtigung.');
        header('Location: /admin/');
        exit;
    }
}

$title = $session['title'];

match ($action) {
    'close'  => [$mgr->closeSession($id),   setFlash('success', 'Sitzung "' . $title . '" geschlossen.')],
    'reopen' => [$mgr->reopenSession($id),  setFlash('success', 'Sitzung "' . $title . '" wieder geöffnet.')],
    'delete' => [$mgr->deleteSession($id),  setFlash('success', 'Sitzung "' . $title . '" gelöscht.')],
    'reset'  => [$mgr->resetVotes($id),     setFlash('success', 'Stimmen der Sitzung "' . $title . '" zurueckgesetzt.')],
    default  => null,
};

header('Location: /admin/');
exit;
