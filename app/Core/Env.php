<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lê o arquivo .env (NOME=valor, uma variável por linha).
 * Linhas começando com # são comentários. Valores podem estar entre aspas.
 */
class Env
{
    private static array $values = [];

    public static function load(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo .env não encontrado em: ' . $filePath);
        }

        foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $rawValue] = explode('=', $line, 2);
            self::$values[trim($name)] = self::removeQuotes(trim($rawValue));
        }
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = self::$values[$name] ?? '';

        return $value === '' ? $default : $value;
    }

    public static function getBool(string $name, bool $default = false): bool
    {
        $value = self::get($name);

        return $value === null ? $default : in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    public static function getInt(string $name, int $default = 0): int
    {
        $value = self::get($name);

        return $value === null ? $default : (int) $value;
    }

    /**
     * SENHA="abc" ou SENHA='abc' viram apenas abc.
     */
    private static function removeQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }
        $firstCharacter = $value[0];
        $isQuoted = ($firstCharacter === '"' || $firstCharacter === "'") && $firstCharacter === $value[strlen($value) - 1];

        return $isQuoted ? substr($value, 1, -1) : $value;
    }
}
