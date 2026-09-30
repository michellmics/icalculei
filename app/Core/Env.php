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
    private static ?string $loadedFile = null;

    /**
     * Qual .env vale (o primeiro que existir), igual ao projeto direitaconservada:
     *   1. um nível acima do projeto   (site em /home/USUARIO/vibe2000 → /home/USUARIO/.env)
     *   2. dois níveis acima           (site em /home/USUARIO/public_html/vibe2000 → /home/USUARIO/.env)
     *   3. na raiz do projeto          (desenvolvimento)
     * Os dois primeiros ficam fora do alcance do navegador e não são tocados pelo "Atualizar site".
     */
    public static function candidateFiles(string $projectRoot): array
    {
        return [dirname($projectRoot) . '/.env', dirname($projectRoot, 2) . '/.env', $projectRoot . '/.env'];
    }

    public static function loadFromProject(string $projectRoot): void
    {
        foreach (self::candidateFiles($projectRoot) as $filePath) {
            if (is_readable($filePath)) {
                self::load($filePath);

                return;
            }
        }
        throw new RuntimeException('Arquivo .env não encontrado. Procurado em: ' . implode(', ', self::candidateFiles($projectRoot)));
    }

    /**
     * Caminho do .env que foi lido (o painel mostra em Atualizar site).
     */
    public static function loadedFile(): ?string
    {
        return self::$loadedFile;
    }

    public static function load(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo .env não encontrado em: ' . $filePath);
        }
        self::$loadedFile = realpath($filePath) ?: $filePath;

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
