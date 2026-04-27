<?php
/**
 * Hochgeladenes Bild sofort löschen (wenn noch nicht in einer gespeicherten Sitzung)
 * POST /api/remove_upload.php  JSON-Body: { image_url, session_id, csrf_token }
 * Erfordert Admin-Login.
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
Auth::require();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false], 405);
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$imageUrl  = trim((string) ($body['image_url']  ?? ''));
$sessionId = (int) ($body['session_id'] ?? 0);
$csrfToken = (string) ($body['csrf_token'] ?? '');

if (!hash_equals(Auth::getCsrfToken(), $csrfToken)) {
    jsonResponse(['success' => false, 'error' => 'CSRF'], 403);
}

$mgr = new WordCloudManager();
jsonResponse(['success' => $mgr->deleteUploadedImage($imageUrl, $sessionId)]);
