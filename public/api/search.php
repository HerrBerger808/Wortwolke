<?php
/**
 * ARASAAC-Suchproxy
 * GET /api/search.php?q=essen&lang=de
 * Kein Login erforderlich.
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q    = trim($_GET['q'] ?? '');
$lang = preg_replace('/[^a-z]/', '', strtolower($_GET['lang'] ?? APP_LANG ?? 'de')) ?: 'de';

if (mb_strlen($q) < 2 || mb_strlen($q) > 100) {
    echo '[]';
    exit;
}

$mgr = new WordCloudManager();
echo json_encode($mgr->searchArasaac($q, $lang), JSON_UNESCAPED_UNICODE);
