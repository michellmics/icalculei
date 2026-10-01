<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache simples em arquivos JSON (storage/cache/{pasta}), para guardar respostas de APIs externas.
 */
class FileCache
{
    public function __construct(private string $folderName)
    {
    }

    private function folder(): string
    {
        $folder = BASE_PATH . '/storage/cache/' . $this->folderName;
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder;
    }

    private function file(string $key): string
    {
        return $this->folder() . '/' . sha1($key) . '.json';
    }

    /**
     * Valor guardado, ou null se não existe ou é mais velho que $maxAgeSeconds (null = nunca vence).
     */
    public function get(string $key, ?int $maxAgeSeconds = null): mixed
    {
        $file = $this->file($key);
        if (!is_file($file) || ($maxAgeSeconds !== null && filemtime($file) < time() - $maxAgeSeconds)) {
            return null;
        }

        return json_decode((string) file_get_contents($file), true);
    }

    public function set(string $key, mixed $value): void
    {
        file_put_contents($this->file($key), json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
