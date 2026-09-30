<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lê o conteúdo do site (pasta content/): calculadoras, notícias, vitrine e anúncios.
 */
class Content
{
    private static array $cache = [];

    private static function load(string $fileName): array
    {
        return self::$cache[$fileName] ??= require BASE_PATH . '/content/' . $fileName . '.php';
    }

    public static function categories(): array
    {
        return self::load('tools')['categories'];
    }

    /**
     * Todas as calculadoras, cada uma com o próprio id na chave "id".
     */
    public static function tools(): array
    {
        $tools = [];
        foreach (self::load('tools')['tools'] as $toolId => $tool) {
            $tools[$toolId] = ['id' => $toolId] + $tool;
        }

        return $tools;
    }

    public static function tool(string $toolId): ?array
    {
        return self::tools()[$toolId] ?? null;
    }

    public static function toolsByIds(array $toolIds): array
    {
        $tools = self::tools();

        return array_values(array_filter(array_map(fn (string $toolId) => $tools[$toolId] ?? null, $toolIds)));
    }

    /**
     * Notícias da mais nova para a mais antiga.
     */
    public static function news(): array
    {
        $news = self::load('news');
        usort($news, fn (array $first, array $second) => strcmp($second['date'], $first['date']));

        return $news;
    }

    public static function article(string $articleId): ?array
    {
        foreach (self::news() as $article) {
            if ($article['id'] === $articleId) {
                return $article;
            }
        }

        return null;
    }

    public static function newsByIds(array $articleIds): array
    {
        return array_values(array_filter(array_map(fn (string $articleId) => self::article($articleId), $articleIds)));
    }

    public static function showcase(): array
    {
        return self::load('showcase');
    }

    public static function adSlots(): array
    {
        return self::load('ads');
    }

    /**
     * Busca calculadoras pelo nome, descrição ou palavras-chave (sem ligar para acentos).
     */
    public static function searchTools(string $searchTerm, ?string $categoryKey): array
    {
        $normalizedTerm = normalize_text(trim($searchTerm));

        return array_values(array_filter(self::tools(), function (array $tool) use ($normalizedTerm, $categoryKey): bool {
            $matchesCategory = $categoryKey === null || $tool['category'] === $categoryKey;
            $searchableText = normalize_text($tool['name'] . ' ' . $tool['description'] . ' ' . $tool['keywords']);
            $matchesSearch = $normalizedTerm === '' || str_contains($searchableText, $normalizedTerm);

            return $matchesCategory && $matchesSearch;
        }));
    }
}
