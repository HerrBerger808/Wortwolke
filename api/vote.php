<?php
/**
 * Stimme umschalten (toggle)
 * POST /api/vote.php  (JSON-Body)
 * Body: { session_id, token, arasaac_id, label }
 * Kein Login erforderlich.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    jsonResponse(['success' => false, 'error' => 'Ungültiger JSON-Body'], 400);
}

$sessionId = (int)   ($body['session_id'] ?? 0);
$token     = trim((string) ($body['token']      ?? ''));
$arasaacId = (int)   ($body['arasaac_id'] ?? 0);
$label     = trim((string) ($body['label']      ?? ''));

if ($sessionId <= 0)                         jsonResponse(['success'=>false,'error'=>'Ungültige session_id'], 400);
if (!WordCloudManager::isValidToken($token)) jsonResponse(['success'=>false,'error'=>'Ungültiges Token'], 400);
if ($arasaacId <= 0)                         jsonResponse(['success'=>false,'error'=>'Ungültige arasaac_id'], 400);
if ($label === '')                           jsonResponse(['success'=>false,'error'=>'Label fehlt'], 400);

$mgr = new WordCloudManager();
jsonResponse($mgr->toggleVote($sessionId, $token, $arasaacId, $label));
