<?php
/**
 * ARASAAC Wortwolke – Konfiguration
 *
 * Diese Datei als config.php kopieren und anpassen:
 *   cp config.example.php config.php
 *
 * WICHTIG: config.php niemals einchecken (steht in .gitignore)!
 * Die Installation kann auch über install.php vorgenommen werden.
 */

// ---- Datenbank -------------------------------------------------------
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'wordcloud');
define('DB_USER',    'wordcloud_user');
define('DB_PASS',    'sicheres_passwort');
define('DB_CHARSET', 'utf8mb4');

// ---- Admin-Passwort (bcrypt-Hash) ------------------------------------
// Hash erzeugen: php -r "echo password_hash('meinPasswort', PASSWORD_BCRYPT);"
define('ADMIN_HASH', '$2y$12$BEISPIEL_HASH_HIER_ERSETZEN');

// ---- Anwendung -------------------------------------------------------
define('APP_TITLE',  'ARASAAC Wortwolke');
define('APP_LANG',   'de');          // Standardsprache für ARASAAC-Suche
define('POLL_MS',    3000);          // Polling-Intervall in Millisekunden
