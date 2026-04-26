<?php
/**
 * Aktuelle Cloud-Daten abrufen (Polling)
 * GET /api/data.php?session_id=123&token=xxxxx
 * Kein Login erforderlich.
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';

$sessionId = (int)  ($_GET['session_id'] ?? 0);
$token     = trim(   $_GET['token']      ?? '');

if ($sessionId <= 0) {
    jsonResponse(['error' => 'Ungültige session_id'], 400);
}

$mgr     = new WordCloudManager();
$session = $mgr->getSession($sessionId);

if (!$session) {
    jsonResponse(['error' => 'Sitzung nicht gefunden'], 404);
}

$items        = $mgr->getCloudData($sessionId);
$myVotes      = (WordCloudManager::isValidToken($token))
                ? $mgr->getParticipantVotes($sessionId, $token)
                : [];
$participants = $mgr->activeParticipants($sessionId);

// Bild-URLs für eigene Symbole (negative IDs) nachschlagen
$customUrls = [];
foreach ($session['predefined_symbols'] as $sym) {
    $aid = (int) $sym['arasaac_id'];
    if ($aid < 0 && !empty($sym['image_url'])) {
        $customUrls[$aid] = $sym['image_url'];
    }
}
foreach ($items as &$item) {
    $aid = (int) $item['arasaac_id'];
    if (isset($customUrls[$aid])) {
        $item['image_url'] = $customUrls[$aid];
    }
}
unset($item);

jsonResponse([
    'status'       => $session['status'],
    'items'        => $items,
    'my_votes'     => $myVotes,
    'participants' => $participants,
]);
