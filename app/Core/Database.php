<?php

declare(strict_types=1);

namespace App\Core;

use DateTime;
use PDO;

/**
 * Conexão com o MySQL via PDO.
 * Todas as consultas usam prepared statements (proteção contra SQL Injection):
 * nunca coloque dados do usuário direto no texto do SQL.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $dataSourceName = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db_host'),
            config('db_port'),
            config('db_name')
        );

        self::$connection = new PDO($dataSourceName, config('db_user'), config('db_password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        // Mesmo fuso horário do PHP, para NOW() do MySQL bater com date() do PHP
        $timezoneOffset = (new DateTime())->format('P');
        self::$connection->exec("SET time_zone = '{$timezoneOffset}'");
        // Modo estrito: o MySQL avisa em vez de cortar ou alterar dados em silêncio
        self::$connection->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return self::$connection;
    }

    public static function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public static function fetchValue(string $sql, array $parameters = []): mixed
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Pares chave => valor (primeira coluna => segunda coluna), usado nos rankings.
     */
    public static function fetchPairs(string $sql, array $parameters = []): array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $sql, array $parameters = []): int
    {
        self::connection()->prepare($sql)->execute($parameters);

        return (int) self::connection()->lastInsertId();
    }

    public static function execute(string $sql, array $parameters = []): int
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }
}
