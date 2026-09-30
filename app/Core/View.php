<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderiza os arquivos de app/Views. A view vira a variável $content,
 * que o layout imprime no lugar certo.
 */
class View
{
    public static function render(string $viewName, array $data = [], ?string $layoutName = 'layouts/site'): string
    {
        $content = self::renderFile($viewName, $data);

        if ($layoutName === null) {
            return $content;
        }

        return self::renderFile($layoutName, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $partialName, array $data = []): string
    {
        return self::renderFile('partials/' . $partialName, $data);
    }

    private static function renderFile(string $viewName, array $data): string
    {
        $filePath = BASE_PATH . '/app/Views/' . $viewName . '.php';
        if (!is_file($filePath)) {
            throw new RuntimeException('View não encontrada: ' . $viewName);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $filePath;

        return (string) ob_get_clean();
    }
}
