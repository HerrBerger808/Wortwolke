<?php
/**
 * Admin-Authentifizierung – DB-basiert mit config.php-Fallback
 */
class Auth
{
    private const SESSION_KEY     = 'wc_admin';
    private const CSRF_KEY        = 'wc_csrf';
    private const TIMEOUT_SECONDS = 7200;

    public static function check(): bool
    {
        if (empty($_SESSION[self::SESSION_KEY])) return false;
        if (!empty($_SESSION['wc_login_time']) &&
            time() - $_SESSION['wc_login_time'] > self::TIMEOUT_SECONDS) {
            self::logout();
            return false;
        }
        return true;
    }

    public static function require(): void
    {
        if (!self::check()) {
            $target = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/');
            header('Location: /admin/login.php?redirect=' . $target);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::require();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Kein Zugriff.');
        }
    }

    public static function isAdmin(): bool
    {
        return !empty($_SESSION['wc_is_admin']);
    }

    public static function currentUsername(): string
    {
        return $_SESSION['wc_username'] ?? '';
    }

    public static function currentUserId(): int
    {
        return (int) ($_SESSION['wc_user_id'] ?? 0);
    }

    public static function login(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') return false;

        try {
            $pdo  = DB::get();
            $stmt = $pdo->prepare(
                "SELECT id, password_hash, is_admin FROM wordcloud_users WHERE username = :u LIMIT 1"
            );
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                self::startSession($username, (int) $user['id'], (bool) $user['is_admin']);
                return true;
            }
        } catch (\Throwable $e) {
            // Tabelle noch nicht vorhanden – Fallback unten
        }

        // Fallback auf ADMIN_HASH in config.php (nur Benutzername "admin")
        if ($username === 'admin' && defined('ADMIN_HASH') && password_verify($password, ADMIN_HASH)) {
            self::startSession('admin', 0, true);
            return true;
        }

        return false;
    }

    private static function startSession(string $username, int $userId, bool $isAdmin): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY]  = true;
        $_SESSION['wc_login_time']    = time();
        $_SESSION['wc_username']      = $username;
        $_SESSION['wc_user_id']       = $userId;
        $_SESSION['wc_is_admin']      = $isAdmin;
        $_SESSION[self::CSRF_KEY]     = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    public static function csrfInput(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(self::getCsrfToken()) . '">';
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function requireCsrf(): void
    {
        $token    = $_POST['csrf_token'] ?? '';
        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        if (!$token || !hash_equals($expected, $token)) {
            http_response_code(403);
            die('Ungültiger CSRF-Token.');
        }
    }
}
