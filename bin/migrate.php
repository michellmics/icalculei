<?php

declare(strict_types=1);

/**
 * Roda as migrations pendentes (arquivos .sql em database/migrations, em ordem).
 * Cada uma roda uma única vez; é seguro executar sempre.
 * O painel (Atualizar site) usa a mesma lógica: app/Services/Migrator.php.
 *
 * Uso:
 *   php bin/migrate.php          roda as pendentes
 *   php bin/migrate.php status   mostra quais já rodaram
 */

use App\Services\Migrator;

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado pelo terminal.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (($argv[1] ?? '') === 'status') {
    foreach (Migrator::status() as $migrationName => $alreadyRan) {
        echo ($alreadyRan ? '[x] ' : '[ ] ') . $migrationName . PHP_EOL;
    }
    exit(0);
}

$result = Migrator::runPending();
foreach ($result['applied'] as $migrationName) {
    echo 'Executada: ' . $migrationName . PHP_EOL;
}
if ($result['error'] !== null) {
    echo 'ERRO na migration ' . $result['error'] . PHP_EOL;
    exit(1);
}

echo $result['applied'] === [] ? 'Nenhuma migration pendente.' . PHP_EOL : count($result['applied']) . ' migration(s) executada(s).' . PHP_EOL;
