<?php
/**
 * Datenbank-Singleton (PDO) – unterstützt MySQL/MariaDB und SQLite
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function getType(): string
    {
        if (defined('DB_TYPE')) return DB_TYPE;
        if (defined('DB_PATH'))  return 'sqlite';
        return 'mysql';
    }

    public static function isConfigured(): bool
    {
        return defined('DB_HOST') || defined('DB_PATH');
    }

    public static function get(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        if (self::getType() === 'sqlite') {
            if (!defined('DB_PATH')) {
                throw new RuntimeException('Datenbank nicht konfiguriert.');
            }
            $dir = dirname(DB_PATH);
            if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
                throw new RuntimeException('Datenbankverzeichnis konnte nicht angelegt werden: ' . $dir);
            }
            try {
                self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec("PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;");
            } catch (PDOException $e) {
                throw new RuntimeException('SQLite-Verbindung fehlgeschlagen: ' . $e->getMessage());
            }
        } else {
            if (!defined('DB_HOST')) {
                throw new RuntimeException('Datenbank nicht konfiguriert.');
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    /** Tabellen anlegen (idempotent) */
    public static function createSchema(): void
    {
        if (self::getType() === 'sqlite') {
            self::createSchemaSQLite();
        } else {
            self::createSchemaMySQL();
        }
    }

    private static function createSchemaMySQL(): void
    {
        $pdo = self::get();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wordcloud_sessions (
                id                 INT AUTO_INCREMENT PRIMARY KEY,
                session_code       VARCHAR(8) NOT NULL,
                title              VARCHAR(255) NOT NULL,
                mode               ENUM('symbols','search','both') NOT NULL DEFAULT 'both',
                predefined_symbols JSON DEFAULT NULL,
                status             ENUM('active','closed') NOT NULL DEFAULT 'active',
                created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_code (session_code),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS wordcloud_votes (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                session_id        INT NOT NULL,
                participant_token VARCHAR(64) NOT NULL,
                arasaac_id        INT NOT NULL,
                label             VARCHAR(255) NOT NULL,
                voted_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES wordcloud_sessions(id) ON DELETE CASCADE,
                UNIQUE KEY uq_vote (session_id, participant_token, arasaac_id),
                INDEX idx_session (session_id, arasaac_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS wordcloud_users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(64) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_admin      TINYINT(1) NOT NULL DEFAULT 0,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    private static function createSchemaSQLite(): void
    {
        $pdo = self::get();
        // Alle aktuellen Spalten direkt anlegen – keine separate Migration für Neuinstallationen
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wordcloud_sessions (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                session_code       TEXT    NOT NULL UNIQUE,
                title              TEXT    NOT NULL,
                mode               TEXT    NOT NULL DEFAULT 'both',
                predefined_symbols TEXT    DEFAULT NULL,
                status             TEXT    NOT NULL DEFAULT 'active',
                created_at         TEXT    DEFAULT (datetime('now')),
                updated_at         TEXT    DEFAULT (datetime('now')),
                created_by         INTEGER DEFAULT NULL,
                is_guest           INTEGER NOT NULL DEFAULT 0,
                guest_admin_token  TEXT    DEFAULT NULL,
                expires_at         TEXT    DEFAULT NULL,
                display_mode       TEXT    NOT NULL DEFAULT 'cloud',
                max_symbols        INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_status      ON wordcloud_sessions(status);
            CREATE INDEX IF NOT EXISTS idx_created_by  ON wordcloud_sessions(created_by);
            CREATE INDEX IF NOT EXISTS idx_guest_token ON wordcloud_sessions(guest_admin_token);
            CREATE INDEX IF NOT EXISTS idx_expires     ON wordcloud_sessions(expires_at);

            CREATE TABLE IF NOT EXISTS wordcloud_votes (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id        INTEGER NOT NULL,
                participant_token TEXT    NOT NULL,
                arasaac_id        INTEGER NOT NULL,
                label             TEXT    NOT NULL,
                voted_at          TEXT    DEFAULT (datetime('now')),
                UNIQUE(session_id, participant_token, arasaac_id),
                FOREIGN KEY (session_id) REFERENCES wordcloud_sessions(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_vote_session ON wordcloud_votes(session_id, arasaac_id);

            CREATE TABLE IF NOT EXISTS wordcloud_users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                is_admin      INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT    DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS wordcloud_settings (
                setting_key   TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL DEFAULT ''
            );
        ");
    }

    /** Verbindungstest für MySQL (install.php) */
    public static function testConnection(
        string $host, int $port, string $name,
        string $user, string $pass
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Verbindungstest für SQLite (install.php) */
    public static function testSQLite(string $path): array
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
                return ['success' => false, 'error' => 'Verzeichnis konnte nicht angelegt werden: ' . $dir];
            }
            if (!is_writable($dir)) {
                return ['success' => false, 'error' => 'Verzeichnis nicht schreibbar: ' . $dir];
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("PRAGMA journal_mode=WAL;");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Bestehende Tabellen migrieren (idempotent) */
    public static function migrateSchema(): void
    {
        if (self::getType() === 'sqlite') {
            self::migrateSchemaSQLite();
        } else {
            self::migrateSchemaMySQL();
        }
    }

    private static function migrateSchemaSQLite(): void
    {
        $pdo  = self::get();
        $cols = array_column(
            $pdo->query("PRAGMA table_info(wordcloud_sessions)")->fetchAll(),
            'name'
        );

        $add = [
            'created_by'        => "INTEGER DEFAULT NULL",
            'is_guest'          => "INTEGER NOT NULL DEFAULT 0",
            'guest_admin_token' => "TEXT DEFAULT NULL",
            'expires_at'        => "TEXT DEFAULT NULL",
            'display_mode'      => "TEXT NOT NULL DEFAULT 'cloud'",
            'max_symbols'       => "INTEGER NOT NULL DEFAULT 0",
        ];
        foreach ($add as $col => $def) {
            if (!in_array($col, $cols)) {
                $pdo->exec("ALTER TABLE wordcloud_sessions ADD COLUMN {$col} {$def}");
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS wordcloud_settings (
            setting_key   TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL DEFAULT ''
        )");
    }

    private static function migrateSchemaMySQL(): void
    {
        $pdo  = self::get();
        $cols = array_column($pdo->query("DESCRIBE wordcloud_sessions")->fetchAll(), 'Field');

        if (!in_array('created_by', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN created_by INT DEFAULT NULL,
                ADD INDEX idx_created_by (created_by)");
        }
        if (!in_array('is_guest', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('guest_admin_token', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN guest_admin_token VARCHAR(64) DEFAULT NULL,
                ADD INDEX idx_guest_token (guest_admin_token)");
        }
        if (!in_array('expires_at', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN expires_at DATETIME DEFAULT NULL,
                ADD INDEX idx_expires (expires_at)");
        }
        if (!in_array('display_mode', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN display_mode ENUM('cloud','list','umfrage') NOT NULL DEFAULT 'cloud'");
        } else {
            $row = $pdo->query("SHOW COLUMNS FROM wordcloud_sessions LIKE 'display_mode'")->fetch();
            if ($row && strpos($row['Type'], 'umfrage') === false) {
                $pdo->exec("ALTER TABLE wordcloud_sessions
                    MODIFY COLUMN display_mode ENUM('cloud','list','umfrage') NOT NULL DEFAULT 'cloud'");
            }
        }
        if (!in_array('max_symbols', $cols)) {
            $pdo->exec("ALTER TABLE wordcloud_sessions
                ADD COLUMN max_symbols INT NOT NULL DEFAULT 0");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS wordcloud_settings (
            setting_key   VARCHAR(64) PRIMARY KEY,
            setting_value TEXT NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function __construct() {}
    private function __clone() {}
}
