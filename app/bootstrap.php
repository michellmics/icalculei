<?php

declare(strict_types=1);

/**
 * Prepara a aplicação: carrega as classes, lê o .env e configura
 * fuso horário e tratamento de erros. Usado pelo site e pelos scripts em bin/.
 */

define('BASE_PATH', dirname(__DIR__));

// Carrega as classes automaticamente: App\Core\Env fica em app/Core/Env.php
spl_autoload_register(function (string $className): void {
    $prefix = 'App\\';
    if (!str_starts_with($className, $prefix)) {
        return;
    }
    $relativePath = str_replace('\\', '/', substr($className, strlen($prefix)));
    $filePath = BASE_PATH . '/app/' . $relativePath . '.php';
    if (is_file($filePath)) {
        require $filePath;
    }
});

require BASE_PATH . '/app/helpers.php';

// .env: um ou dois níveis acima do projeto (produção, fora do alcance do navegador) ou na raiz (desenvolvimento)
App\Core\Env::loadFromProject(BASE_PATH);

date_default_timezone_set(config('timezone'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
