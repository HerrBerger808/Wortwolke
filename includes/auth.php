<?php
/**
 * Admin-Authentifizierung (passwortbasiert, kein LDAP)
 */
class Auth
{
    private const SESSION_KEY     = 'wc_admin';
    private const CSRF_KEY        = 'wc_csrf';
    private const TIMEOUT_SECONDS = 7200; // 2 Stunden

    /** Prüft ob Admin eingeloggt und Session noch gültig */
    public static function check(): bool
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        // Timeout prüfen
        if (!empty($_SESSION['wc_login_time']) &&
            time() - $_SESSION['wc_login_time'] > self::TIMEOUT_SECONDS) {
            self::logout();
            return false;
        }
        return true;
    }

    /** Weiterleitung zu Login falls nicht angemeldet */
    public static function require(): void
    {
        if (!self::check()) {
            $target = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/');
            header('Location: /admin/login.php?redirect=' . $target);
            exit;
        }
    }

    /** Login-Versuch. Gibt true zurück bei Erfolg. */
    public static function login(string $password): bool
    {
        if (!defined('ADMIN_HASH') || empty(ADMIN_HASH)) {
            return false;
        }
        if (!password_verify($password, ADMIN_HASH)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY]  = true;
        $_SESSION['wc_login_time']    = time();
        $_SESSION[self::CSRF_KEY]     = bin2hex(random_bytes(32));
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    /** CSRF-Token für Formulare ausgeben */
    public static function csrfInput(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    /** CSRF-Token validieren und ggf. abbrechen */
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
