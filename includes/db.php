<?php
/**
 * Datenbank-Singleton (PDO)
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
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

    /** Tabellen anlegen (idempotent, beim ersten Aufruf) */
    public static function createSchema(): void
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

    /** Verbindungstest (für install.php / Setup) */
    public static function testConnection(
        string $host, int $port, string $name,
        string $user, string $pass
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Datenbank anlegen falls nicht vorhanden
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function __construct() {}
    private function __clone() {}
}
