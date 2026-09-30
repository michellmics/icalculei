<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Limita quantas vezes uma ação pode ser feita num intervalo
 * (ex.: 5 tentativas de login a cada 15 minutos por IP).
 */
class RateLimit
{
    /**
     * Chave sem dados pessoais: o IP vira um hash.
     */
    public static function key(string $action, string $identifier): string
    {
        return $action . ':' . hash('sha256', $identifier . '|' . config('app_key'));
    }

    public static function tooManyAttempts(string $limitKey, int $maxAttempts): bool
    {
        $attempts = Database::fetchValue(
            'SELECT attempts FROM rate_limits WHERE limit_key = :limit_key AND expires_at > NOW()',
            ['limit_key' => $limitKey]
        );

        return (int) $attempts >= $maxAttempts;
    }

    public static function hit(string $limitKey, int $windowMinutes): void
    {
        Database::execute(
            'INSERT INTO rate_limits (limit_key, attempts, expires_at)
             VALUES (:limit_key, 1, DATE_ADD(NOW(), INTERVAL :window_minutes MINUTE))
             ON DUPLICATE KEY UPDATE
                attempts = IF(expires_at > NOW(), attempts + 1, 1),
                expires_at = IF(expires_at > NOW(), expires_at, DATE_ADD(NOW(), INTERVAL :window_minutes_again MINUTE))',
            ['limit_key' => $limitKey, 'window_minutes' => $windowMinutes, 'window_minutes_again' => $windowMinutes]
        );
    }

    public static function clear(string $limitKey): void
    {
        Database::execute('DELETE FROM rate_limits WHERE limit_key = :limit_key', ['limit_key' => $limitKey]);
    }
}
