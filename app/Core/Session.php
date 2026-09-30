<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sessão com configurações seguras. Só é aberta nas páginas que precisam
 * (contato e painel), para o resto do site não gravar cookie de sessão.
 */
class Session
{
    private const FLASH_KEY = '_flash';
    private const INACTIVITY_LIMIT_SECONDS = 7200;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_save_path(BASE_PATH . '/storage/sessions');
        session_name(config('session_name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Encerra a sessão depois de muito tempo sem uso
        $lastActivity = $_SESSION['_last_activity'] ?? null;
        if ($lastActivity !== null && time() - $lastActivity > self::INACTIVITY_LIMIT_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Novo id de sessão (no login e logout), contra o ataque de fixação de sessão.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Guarda uma mensagem para ser mostrada só na próxima página.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Lê e apaga a mensagem guardada pela página anterior.
     */
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);

        return $value;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
