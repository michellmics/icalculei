<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

/**
 * Links curtos do encurtador de URL (tabela short_links).
 */
class ShortLink
{
    private const CODE_LENGTH = 6;
    private const CODE_CHARACTERS = 'abcdefghijkmnpqrstuvwxyz23456789'; // sem l, o, 0 e 1 (parecidos)

    /**
     * Devolve o código do link: o mesmo endereço longo sempre recebe o mesmo código.
     */
    public static function codeFor(string $url): string
    {
        $urlHash = hash('sha256', $url);
        $existingCode = Database::fetchValue('SELECT code FROM short_links WHERE url_hash = :url_hash', ['url_hash' => $urlHash]);
        if ($existingCode !== null) {
            return (string) $existingCode;
        }

        // Código aleatório; se por azar já existir, tenta outro
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = self::randomCode();
            try {
                Database::execute(
                    'INSERT INTO short_links (code, url, url_hash) VALUES (:code, :url, :url_hash)',
                    ['code' => $code, 'url' => $url, 'url_hash' => $urlHash]
                );
                return $code;
            } catch (PDOException $exception) {
                // Outro visitante encurtou o mesmo endereço ao mesmo tempo
                $existingCode = Database::fetchValue('SELECT code FROM short_links WHERE url_hash = :url_hash', ['url_hash' => $urlHash]);
                if ($existingCode !== null) {
                    return (string) $existingCode;
                }
            }
        }

        throw new \RuntimeException('Não foi possível gerar um código livre.');
    }

    public static function find(string $code): ?array
    {
        return Database::fetchOne('SELECT id, url, clicks FROM short_links WHERE code = :code', ['code' => $code]);
    }

    public static function countClick(int $id): void
    {
        Database::execute('UPDATE short_links SET clicks = clicks + 1 WHERE id = :id', ['id' => $id]);
    }

    private static function randomCode(): string
    {
        $code = '';
        $lastIndex = strlen(self::CODE_CHARACTERS) - 1;
        for ($position = 0; $position < self::CODE_LENGTH; $position++) {
            $code .= self::CODE_CHARACTERS[random_int(0, $lastIndex)];
        }

        return $code;
    }
}
