<?php

declare(strict_types=1);

/**
 * NOTÍCIAS E ARTIGOS
 *
 * Para publicar, adicione um item NO COMEÇO da lista (a mais nova primeiro).
 * Cada item vira a página /noticias/{id} (use só letras minúsculas, números e hífen no id).
 *
 *   body          cada texto vira um parágrafo; ['heading' => '...'] vira subtítulo;
 *                 ['ad' => true] marca onde entra o anúncio do meio do texto
 *   related_tools ids das calculadoras mostradas no fim da notícia (veja content/tools.php)
 *   image         SEMPRE uma foto de verdade (nada de ícone ou desenho), em public/assets/img/news/
 *                 com o mesmo nome do id. Use fotos livres (domínio público/CC0) e salve em JPG
 *                 com 960 px de largura. image_alt descreve a foto; image_credit dá o crédito.
 */

return [
    [
        'id' => 'decimo-terceiro-quem-tem-direito',
        'title' => '13º salário: quem tem direito e como são pagas as parcelas',
        'category' => 'Trabalho',
        'date' => '2026-09-29',
        'image' => 'decimo-terceiro-quem-tem-direito.jpg',
        'image_alt' => 'Notas de real espalhadas sobre a mesa',
        'image_credit' => 'Foto: rawpixel (domínio público)',
        'summary' => 'Entenda o cálculo proporcional, os prazos das duas parcelas e por que a segunda vem menor.',
        'related_tools' => ['decimo-terceiro', 'ferias', 'salario-liquido'],
        'body' => [
            'O 13º salário é um direito de quem trabalha com carteira assinada, e também de aposentados e pensionistas do INSS. Ele é pago em duas parcelas e equivale a um salário a mais por ano para quem trabalhou os 12 meses.',
            ['heading' => 'Como é o cálculo proporcional'],
            'Quem trabalhou menos de um ano recebe o valor proporcional: o salário dividido por 12 e multiplicado pelos meses trabalhados. Cada mês em que a pessoa trabalhou pelo menos 15 dias conta como mês inteiro.',
            ['ad' => true],
            ['heading' => 'Por que a segunda parcela vem menor'],
            'A primeira parcela corresponde à metade do valor, sem descontos. Os descontos de INSS e Imposto de Renda são feitos na segunda parcela, calculados sobre o valor total do 13º. Por isso ela costuma ser menor.',
            'Desde 2026, a redução do Imposto de Renda criada pela Lei 15.270/2025 também vale para o 13º: quem tem 13º de até R$ 5.000 não paga imposto sobre ele. Use a calculadora de 13º do Vibe2000 para ver o valor de cada parcela.',
        ],
    ],
    [
        'id' => 'selic-e-investimentos',
        'title' => 'Como a taxa Selic influencia seus investimentos',
        'category' => 'Dinheiro',
        'date' => '2026-09-26',
        'image' => 'selic-e-investimentos.jpg',
        'image_alt' => 'Notebook mostrando gráfico da bolsa de valores',
        'image_credit' => 'Foto: Negative Space / StockSnap (domínio público)',
        'summary' => 'Quando a Selic sobe ou desce, muda o rendimento da renda fixa e o custo das dívidas. Veja como.',
        'related_tools' => ['juros-compostos', 'juros-simples', 'financiamento'],
        'body' => [
            'A Selic é a taxa básica de juros da economia brasileira, definida pelo Comitê de Política Monetária do Banco Central. Ela serve de referência para quase todos os outros juros do país.',
            ['heading' => 'O que acontece com a renda fixa'],
            'Investimentos pós-fixados, como o Tesouro Selic e muitos CDBs, acompanham a taxa. Quando ela sobe, esses investimentos passam a render mais; quando cai, rendem menos.',
            ['ad' => true],
            ['heading' => 'E com as dívidas'],
            'Juros de empréstimos e financiamentos também tendem a acompanhar a Selic. Antes de contratar um financiamento, simule as parcelas e compare os sistemas de amortização Price e SAC.',
        ],
    ],
    [
        'id' => 'alcool-ou-gasolina',
        'title' => 'Álcool ou gasolina: a regra dos 70% ainda vale?',
        'category' => 'Dia a dia',
        'date' => '2026-09-22',
        'image' => 'alcool-ou-gasolina.jpg',
        'image_alt' => 'Bicos de bomba de combustível em um posto',
        'image_credit' => 'Foto: rawpixel (domínio público)',
        'summary' => 'A conta clássica para decidir no posto, e quando vale a pena fazer o teste no seu próprio carro.',
        'related_tools' => ['alcool-gasolina', 'consumo'],
        'body' => [
            'A regra mais conhecida diz que o etanol compensa quando custa até 70% do preço da gasolina. Isso acontece porque, em média, um carro flex roda cerca de 30% menos com etanol.',
            ['heading' => 'Por que o seu carro pode ser diferente'],
            'A diferença de consumo varia de modelo para modelo. Alguns carros perdem menos de 30% com etanol, e aí o limite fica acima de 70%.',
            ['ad' => true],
            'A forma mais precisa é medir: encha o tanque, anote a quilometragem e calcule o consumo com cada combustível usando a calculadora de consumo.',
        ],
    ],
    [
        'id' => 'imc-o-que-diz',
        'title' => 'IMC: o que o número diz (e o que ele não diz)',
        'category' => 'Saúde',
        'date' => '2026-09-18',
        'image' => 'imc-o-que-diz.jpg',
        'image_alt' => 'Balança de banheiro com uma fita métrica',
        'image_credit' => 'Foto: rawpixel (domínio público)',
        'summary' => 'O índice é útil como triagem, mas não mede gordura corporal. Entenda os limites.',
        'related_tools' => ['imc', 'calorias', 'agua'],
        'body' => [
            'O Índice de Massa Corporal relaciona peso e altura e é usado no mundo todo como uma forma rápida de triagem.',
            ['heading' => 'As limitações'],
            'O IMC não diferencia músculo de gordura. Uma pessoa muito musculosa pode ter IMC de sobrepeso sem ter excesso de gordura.',
            ['ad' => true],
            'Por isso ele deve ser visto junto com outras medidas, como a circunferência da cintura, e sempre com orientação de um profissional de saúde.',
        ],
    ],
    [
        'id' => 'hora-extra-como-calcular',
        'title' => 'Hora extra: como calcular o valor com adicional de 50% ou 100%',
        'category' => 'Trabalho',
        'date' => '2026-09-15',
        'image' => 'hora-extra-como-calcular.jpg',
        'image_alt' => 'Despertador sobre uma mesa',
        'image_credit' => 'Foto: Negative Space / StockSnap (domínio público)',
        'summary' => 'Descubra o valor da sua hora de trabalho e quanto recebe por cada hora a mais.',
        'related_tools' => ['hora-extra', 'somar-horas', 'salario-liquido'],
        'body' => [
            'Pela CLT, a hora extra vale pelo menos 50% a mais que a hora normal. Em domingos e feriados, muitas convenções coletivas preveem 100%.',
            ['heading' => 'Passo a passo'],
            'Primeiro divida o salário pela jornada mensal (220 horas para quem trabalha 44 horas por semana). Depois aplique o adicional e multiplique pela quantidade de horas extras.',
            ['ad' => true],
            'Confira sempre a convenção coletiva da sua categoria: ela pode prever adicionais maiores do que o mínimo da lei.',
        ],
    ],
];
