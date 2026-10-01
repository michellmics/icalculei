<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dados estruturados (Schema.org em JSON-LD) que ajudam o Google a entender cada página.
 * O layout do site imprime cada item como <script type="application/ld+json">.
 * Teste em: https://search.google.com/test/rich-results
 */
class StructuredData
{
    // Tipo de aplicativo (Schema.org) de cada categoria de calculadora
    private const APPLICATION_CATEGORIES = [
        'financas' => 'FinanceApplication',
        'trabalho' => 'BusinessApplication',
        'saude' => 'HealthApplication',
        'dev' => 'DeveloperApplication',
    ];

    private static function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Vibe2000',
            'url' => url('/'),
            'logo' => url('/icons/site-512.png'),
        ];
    }

    private static function breadcrumb(array $items): array
    {
        $position = 0;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(function (array $item) use (&$position) {
                return ['@type' => 'ListItem', 'position' => ++$position, 'name' => $item[0], 'item' => url($item[1])];
            }, $items),
        ];
    }

    /**
     * Página inicial: o site, a busca interna (caixa de busca no Google) e quem publica.
     */
    public static function home(): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'Vibe2000',
                'url' => url('/'),
                'inLanguage' => 'pt-BR',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/') . '?busca={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            ['@context' => 'https://schema.org'] + self::organization(),
        ];
    }

    /**
     * Calculadora: é uma ferramenta web (WebApplication), fica numa categoria (BreadcrumbList)
     * e tem perguntas frequentes (FAQPage, lidas dos <details> da explicação).
     */
    public static function tool(array $tool, string $categoryName): array
    {
        $items = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => $tool['seo_title'] ?? $tool['name'],
                'alternateName' => $tool['name'],
                'url' => url('/calculadoras/' . $tool['id']),
                'description' => $tool['lead'],
                'applicationCategory' => self::APPLICATION_CATEGORIES[$tool['category']] ?? 'UtilitiesApplication',
                'operatingSystem' => 'Qualquer (navegador)',
                'browserRequirements' => 'Requer JavaScript',
                'inLanguage' => 'pt-BR',
                'isAccessibleForFree' => true,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'BRL'],
                'publisher' => self::organization(),
            ],
            self::breadcrumb([
                ['Início', '/'],
                [$categoryName, '/?categoria=' . $tool['category']],
                [$tool['name'], '/calculadoras/' . $tool['id']],
            ]),
        ];
        if ($tool['reviewed'] !== '') {
            $items[0]['dateModified'] = $tool['reviewed'];
        }

        $questions = self::questionsFromHtml($tool['explainer'] ?? '');
        if ($questions !== []) {
            $items[] = self::faq($questions);
        }

        return $items;
    }

    /**
     * Feriados de um mês (/feriados/2026/novembro): trilha de navegação e perguntas frequentes.
     * $questions: lista de [pergunta, resposta] em texto simples.
     */
    public static function holidayMonth(int $year, string $monthName, string $path, array $questions): array
    {
        return [
            self::breadcrumb([
                ['Início', '/'],
                ['Feriados ' . $year, '/feriados/' . $year],
                ['Feriados de ' . $monthName . ' de ' . $year, $path],
            ]),
            self::faq($questions),
        ];
    }

    private static function faq(array $questions): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $pair) => [
                '@type' => 'Question',
                'name' => $pair[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $pair[1]],
            ], $questions),
        ];
    }

    /**
     * Notícia: artigo com título, foto, datas e quem publica.
     */
    public static function article(array $article, string $imageUrl): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article['title'],
                'description' => $article['summary'],
                'image' => [$imageUrl],
                'datePublished' => $article['date'],
                'dateModified' => $article['date'],
                'inLanguage' => 'pt-BR',
                'author' => ['@type' => 'Organization', 'name' => 'Redação Vibe2000', 'url' => url('/sobre')],
                'publisher' => self::organization(),
                'mainEntityOfPage' => url('/noticias/' . $article['id']),
            ],
            self::breadcrumb([
                ['Início', '/'],
                ['Notícias', '/noticias'],
                [$article['title'], '/noticias/' . $article['id']],
            ]),
        ];
    }

    /**
     * Perguntas e respostas escritas como <details><summary>pergunta</summary><p>resposta</p></details>.
     */
    private static function questionsFromHtml(string $html): array
    {
        preg_match_all('#<details>\s*<summary>(.*?)</summary>\s*<p>(.*?)</p>\s*</details>#s', $html, $matches, PREG_SET_ORDER);
        $clean = fn (string $text) => trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return array_map(fn (array $match) => [$clean($match[1]), $clean($match[2])], $matches);
    }
}
