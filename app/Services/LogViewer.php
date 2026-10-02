<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Leitura dos logs para o painel (Painel → Logs). Só lê: nada aqui apaga ou muda arquivos.
 *
 * Fontes:
 *   - storage/logs/app-AAAA-MM-DD.log: um arquivo por dia, gravado pelo ErrorHandler::log
 *     (exceções, falhas de APIs externas, passos do deploy).
 *   - error_log: arquivo que o próprio PHP do cPanel grava (erros fatais que o ErrorHandler não pega).
 */
class LogViewer
{
    /** Lê no máximo o final do arquivo (os registros mais novos) */
    private const MAX_BYTES = 1048576;
    private const MAX_LINES = 1000;

    /**
     * Arquivos disponíveis, o mais novo primeiro: [chave => ['label', 'path', 'size', 'modified']].
     */
    public static function files(): array
    {
        $files = [];
        $appLogs = glob(BASE_PATH . '/storage/logs/app-*.log') ?: [];
        rsort($appLogs);
        foreach ($appLogs as $path) {
            $date = substr(basename($path, '.log'), 4);
            $files['app-' . $date] = ['label' => date('d/m/Y', strtotime($date)), 'path' => $path];
        }

        $phpLogs = ['php-raiz' => BASE_PATH . '/error_log', 'php-public' => BASE_PATH . '/public/error_log'];
        foreach ($phpLogs as $key => $path) {
            if (is_file($path)) {
                $files[$key] = ['label' => 'PHP (' . substr($path, strlen(BASE_PATH) + 1) . ')', 'path' => $path];
            }
        }

        foreach ($files as $key => $file) {
            $files[$key] += ['size' => (int) filesize($file['path']), 'modified' => (int) filemtime($file['path'])];
        }

        return $files;
    }

    /**
     * Linhas do arquivo, a mais nova primeiro, já filtradas pelo texto buscado (sem diferença de maiúsculas).
     * Cada linha: ['text', 'kind'] (kind = erro, deploy ou info). 'truncated' = o arquivo é maior que o lido.
     */
    public static function read(string $path, string $search = ''): array
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['lines' => [], 'truncated' => false];
        }
        $truncated = $size > self::MAX_BYTES;
        if ($truncated) {
            fseek($handle, -self::MAX_BYTES, SEEK_END);
            fgets($handle); // descarta a linha cortada no meio
        }
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = [];
        foreach (array_reverse(preg_split('/\R/', $content)) as $line) {
            if (trim($line) === '' || ($search !== '' && mb_stripos($line, $search) === false)) {
                continue;
            }
            $lines[] = ['text' => mb_scrub($line, 'UTF-8'), 'kind' => self::kind($line)];
            if (count($lines) >= self::MAX_LINES) {
                $truncated = true;
                break;
            }
        }

        return ['lines' => $lines, 'truncated' => $truncated];
    }

    private static function kind(string $line): string
    {
        if (str_contains($line, '[deploy]')) {
            return str_contains($line, 'falhou') ? 'erro' : 'deploy';
        }
        if (preg_match('/SQLSTATE|Exception|Error|Fatal|Warning|Falha|falhou|erro/i', $line)) {
            return 'erro';
        }

        return 'info';
    }
}
