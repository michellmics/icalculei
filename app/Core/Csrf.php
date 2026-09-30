<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Proteção contra CSRF: formulários que alteram dados enviam um token secreto da sessão.
 * Um site de terceiros não conhece o token, então não consegue enviar em nome do usuário.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function isValid(mixed $sentToken): bool
    {
        Session::start();
        $sessionToken = Session::get(self::SESSION_KEY);

        return is_string($sentToken) && $sessionToken !== null && hash_equals($sessionToken, $sentToken);
    }
}
