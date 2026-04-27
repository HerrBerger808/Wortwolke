<?php
/**
 * Hilfsfunktionen
 */

/** HTML-Sonderzeichen escapen */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** POST-Wert trimmen */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/** GET-Wert trimmen */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/** Flash-Nachricht setzen */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Flash-Nachrichten als Bootstrap-Alerts ausgeben */
function renderFlash(): string
{
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible shadow-sm">'
               . $f['message']
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/** Datum formatieren */
function fmtDate(string $dt): string
{
    return $dt ? date('d.m.Y', strtotime($dt)) : '–';
}

function fmtDateTime(string $dt): string
{
    return $dt ? date('d.m.Y H:i', strtotime($dt)) : '–';
}

/** App-Titel (mit Fallback) */
function appTitle(): string
{
    return defined('APP_TITLE') ? APP_TITLE : 'ARASAAC Wortwolke';
}

/** Impressum-Link (leer wenn keine URL konfiguriert) */
function impressumLink(string $style = 'color:#9ca3af;font-size:11px;text-decoration:none;'): string
{
    try {
        $url = (new WordCloudManager())->getSetting('impressum_url', '');
    } catch (\Throwable $e) {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer"'
         . ($style ? ' style="' . htmlspecialchars($style) . '"' : '') . '>Impressum</a>';
}

/** JSON-Response ausgeben und beenden */
function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
