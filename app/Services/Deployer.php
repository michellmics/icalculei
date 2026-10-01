<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\ErrorHandler;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Atualizar o site pelo painel (/painel/atualizar): baixa a branch do GitHub e aplica no servidor.
 * Não precisa de git nem de terminal no servidor (funciona no cPanel): usa a API do GitHub (ZIP) + ZipArchive.
 *
 *   .env:  DEPLOY_REPO=michellmics/icalculei   DEPLOY_BRANCH=main
 *          DEPLOY_TOKEN=github_pat_…   (repositório privado: token "fine-grained" só com Contents: Read-only)
 *
 * O que faz: baixa o ZIP do último commit → confere → copia os arquivos por cima → apaga os que saíram
 * do repositório (só os que vieram de um deploy anterior) → roda as migrations pendentes → limpa o cache do PHP.
 * Nunca mexe em: .env, .git/, storage/ (sessões, logs e o registro do próprio deploy).
 */
class Deployer
{
    private const STATE_FILE = 'storage/deploy.json'; // último deploy: commit, data e lista de arquivos
    private const LOCK_FILE = 'storage/deploy.lock';
    private const PROTECTED_PATHS = '#^(\.env|\.git/|\.github/|storage/|error_log$)#';

    public static function config(): array
    {
        return [
            'repo' => Env::get('DEPLOY_REPO', 'michellmics/icalculei'),
            'branch' => Env::get('DEPLOY_BRANCH', 'main'),
            'token' => Env::get('DEPLOY_TOKEN', ''),
        ];
    }

    /**
     * Na máquina de desenvolvimento, o deploy sobrescreveria o que ainda não foi para o git.
     */
    public static function isBlockedHere(): bool
    {
        return config('env') === 'local' && Env::get('DEPLOY_ALLOW_LOCAL', '') !== '1';
    }

    /**
     * GET na API do GitHub. Retorna [status, corpo]. Com $saveToFile, grava o corpo no arquivo (download do ZIP).
     */
    private static function request(string $url, ?string $saveToFile = null): array
    {
        $settings = self::config();
        $headers = ['User-Agent: icalculei-deploy', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($settings['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $settings['token'];
        }
        $curl = curl_init($url);
        $fileHandle = $saveToFile !== null ? fopen($saveToFile, 'wb') : null;
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true, // o ZIP redireciona para codeload.github.com
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
        ] + ($fileHandle ? [CURLOPT_FILE => $fileHandle] : [CURLOPT_RETURNTRANSFER => true]));
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($fileHandle) {
            fclose($fileHandle);
        }
        if ($body === false) {
            throw new RuntimeException('Não conectou no GitHub: ' . $error);
        }

        return [$status, $fileHandle ? '' : (string) $body];
    }

    /**
     * Último commit da branch no GitHub: ['sha', 'message', 'author', 'date'].
     */
    public static function remoteCommit(): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('O PHP do servidor precisa da extensão curl (cPanel → Select PHP Version).');
        }
        $settings = self::config();
        [$status, $body] = self::request("https://api.github.com/repos/{$settings['repo']}/commits/" . rawurlencode($settings['branch']));
        $data = json_decode($body, true);
        if ($status !== 200 || empty($data['sha'])) {
            $hint = in_array($status, [401, 403, 404], true) ? ' Repositório privado ou sem commits? Confira DEPLOY_REPO e DEPLOY_TOKEN no .env.' : '';
            throw new RuntimeException("GitHub respondeu {$status}: " . ($data['message'] ?? 'sem detalhes') . '.' . $hint);
        }

        return [
            'sha' => $data['sha'],
            'message' => strtok((string) ($data['commit']['message'] ?? ''), "\n"),
            'author' => $data['commit']['author']['name'] ?? '',
            'date' => $data['commit']['author']['date'] ?? '',
        ];
    }

    /**
     * O último deploy feito pelo painel (ou null).
     */
    public static function lastDeploy(): ?array
    {
        $stateFile = BASE_PATH . '/' . self::STATE_FILE;

        return is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
    }

    private static function deleteFolder(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($folder);
    }

    private static function log(string $message): void
    {
        ErrorHandler::log('[deploy] ' . $message);
    }

    /**
     * Faz o deploy. Retorna ['ok' => bool, 'steps' => [passo a passo], 'commit' => sha].
     * Só um por vez (trava em arquivo). Tudo vai para o log (storage/logs).
     */
    public static function run(): array
    {
        $steps = [];
        if (self::isBlockedHere()) {
            return ['ok' => false, 'steps' => ['Bloqueado: APP_ENV=local. A atualização pelo painel é para o servidor (APP_ENV=production no .env de lá).'], 'commit' => null];
        }

        @set_time_limit(600);
        ignore_user_abort(true); // fechar a aba não interrompe no meio
        $lockHandle = fopen(BASE_PATH . '/' . self::LOCK_FILE, 'c');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'steps' => ['Já tem uma atualização rodando. Espere ela terminar.'], 'commit' => null];
        }

        $tempFolder = sys_get_temp_dir() . '/icalculei-deploy-' . bin2hex(random_bytes(6));
        $zipFile = $tempFolder . '.zip';
        $commit = null;
        try {
            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('O PHP do servidor precisa da extensão zip (cPanel → Select PHP Version).');
            }
            $settings = self::config();
            $remote = self::remoteCommit();
            $commit = $remote['sha'];
            $steps[] = "Commit {$settings['branch']}: " . substr($commit, 0, 7) . " — {$remote['message']} ({$remote['author']})";
            self::log('iniciado: ' . substr($commit, 0, 7));

            // 1. Baixa e abre o ZIP
            [$status] = self::request("https://api.github.com/repos/{$settings['repo']}/zipball/{$commit}", $zipFile);
            if ($status !== 200 || filesize($zipFile) < 1000) {
                throw new RuntimeException("Não deu para baixar o código (GitHub respondeu {$status}).");
            }
            $steps[] = 'Baixado: ' . round(filesize($zipFile) / 1024) . ' KB';
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true || !$zip->extractTo($tempFolder)) {
                throw new RuntimeException('O arquivo baixado não abriu (ZIP corrompido?).');
            }
            $zip->close();
            $extractedFolders = glob($tempFolder . '/*', GLOB_ONLYDIR); // o GitHub põe tudo dentro de "dono-repo-sha/"
            $sourceFolder = $extractedFolders[0] ?? '';
            if (!is_file($sourceFolder . '/app/bootstrap.php') || !is_file($sourceFolder . '/public/index.php')) {
                throw new RuntimeException('O código baixado não parece ser deste site (faltam app/bootstrap.php e public/index.php). Nada foi alterado.');
            }

            // 2. Lista os arquivos novos (sem os protegidos)
            $newFiles = [];
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceFolder, FilesystemIterator::SKIP_DOTS));
            foreach ($items as $item) {
                $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceFolder) + 1));
                if (!preg_match(self::PROTECTED_PATHS, $relativePath)) {
                    $newFiles[] = $relativePath;
                }
            }
            sort($newFiles);

            // 3. Copia por cima (cada arquivo troca de uma vez: grava ao lado e renomeia)
            $copiedCount = 0;
            foreach ($newFiles as $relativePath) {
                $destination = BASE_PATH . '/' . $relativePath;
                $destinationFolder = dirname($destination);
                if (!is_dir($destinationFolder) && !mkdir($destinationFolder, 0755, true) && !is_dir($destinationFolder)) {
                    throw new RuntimeException("Não deu para criar a pasta de {$relativePath} (permissão?).");
                }
                if (is_file($destination) && md5_file($destination) === md5_file($sourceFolder . '/' . $relativePath)) {
                    continue; // igual: não mexe
                }
                if (!copy($sourceFolder . '/' . $relativePath, $destination . '.deploy-new') || !rename($destination . '.deploy-new', $destination)) {
                    @unlink($destination . '.deploy-new');
                    throw new RuntimeException("Não deu para gravar {$relativePath} (permissão?). Parte dos arquivos já foi atualizada: rode de novo.");
                }
                $copiedCount++;
            }
            $steps[] = 'Arquivos: ' . count($newFiles) . " no repositório, {$copiedCount} atualizados";

            // 4. Apaga o que saiu do repositório (só o que veio de um deploy anterior; nunca os protegidos)
            $previous = self::lastDeploy();
            $deletedCount = 0;
            foreach (array_diff($previous['files'] ?? [], $newFiles) as $relativePath) {
                if (!preg_match(self::PROTECTED_PATHS, $relativePath) && !str_contains($relativePath, '..') && is_file(BASE_PATH . '/' . $relativePath)) {
                    @unlink(BASE_PATH . '/' . $relativePath) && $deletedCount++;
                }
            }
            if ($deletedCount > 0) {
                $steps[] = "Removidos {$deletedCount} arquivos que saíram do repositório";
            }
            file_put_contents(BASE_PATH . '/' . self::STATE_FILE, json_encode([
                'commit' => $commit,
                'message' => $remote['message'],
                'author' => $remote['author'],
                'date' => date('c'),
                'files' => $newFiles,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            // 5. Migrations pendentes
            $migrations = Migrator::runPending();
            $steps[] = $migrations['applied'] !== [] ? 'Migrations aplicadas: ' . implode(', ', $migrations['applied']) : 'Migrations: nada pendente';
            if ($migrations['error'] !== null) {
                throw new RuntimeException('Migration com erro: ' . $migrations['error']);
            }

            // 6. Limpa o cache do PHP para ele ler os arquivos novos
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $steps[] = '✓ Site atualizado.';
            self::log('concluído: ' . substr($commit, 0, 7) . " ({$copiedCount} arquivos, {$deletedCount} removidos)");

            return ['ok' => true, 'steps' => $steps, 'commit' => $commit];
        } catch (Throwable $exception) {
            $steps[] = '✗ ' . $exception->getMessage();
            self::log('falhou: ' . $exception->getMessage());

            return ['ok' => false, 'steps' => $steps, 'commit' => $commit];
        } finally {
            @unlink($zipFile);
            self::deleteFolder($tempFolder);
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
