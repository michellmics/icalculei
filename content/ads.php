<?php

declare(strict_types=1);

/**
 * ESPAÇOS DE ANÚNCIO (Google AdSense)
 *
 * 1. Quando o AdSense aprovar o site, coloque o ID no .env: ADSENSE_CLIENT=ca-pub-...
 * 2. No painel do AdSense, crie um bloco de anúncio para cada espaço abaixo e
 *    cole o número dele em "slot_id".
 * Espaço sem slot_id não mostra nada. Com ADS_PLACEHOLDERS=true no .env,
 * aparece uma caixa cinza no lugar (útil para desenvolver).
 */

return [
    'top_banner' => ['size' => 'wide', 'description' => 'topo das páginas (728 × 90 / responsivo)', 'slot_id' => ''],
    'home_sidebar' => ['size' => 'square', 'description' => 'lateral da página inicial (300 × 250)', 'slot_id' => ''],
    'home_between_sections' => ['size' => 'wide', 'description' => 'entre as notícias e o diretório de calculadoras', 'slot_id' => ''],
    'tool_after_calculator' => ['size' => 'wide', 'description' => 'abaixo do resultado, separado dos campos', 'slot_id' => ''],
    'tool_bottom' => ['size' => 'wide', 'description' => 'fim da explicação da calculadora', 'slot_id' => ''],
    'tool_sidebar' => ['size' => 'square', 'description' => 'lateral da calculadora (300 × 250)', 'slot_id' => ''],
    'article_middle' => ['size' => 'wide', 'description' => 'no meio do texto da notícia', 'slot_id' => ''],
    'article_sidebar' => ['size' => 'tall', 'description' => 'lateral das notícias (300 × 600)', 'slot_id' => ''],
];
