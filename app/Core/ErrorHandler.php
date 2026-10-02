<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Erros inesperados: grava em storage/logs e mostra uma página genérica.
 * Com APP_DEBUG=true (só em desenvolvimento) mostra os detalhes.
 */
class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        // Avisos do PHP viram exceções, para não passarem despercebidos
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    /** Logs mais velhos que isso são apagados (a limpeza roda uma vez por dia, ao criar o arquivo do dia) */
    public const LOG_RETENTION_DAYS = 90;
    /** Teto de cada arquivo diário: um erro em loop não lota o disco */
    public const LOG_MAX_BYTES_PER_DAY = 10485760;

    public static function log(string $message): void
    {
        $logFile = BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
        clearstatcache(true, $logFile);
        $currentSize = is_file($logFile) ? (int) filesize($logFile) : null;
        if ($currentSize === null) {
            self::deleteOldLogs();
        } elseif ($currentSize >= self::LOG_MAX_BYTES_PER_DAY) {
            return;
        }
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function deleteOldLogs(): void
    {
        $oldestKept = date('Y-m-d', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'));
        foreach (glob(BASE_PATH . '/storage/logs/app-*.log') ?: [] as $path) {
            if (substr(basename($path, '.log'), 4) < $oldestKept) {
                @unlink($path);
            }
        }
    }

    public static function handleException(Throwable $exception): void
    {
        self::log($exception->getMessage() . ' em ' . $exception->getFile() . ':' . $exception->getLine());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'ERRO: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (config('debug')) {
            echo '<pre>' . e($exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) . '</pre>';
            return;
        }

        echo '<!doctype html><meta charset="utf-8"><title>Erro</title><p style="font-family:sans-serif;padding:2rem">Ocorreu um erro inesperado. Tente novamente em alguns minutos.</p>';
    }
}
