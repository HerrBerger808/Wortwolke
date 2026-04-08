<?php
/**
 * Aktuelle Cloud-Daten abrufen (Polling)
 * GET /api/data.php?session_id=123&token=xxxxx
 * Kein Login erforderlich.
 */
define('APP_ROOT', dirname(__DIR__));
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

jsonResponse([
    'status'       => $session['status'],
    'items'        => $items,
    'my_votes'     => $myVotes,
    'participants' => $participants,
]);
