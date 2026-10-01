<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Aparelhos que ativaram as notificações do painel (tabela push_subscriptions).
 */
class PushSubscription
{
    public static function save(string $endpoint, string $publicKey, string $authSecret, string $device): void
    {
        Database::execute(
            'INSERT INTO push_subscriptions (endpoint, endpoint_hash, public_key, auth_secret, device)
             VALUES (:endpoint, :endpoint_hash, :public_key, :auth_secret, :device)
             ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), auth_secret = VALUES(auth_secret), device = VALUES(device)',
            ['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => $publicKey, 'auth_secret' => $authSecret, 'device' => $device]
        );
    }

    public static function all(): array
    {
        return Database::fetchAll('SELECT id, endpoint, public_key, auth_secret, device FROM push_subscriptions ORDER BY id');
    }

    public static function count(): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM push_subscriptions');
    }

    public static function deleteByEndpoint(string $endpoint): void
    {
        Database::execute('DELETE FROM push_subscriptions WHERE endpoint_hash = :endpoint_hash', ['endpoint_hash' => hash('sha256', $endpoint)]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM push_subscriptions WHERE id = :id', ['id' => $id]);
    }
}
