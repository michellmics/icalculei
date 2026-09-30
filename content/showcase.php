<?php

declare(strict_types=1);

/**
 * VITRINE DA PÁGINA INICIAL
 * Ids das calculadoras (content/tools.php) e das notícias (content/news.php).
 */

return [
    // Bloco "Calculadoras mais usadas"
    'popular_tools' => ['rescisao', 'salario-liquido', 'ferias', 'decimo-terceiro', 'porcentagem', 'juros-compostos'],

    // Lateral "Ferramentas em alta"
    'trending_tools' => ['alcool-gasolina', 'hora-extra', 'dias-uteis', 'financiamento', 'moedas'],

    // Faixa "Em alta" logo abaixo do menu (misture ferramentas e notícias)
    'trending_strip' => [
        ['type' => 'news', 'id' => 'decimo-terceiro-quem-tem-direito'],
        ['type' => 'tool', 'id' => 'rescisao'],
        ['type' => 'tool', 'id' => 'salario-liquido'],
        ['type' => 'news', 'id' => 'selic-e-investimentos'],
        ['type' => 'tool', 'id' => 'dias-uteis'],
        ['type' => 'tool', 'id' => 'alcool-gasolina'],
    ],

    // Lista "Mais lidas"
    'most_read_news' => ['decimo-terceiro-quem-tem-direito', 'alcool-ou-gasolina', 'hora-extra-como-calcular', 'selic-e-investimentos', 'imc-o-que-diz'],
];
