<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDOException;

/**
 * Migrations: arquivos .sql em database/migrations, rodados em ordem, cada um uma única vez.
 * Usado pelo terminal (php bin/migrate.php) e pelo painel (Atualizar site).
 */
class Migrator
{
    private static function createTable(): void
    {
        Database::connection()->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(191) NOT NULL UNIQUE,
            ran_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * Todas as migrations, com a indicação de quais já rodaram: [nome => true/false].
     */
    public static function status(): array
    {
        self::createTable();
        $alreadyRan = array_column(Database::fetchAll('SELECT migration FROM migrations'), 'migration');
        $migrationFiles = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($migrationFiles);

        $status = [];
        foreach ($migrationFiles as $filePath) {
            $migrationName = basename($filePath, '.sql');
            $status[$migrationName] = in_array($migrationName, $alreadyRan, true);
        }

        return $status;
    }

    /**
     * Roda as pendentes, em ordem, e para na primeira que falhar.
     * Retorna ['applied' => [nomes], 'error' => null ou "nome: mensagem"].
     * Obs.: CREATE/ALTER TABLE no MySQL não voltam atrás; se uma falhar no meio, corrija o banco antes de rodar de novo.
     */
    public static function runPending(): array
    {
        $applied = [];
        foreach (self::status() as $migrationName => $alreadyRan) {
            if ($alreadyRan) {
                continue;
            }
            try {
                foreach (self::splitCommands((string) file_get_contents(BASE_PATH . "/database/migrations/{$migrationName}.sql")) as $sqlCommand) {
                    Database::connection()->exec($sqlCommand);
                }
                Database::insert('INSERT INTO migrations (migration, ran_at) VALUES (:migration, NOW())', ['migration' => $migrationName]);
                $applied[] = $migrationName;
            } catch (PDOException $exception) {
                return ['applied' => $applied, 'error' => $migrationName . ': ' . $exception->getMessage()];
            }
        }

        return ['applied' => $applied, 'error' => null];
    }

    /**
     * Um arquivo pode ter vários comandos, separados por ";" no fim da linha. Linhas "--" são comentários.
     */
    private static function splitCommands(string $sql): array
    {
        $commands = [];
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $sqlCommand) {
            $withoutComments = trim((string) preg_replace('/^\s*--.*$/m', '', $sqlCommand));
            if ($withoutComments !== '') {
                $commands[] = $withoutComments;
            }
        }

        return $commands;
    }
}
