<?php
/**
 * Bootstrap – wird von jeder Seite als erstes eingebunden.
 * Lädt Konfiguration, startet Session, initialisiert DB.
 */

// APP_ROOT muss vor dem Einbinden gesetzt werden
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Konfiguration laden
$configFile = APP_ROOT . '/config.php';
if (!file_exists($configFile)) {
    // Weiterleitung zur Installation (außer wenn wir schon dort sind)
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!str_ends_with($script, 'install.php')) {
        header('Location: /install.php');
        exit;
    }
} else {
    require_once $configFile;
}

// Includes
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/WordCloudManager.php';

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Auto-migrate: Tabellen anlegen + migrieren (idempotent, einmal pro Schema-Version)
define('SCHEMA_VERSION', 6);
if (defined('DB_HOST') && (($_SESSION['schema_v'] ?? 0) < SCHEMA_VERSION)) {
    try {
        DB::createSchema();
        DB::migrateSchema();
        $_SESSION['schema_v'] = SCHEMA_VERSION;
    } catch (\Throwable $e) {}
}

// Abgelaufene Gastsitzungen bereinigen (max. 1× pro Minute)
if (defined('DB_HOST') && (!isset($_SESSION['guest_cleanup']) || $_SESSION['guest_cleanup'] < time() - 60)) {
    try {
        (new WordCloudManager())->cleanupExpiredGuestSessions();
        $_SESSION['guest_cleanup'] = time();
    } catch (\Throwable $e) {}
}
