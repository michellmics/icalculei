<?php

declare(strict_types=1);

/**
 * CALCULADORAS DO VIBE2000
 *
 * Cada item vira uma página em /calculadoras/{id}.
 *   seo_title título para o Google: a busca exata no começo (ex.: "Calculadora de IMC: Índice de Massa Corporal")
 *   ready     true = funcionando | false = aparece como "em breve"
 *   reviewed  data da última revisão (aparece na página da calculadora)
 *   lead      frase de abertura da página
 *   explainer texto explicativo (fórmula, exemplo, perguntas frequentes)
 *             Perguntas em <details><summary>pergunta</summary><p>resposta</p></details>:
 *             viram também dados estruturados FAQPage para o Google.
 *
 * A conta de cada calculadora fica em public/assets/js/calculators.js, com o mesmo id.
 * Ao revisar uma calculadora, atualize o "reviewed".
 */

return [
    'categories' => [
        'financas' => 'Finanças',
        'trabalho' => 'Trabalho',
        'matematica' => 'Matemática',
        'saude' => 'Saúde',
        'conversores' => 'Conversores',
        'datas' => 'Datas e tempo',
        'veiculos' => 'Veículos',
        'texto' => 'Texto',
        'dev' => 'TI',
    ],

    'tools' => [
        'juros-compostos' => [
            'name' => 'Juros compostos',
            'seo_title' => 'Calculadora de Juros Compostos com Aportes Mensais',
            'symbol' => 'j%',
            'category' => 'financas',
            'description' => 'Quanto seu dinheiro rende com aportes mensais.',
            'keywords' => 'investimento rendimento poupança',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Simule quanto um investimento rende com juros sobre juros e aportes todo mês.',
            'explainer' => <<<'HTML'
<h2>O que são juros compostos</h2>
<p>Cada mês rende sobre o valor do mês anterior, que já inclui os juros. É o juros sobre juros.</p>
<p class="formula">M = C × (1 + i)ⁿ + A × [(1 + i)ⁿ − 1] ÷ i</p>
<p class="notice">A simulação é bruta: não desconta imposto de renda nem taxas.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual a diferença entre juros simples e compostos?</summary><p>Nos juros simples, a taxa incide só sobre o valor inicial. Nos compostos, incide também sobre os juros já acumulados (&quot;juros sobre juros&quot;), por isso o valor cresce mais rápido com o tempo.</p></details>
  <details><summary>Os aportes mensais fazem muita diferença?</summary><p>Sim. Aportes regulares aumentam a base que rende juros todo mês. Em prazos longos, eles costumam pesar mais no resultado final do que o valor inicial.</p></details>
</div>
HTML,
        ],
        'juros-simples' => [
            'name' => 'Juros simples',
            'seo_title' => 'Calculadora de Juros Simples: Montante e Juros',
            'symbol' => 'j',
            'category' => 'financas',
            'description' => 'Juros e montante com taxa fixa sobre o capital.',
            'keywords' => 'empréstimo taxa',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule juros simples e o valor final com taxa fixa sobre o capital.',
            'explainer' => <<<'HTML'
<h2>Juros simples</h2>
<p>Nos juros simples a taxa incide sempre sobre o capital inicial, então os juros são iguais todo mês.</p>
<p class="formula">J = C × i × t · M = C + J</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Onde os juros simples são usados?</summary><p>Em algumas operações de curto prazo, multas e juros de mora por atraso. Investimentos e financiamentos, na maioria, usam juros compostos.</p></details>
  <details><summary>Como calcular juros simples?</summary><p>Multiplique o capital pela taxa e pelo número de períodos. A taxa e o prazo precisam estar na mesma unidade (ao mês com meses, ao ano com anos).</p></details>
</div>
HTML,
        ],
        'financiamento' => [
            'name' => 'Financiamento (Price e SAC)',
            'seo_title' => 'Simulador de Financiamento: Tabela Price e SAC',
            'symbol' => 'R$',
            'category' => 'financas',
            'description' => 'Parcelas, juros e total pago nos dois sistemas.',
            'keywords' => 'parcela empréstimo carro casa amortização',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Compare as parcelas de um financiamento pela tabela Price (parcelas iguais) e pelo SAC (parcelas decrescentes).',
            'explainer' => <<<'HTML'
<h2>Price ou SAC?</h2>
<p>Na <b>Price</b> as parcelas são iguais do começo ao fim. No <b>SAC</b> a amortização é fixa e as parcelas começam maiores e vão diminuindo. No total, o SAC costuma cobrar menos juros.</p>
<p class="formula">Price: P = V × i ÷ [1 − (1 + i)⁻ⁿ]</p>
<p class="notice">A simulação não inclui seguros, tarifas e IOF, que aumentam o custo efetivo total (CET).</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual sistema paga menos juros no total?</summary><p>O SAC, porque amortiza mais do saldo logo no começo. Em troca, as primeiras parcelas são maiores que as da tabela Price.</p></details>
  <details><summary>O que é amortização?</summary><p>É a parte da parcela que abate a dívida. O restante da parcela são os juros sobre o saldo que ainda falta pagar.</p></details>
</div>
HTML,
        ],
        'financiamento-veiculo' => [
            'name' => 'Financiamento de veículo',
            'seo_title' => 'Simulador de Financiamento de Veículos com IOF e CET',
            'symbol' => '🚗',
            'category' => 'financas',
            'description' => 'Parcela do carro ou moto com IOF, tarifas e CET.',
            'keywords' => 'financiamento carro moto veículo parcela entrada cet iof',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Simule o financiamento do seu carro ou moto: valor da parcela, IOF, custo efetivo total (CET) e quanto o veículo sai no fim.',
            'explainer' => <<<'HTML'
<h2>Como é calculada a parcela do financiamento</h2>
<p>O financiamento de veículos costuma usar a <b>tabela Price</b>: todas as parcelas têm o mesmo valor. Sobre o valor financiado (preço menos a entrada, mais tarifas e IOF) incidem os juros mensais combinados com o banco.</p>
<p class="formula">parcela = valor financiado × i ÷ [1 − (1 + i)⁻ⁿ]</p>
<h2>IOF no financiamento</h2>
<p>Para pessoa física, o IOF é de 0,38% sobre o valor financiado mais 0,0082% ao dia sobre cada parcela, contando no máximo 365 dias. Em financiamentos longos, isso dá perto de 3% do valor. Normalmente o IOF é incluído no financiamento, e você paga juros sobre ele também.</p>
<h2>O que é o CET</h2>
<p>O custo efetivo total (CET) junta juros, IOF, tarifas e seguros em uma taxa só. É o número certo para comparar propostas: um banco com juros menores pode ter um CET maior por causa das tarifas. O banco é obrigado a informar o CET antes da assinatura do contrato.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Vale a pena dar uma entrada maior?</summary><p>Quase sempre. Quanto maior a entrada, menor o valor financiado e menos juros e IOF você paga. Compare na tabela de prazos quanto o carro sai no total.</p></details>
  <details><summary>Prazo maior é melhor?</summary><p>O prazo maior diminui a parcela, mas aumenta muito o custo total, porque você paga juros por mais tempo. Escolha a menor parcela que caiba com folga no orçamento.</p></details>
  <details><summary>Posso quitar antes e ter desconto?</summary><p>Sim. Pelo Código de Defesa do Consumidor, quem antecipa parcelas tem direito à redução proporcional dos juros.</p></details>
</div>
HTML,
        ],
        'moedas' => [
            'name' => 'Conversor de moedas',
            'seo_title' => 'Conversor de Moedas: Dólar e Euro Hoje em Reais',
            'symbol' => 'US$',
            'category' => 'financas',
            'description' => 'Dólar, euro, libra e peso argentino.',
            'keywords' => 'dólar euro câmbio cotação',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Converta valores entre real, dólar, euro, libra e peso argentino com a cotação comercial do dia.',
            'explainer' => <<<'HTML'
<h2>Cotação comercial e cotação de turismo</h2>
<p>A cotação comercial é usada entre bancos e empresas. A de turismo, usada para comprar moeda em espécie, costuma ser mais alta. Cartões de crédito internacionais também cobram IOF.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual cotação a calculadora usa?</summary><p>A cotação comercial do dia, de APIs gratuitas. Casas de câmbio e cartões de crédito cobram mais que isso: ajuste o valor em &quot;Cotações usadas&quot; se quiser.</p></details>
  <details><summary>Por que o dólar turismo é mais caro?</summary><p>Porque inclui os custos de comprar e transportar dinheiro em espécie, além da margem da casa de câmbio.</p></details>
</div>
HTML,
        ],
        'decimo-terceiro' => [
            'name' => '13º salário',
            'seo_title' => 'Calculadora de 13º Salário Líquido 2026',
            'symbol' => '13º',
            'category' => 'trabalho',
            'description' => 'Parcelas brutas e líquidas com descontos de 2026.',
            'keywords' => 'décimo terceiro',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule o 13º salário bruto e líquido, com o valor de cada parcela e os descontos de 2026.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o 13º</h2>
<p>O 13º é o salário dividido por 12 e multiplicado pelos meses trabalhados. A primeira parcela é metade do valor, sem descontos. Na segunda são descontados o INSS e o Imposto de Renda, calculados sobre o 13º inteiro e separados do salário do mês.</p>
<p class="formula">13º = salário ÷ 12 × meses trabalhados</p>
<p>A redução do IR criada pela Lei 15.270/2025 também vale para o 13º: quem tem 13º de até R$ 5.000 não paga imposto sobre ele.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quando o 13º é pago?</summary><p>A primeira parcela até 30 de novembro e a segunda até 20 de dezembro. A empresa pode pagar tudo de uma vez, até 30 de novembro.</p></details>
  <details><summary>Quem trabalhou só alguns meses recebe?</summary><p>Sim, proporcional: 1/12 do salário por mês trabalhado. Conta como mês inteiro o mês em que trabalhou pelo menos 15 dias.</p></details>
</div>
HTML,
        ],
        'ferias' => [
            'name' => 'Férias',
            'seo_title' => 'Calculadora de Férias 2026 com 1/3 e Abono',
            'symbol' => '⛱',
            'category' => 'trabalho',
            'description' => 'Férias com 1/3, abono e descontos de 2026.',
            'keywords' => 'férias um terço abono',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule as férias com o terço constitucional, a venda de 10 dias e os descontos de 2026.',
            'explainer' => <<<'HTML'
<h2>Como são calculadas as férias</h2>
<p>O valor das férias é o salário proporcional aos dias, mais um terço. Sobre férias e 1/3 incidem INSS e Imposto de Renda. Quem vende 10 dias (abono pecuniário) recebe esses dias com 1/3, sem desconto de INSS e IR.</p>
<p class="formula">férias = salário ÷ 30 × dias · 1/3 = férias ÷ 3</p>
<p>O pagamento deve ser feito até 2 dias antes do início das férias.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Posso vender as férias?</summary><p>Você pode vender até 1/3 das férias (10 dias, no caso de 30), o chamado abono pecuniário. Esses dias são pagos com 1/3 e sem desconto de INSS e IR.</p></details>
  <details><summary>As férias podem ser divididas?</summary><p>Sim, em até 3 períodos, se o empregado concordar: um deles com pelo menos 14 dias e os outros com pelo menos 5 dias cada.</p></details>
</div>
HTML,
        ],
        'hora-extra' => [
            'name' => 'Hora extra',
            'seo_title' => 'Calculadora de Hora Extra 50% e 100%',
            'symbol' => '+h',
            'category' => 'trabalho',
            'description' => 'Valor da hora normal e da hora extra.',
            'keywords' => 'adicional 50% 100% jornada',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra o valor da sua hora de trabalho e quanto recebe pelas horas extras.',
            'explainer' => <<<'HTML'
<h2>Como calcular hora extra</h2>
<p>Divida o salário pela jornada mensal para achar o valor da hora. A hora extra vale no mínimo 50% a mais; convenções coletivas podem prever percentuais maiores.</p>
<p class="formula">hora extra = (salário ÷ jornada) × (1 + adicional)</p>
<p class="notice">O reflexo das horas extras no descanso semanal remunerado (DSR) não está incluído.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual o adicional da hora extra?</summary><p>No mínimo 50% sobre a hora normal. Em domingos e feriados trabalhados sem folga compensatória, costuma ser 100%. A convenção coletiva pode prever percentuais maiores.</p></details>
  <details><summary>Quantas horas extras posso fazer por dia?</summary><p>Pela CLT, até 2 horas extras por dia, salvo exceções previstas em lei ou em acordo.</p></details>
</div>
HTML,
        ],
        'rescisao' => [
            'name' => 'Rescisão trabalhista',
            'seo_title' => 'Calculadora de Rescisão Trabalhista 2026 (CLT)',
            'symbol' => 'CLT',
            'category' => 'trabalho',
            'description' => 'Verbas e valor líquido ao sair da empresa.',
            'keywords' => 'demissão acerto aviso prévio fgts multa',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Estime as verbas rescisórias e o valor líquido a receber ao sair da empresa, com as regras da CLT e as tabelas de 2026.',
            'explainer' => <<<'HTML'
<h2>O que entra na rescisão</h2>
<p><b>Demissão sem justa causa:</b> saldo de salário, aviso prévio, 13º e férias proporcionais com 1/3, férias vencidas com 1/3, multa de 40% do FGTS e saque do FGTS.</p>
<p><b>Pedido de demissão:</b> saldo de salário, 13º e férias proporcionais com 1/3 e férias vencidas. Não há multa nem saque do FGTS. Se o aviso não for cumprido, a empresa pode descontar 30 dias.</p>
<p><b>Acordo (art. 484-A da CLT):</b> metade do aviso prévio indenizado, multa de 20% do FGTS e saque de até 80% do saldo, sem seguro-desemprego.</p>
<p><b>Justa causa:</b> apenas saldo de salário e férias vencidas com 1/3.</p>
<h2>Aviso prévio proporcional</h2>
<p>Pela Lei 12.506/2011, o aviso prévio é de 30 dias, mais 3 dias por ano completo de trabalho, até 90 dias. O período do aviso indenizado conta como tempo de serviço para o 13º e as férias.</p>
<p class="formula">aviso = 30 + 3 × anos completos (máximo 90 dias)</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual o prazo para pagar a rescisão?</summary><p>A empresa tem até 10 dias corridos depois do fim do contrato para pagar as verbas rescisórias.</p></details>
  <details><summary>Tem desconto de IR na rescisão?</summary><p>Aviso prévio indenizado, férias indenizadas com 1/3 e a multa do FGTS não têm Imposto de Renda. O saldo de salário e o 13º são tributados normalmente.</p></details>
</div>
HTML,
        ],
        'seguro-desemprego' => [
            'name' => 'Seguro-desemprego',
            'seo_title' => 'Calculadora de Seguro-Desemprego 2026: Valor e Parcelas',
            'symbol' => 'SD',
            'category' => 'trabalho',
            'description' => 'Valor e número de parcelas, tabela 2026.',
            'keywords' => 'seguro desemprego parcelas demissão benefício',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quanto vai receber de seguro-desemprego e em quantas parcelas, com a tabela de 2026.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o seguro-desemprego em 2026</h2>
<p>O valor da parcela depende da média dos salários dos 3 meses anteriores à demissão:</p>
<p><b>Média até R$ 2.222,17:</b> a parcela é 80% da média.</p>
<p><b>Média de R$ 2.222,18 a R$ 3.703,99:</b> R$ 1.777,74 mais 50% do que passar de R$ 2.222,17.</p>
<p><b>Média acima de R$ 3.703,99:</b> valor fixo de R$ 2.518,65, o teto.</p>
<p>Nenhuma parcela é menor que o salário mínimo (R$ 1.621,00).</p>
<p class="formula">parcela = 1.777,74 + (média − 2.222,17) × 0,5   (faixa do meio)</p>
<h2>Quantas parcelas</h2>
<p>Depende de quantas vezes você já pediu o benefício e dos meses trabalhados com carteira nos últimos 36 meses:</p>
<p><b>1ª solicitação:</b> 12 a 23 meses dão 4 parcelas; 24 meses ou mais, 5 parcelas.</p>
<p><b>2ª solicitação:</b> 9 a 11 meses dão 3 parcelas; 12 a 23 meses, 4; 24 ou mais, 5.</p>
<p><b>3ª em diante:</b> 6 a 11 meses dão 3 parcelas; 12 a 23 meses, 4; 24 ou mais, 5.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quem tem direito?</summary><p>Quem foi demitido sem justa causa (inclusive na rescisão indireta), não tem outra renda própria suficiente e não recebe outro benefício contínuo da Previdência, com exceção de pensão por morte e auxílio-acidente. Quem pede demissão, faz acordo (art. 484-A da CLT) ou é demitido por justa causa não tem direito.</p></details>
  <details><summary>Qual o prazo para pedir?</summary><p>Do 7º ao 120º dia depois da demissão, pelo aplicativo ou site Carteira de Trabalho Digital, pelo gov.br ou em uma unidade do Sine.</p></details>
</div>
HTML,
        ],
        'salario-liquido' => [
            'name' => 'Salário líquido',
            'seo_title' => 'Calculadora de Salário Líquido 2026 (INSS e IR)',
            'symbol' => '−%',
            'category' => 'trabalho',
            'description' => 'Salário depois de INSS e IR, tabelas 2026.',
            'keywords' => 'inss irrf desconto imposto de renda 5 mil',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quanto cai na sua conta depois dos descontos de INSS e Imposto de Renda, com as tabelas de 2026.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o salário líquido em 2026</h2>
<p><b>1. INSS:</b> é progressivo. Cada faixa do salário paga a sua alíquota: 7,5% até R$ 1.621,00; 9% até R$ 2.902,84; 12% até R$ 4.354,27; e 14% até o teto de R$ 8.475,55.</p>
<p><b>2. Base do IR:</b> salário bruto menos o INSS e R$ 189,59 por dependente, ou menos o desconto simplificado de R$ 607,20, o que for mais vantajoso.</p>
<p><b>3. IR:</b> aplica-se a tabela progressiva (isento até R$ 2.428,80; depois 7,5%, 15%, 22,5% e 27,5%).</p>
<p><b>4. Redução de 2026:</b> pela Lei 15.270/2025, quem recebe até R$ 5.000 por mês tem o imposto zerado (redução de até R$ 312,89). Entre R$ 5.000,01 e R$ 7.350, a redução diminui aos poucos:</p>
<p class="formula">redução = R$ 978,62 − 0,133145 × salário bruto</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quem ganha R$ 5.000 não paga nada de IR?</summary><p>Isso. Com a redução criada em 2026, o imposto de quem tem rendimentos tributáveis de até R$ 5.000 por mês é zerado. O INSS continua sendo descontado normalmente.</p></details>
  <details><summary>O que é o desconto simplificado?</summary><p>É uma dedução fixa de R$ 607,20 que substitui as deduções legais (INSS, dependentes, pensão). A fonte pagadora usa a opção que resultar em menos imposto.</p></details>
</div>
HTML,
        ],
        'porcentagem' => [
            'name' => 'Porcentagem',
            'seo_title' => 'Calculadora de Porcentagem: Aumento, Desconto e Variação',
            'symbol' => '%',
            'category' => 'matematica',
            'description' => 'X% de um valor, aumentos, descontos e variação.',
            'keywords' => 'porcento desconto aumento',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule porcentagens de quatro jeitos diferentes. O resultado aparece enquanto você digita.',
            'explainer' => <<<'HTML'
<h2>Como calcular porcentagem</h2>
<p>Porcentagem é uma fração de 100. Para saber quanto é X% de um valor, divida X por 100 e multiplique pelo valor.</p>
<p class="formula">X% de Y = (X ÷ 100) × Y</p>
<p><b>Exemplo:</b> 15% de 250 = 0,15 × 250 = 37,5.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Desconto de 10% e depois mais 10% dá 20%?</summary><p>Não. O segundo desconto é sobre o valor já reduzido: 100 vira 90 e depois 81, ou seja, 19% no total.</p></details>
  <details><summary>Como calcular a variação percentual?</summary><p>Divida a diferença entre os valores pelo valor inicial e multiplique por 100.</p></details>
</div>
HTML,
        ],
        'regra-de-tres' => [
            'name' => 'Regra de três',
            'seo_title' => 'Calculadora de Regra de Três Simples e Inversa',
            'symbol' => 'a:b',
            'category' => 'matematica',
            'description' => 'O valor que falta em uma proporção.',
            'keywords' => 'proporção simples inversa',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra o valor que falta quando três valores de uma proporção são conhecidos.',
            'explainer' => <<<'HTML'
<h2>Como fazer regra de três</h2>
<p>Na regra de três direta as grandezas crescem juntas (mais quilos, mais dinheiro). Na inversa, uma cresce e a outra diminui (mais pedreiros, menos dias de obra).</p>
<p class="formula">direta: X = (B × C) ÷ A · inversa: X = (A × B) ÷ C</p>
<p><b>Exemplo:</b> 3 kg custam R$ 12. Então 5 kg custam 12 × 5 ÷ 3 = R$ 20.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quando usar a regra de três inversa?</summary><p>Quando uma grandeza aumenta e a outra diminui. Exemplo: mais trabalhadores terminam a mesma obra em menos dias.</p></details>
  <details><summary>Como saber se é direta?</summary><p>Se as duas grandezas aumentam juntas (mais quilos, mais caro), a regra de três é direta.</p></details>
</div>
HTML,
        ],
        'media' => [
            'name' => 'Média, mediana e moda',
            'seo_title' => 'Calculadora de Média, Mediana e Moda',
            'symbol' => 'x̄',
            'category' => 'matematica',
            'description' => 'Estatísticas de uma lista de números.',
            'keywords' => 'média notas escola',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Cole uma lista de números e veja média, mediana, moda e mais.',
            'explainer' => <<<'HTML'
<h2>Média, mediana e moda</h2>
<p><b>Média</b> é a soma dividida pela quantidade. <b>Mediana</b> é o valor do meio da lista em ordem. <b>Moda</b> é o valor que mais se repete.</p>
<p class="formula">média = (x₁ + x₂ + … + xₙ) ÷ n</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quando usar a mediana em vez da média?</summary><p>Quando há valores muito fora do padrão. A mediana é o valor do meio e não é puxada por extremos, como alguns salários muito altos.</p></details>
  <details><summary>E se nenhum número se repetir?</summary><p>Então não há moda: a moda é o valor que mais aparece na lista.</p></details>
</div>
HTML,
        ],
        'fracoes' => [
            'name' => 'Frações',
            'seo_title' => 'Calculadora de Frações com Simplificação',
            'symbol' => '½',
            'category' => 'matematica',
            'description' => 'Some, subtraia, multiplique e simplifique.',
            'keywords' => 'fração simplificar',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Faça contas com frações e veja o resultado simplificado, em decimal e como número misto.',
            'explainer' => <<<'HTML'
<h2>Como somar frações</h2>
<p>Para somar ou subtrair, as frações precisam ter o mesmo denominador. Multiplique em cruz e depois simplifique dividindo pelo MDC.</p>
<p class="formula">a/b + c/d = (a·d + c·b) ÷ (b·d)</p>
<p><b>Exemplo:</b> 3/4 + 5/6 = (18 + 20)/24 = 38/24 = 19/12.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Como somar frações com denominadores diferentes?</summary><p>Iguale os denominadores (normalmente pelo MMC), ajuste os numeradores e some. A calculadora faz isso e já simplifica o resultado.</p></details>
  <details><summary>O que é simplificar uma fração?</summary><p>É dividir numerador e denominador pelo mesmo número (o MDC) até não dar mais. Por exemplo, 6/8 vira 3/4.</p></details>
</div>
HTML,
        ],
        'mmc-mdc' => [
            'name' => 'MMC e MDC',
            'seo_title' => 'Calculadora de MMC e MDC Online',
            'symbol' => 'mdc',
            'category' => 'matematica',
            'description' => 'Mínimo múltiplo e máximo divisor comum.',
            'keywords' => 'múltiplo divisor',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule o mínimo múltiplo comum e o máximo divisor comum de dois ou mais números.',
            'explainer' => <<<'HTML'
<h2>Para que servem MMC e MDC</h2>
<p>O <b>MMC</b> é o menor número que é múltiplo de todos (útil para somar frações). O <b>MDC</b> é o maior número que divide todos (útil para simplificar).</p>
<p class="formula">MMC(a, b) = (a × b) ÷ MDC(a, b)</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Para que serve o MMC?</summary><p>Para achar um denominador comum ao somar frações e para descobrir quando eventos que se repetem voltam a coincidir.</p></details>
  <details><summary>Para que serve o MDC?</summary><p>Para simplificar frações e dividir coisas em partes iguais do maior tamanho possível.</p></details>
</div>
HTML,
        ],
        'bhaskara' => [
            'name' => 'Equação do 2º grau',
            'seo_title' => 'Calculadora de Bhaskara: Equação do 2º Grau',
            'symbol' => 'x²',
            'category' => 'matematica',
            'description' => 'Raízes pela fórmula de Bhaskara.',
            'keywords' => 'bhaskara delta raiz',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Encontre as raízes de uma equação do tipo ax² + bx + c = 0.',
            'explainer' => <<<'HTML'
<h2>Fórmula de Bhaskara</h2>
<p>Primeiro calcule o delta. Se for negativo, não há raízes reais; se for zero, há uma raiz; se for positivo, há duas.</p>
<p class="formula">Δ = b² − 4ac · x = (−b ± √Δ) ÷ 2a</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O que o delta (Δ) indica?</summary><p>Se Δ for positivo, há duas raízes reais. Se for zero, há uma raiz (dupla). Se for negativo, não há raízes reais.</p></details>
  <details><summary>Qual a fórmula de Bhaskara?</summary><p>x = (−b ± √Δ) ÷ 2a, com Δ = b² − 4ac.</p></details>
</div>
HTML,
        ],
        'area' => [
            'name' => 'Área de figuras',
            'seo_title' => 'Calculadora de Área de Figuras Planas',
            'symbol' => 'm²',
            'category' => 'matematica',
            'description' => 'Quadrado, retângulo, triângulo, círculo e trapézio.',
            'keywords' => 'geometria terreno',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule a área das figuras planas mais comuns.',
            'explainer' => <<<'HTML'
<h2>Fórmulas de área</h2>
<p>Quadrado: lado². Retângulo: base × altura. Triângulo: base × altura ÷ 2. Círculo: π × raio². Trapézio: (base maior + base menor) × altura ÷ 2.</p>
<p><b>Dica:</b> para saber a área de um terreno irregular, divida em figuras simples e some as áreas.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Posso misturar unidades de medida?</summary><p>Não. Use a mesma unidade em todas as medidas: o resultado sai nessa unidade ao quadrado (m², cm²...).</p></details>
  <details><summary>Como calcular a área de um cômodo irregular?</summary><p>Divida o cômodo em retângulos e triângulos, calcule a área de cada parte e some.</p></details>
</div>
HTML,
        ],
        'imc' => [
            'name' => 'IMC',
            'seo_title' => 'Calculadora de IMC: Índice de Massa Corporal',
            'symbol' => 'kg',
            'category' => 'saude',
            'description' => 'Índice de massa corporal e classificação.',
            'keywords' => 'peso altura obesidade',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule seu índice de massa corporal e veja a classificação da Organização Mundial da Saúde.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o IMC</h2>
<p>O IMC divide o peso pela altura ao quadrado. É uma referência rápida, mas não diferencia músculo de gordura.</p>
<p class="formula">IMC = peso (kg) ÷ altura (m)²</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual é o IMC normal?</summary><p>Pela classificação da OMS para adultos, entre 18,5 e 24,9.</p></details>
  <details><summary>O IMC vale para todo mundo?</summary><p>Não. Ele não diferencia músculo de gordura e não serve para crianças, gestantes e atletas. É uma triagem, não um diagnóstico.</p></details>
</div>
HTML,
        ],
        'calorias' => [
            'name' => 'Gasto calórico',
            'seo_title' => 'Calculadora de Gasto Calórico Diário e Taxa Basal',
            'symbol' => 'kcal',
            'category' => 'saude',
            'description' => 'Taxa metabólica basal e calorias por dia.',
            'keywords' => 'dieta tmb',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Estime sua taxa metabólica basal e quantas calorias você gasta por dia.',
            'explainer' => <<<'HTML'
<h2>Taxa metabólica basal</h2>
<p>É a energia que o corpo gasta em repouso. Multiplicada pelo nível de atividade, dá uma estimativa do gasto diário.</p>
<p class="formula">Mifflin-St Jeor: 10 × peso + 6,25 × altura − 5 × idade + 5 (homem) ou − 161 (mulher)</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual fórmula a calculadora usa?</summary><p>A de Mifflin-St Jeor para a taxa metabólica basal, multiplicada pelo nível de atividade física.</p></details>
  <details><summary>Quanto comer para emagrecer?</summary><p>Um pouco abaixo do gasto diário. Veja quantos quilos pode perder na calculadora de perda de peso e procure orientação de um nutricionista.</p></details>
</div>
HTML,
        ],
        'perda-de-peso' => [
            'name' => 'Perda de peso por déficit calórico',
            'seo_title' => 'Calculadora de Perda de Peso por Déficit Calórico',
            'symbol' => '−kg',
            'category' => 'saude',
            'description' => 'Quantos quilos você perde em X dias comendo menos.',
            'keywords' => 'emagrecer déficit calórico perder peso dieta quilos por semana calorias quanto vou emagrecer',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Informe quanto você gasta e quanto come por dia e veja quantos quilos pode perder ao longo dos dias, com gráfico e tabela.',
            'explainer' => <<<'HTML'
<h2>Como funciona o déficit calórico</h2>
<p>Quando você come menos calorias do que gasta, o corpo usa as reservas, principalmente gordura, para completar a diferença. Essa diferença diária é o déficit calórico.</p>
<p class="formula">quilos perdidos ≈ déficit diário × dias ÷ 7.700</p>
<p>Exemplo: quem gasta 1.800 kcal e come 1.300 kcal tem déficit de 500 kcal por dia, o que dá cerca de 0,45 kg por semana.</p>
<h2>Por que a perda desacelera</h2>
<p>Um corpo mais leve gasta menos energia. Por isso, com a mesma dieta, o déficit vai diminuindo e a perda fica mais lenta com o passar das semanas. A calculadora já considera esse efeito. A "conta simples" mostra o resultado sem esse ajuste, só para comparar.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual gasto diário devo informar?</summary><p>O gasto total do dia (taxa basal + atividades), e não só a taxa metabólica basal. Use a calculadora de gasto calórico para estimar o seu.</p></details>
  <details><summary>Quanto é seguro perder por semana?</summary><p>Para a maioria das pessoas, algo entre 0,5 kg e 1 kg por semana é considerado um ritmo razoável. Dietas muito restritivas devem ter acompanhamento de médico ou nutricionista.</p></details>
  <details><summary>Por que perdi mais na primeira semana?</summary><p>No começo o corpo elimina água e reservas de glicogênio, e a balança cai mais rápido. Depois a perda se aproxima da estimativa pelo déficit calórico.</p></details>
</div>
HTML,
        ],
        'agua' => [
            'name' => 'Água por dia',
            'seo_title' => 'Quanta Água Beber por Dia: Calculadora pelo Peso',
            'symbol' => 'H₂O',
            'category' => 'saude',
            'description' => 'Quantos litros beber pelo seu peso.',
            'keywords' => 'hidratação',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quanta água beber por dia com base no seu peso.',
            'explainer' => <<<'HTML'
<h2>Quanto de água beber</h2>
<p>Uma referência comum é 35 ml por quilo de peso. Dias quentes e exercícios aumentam a necessidade. Pessoas com problemas renais ou cardíacos devem seguir a orientação médica.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quanta água devo beber por dia?</summary><p>Uma referência comum é 35 ml por quilo de peso. Calor, exercício e algumas condições de saúde aumentam essa necessidade.</p></details>
  <details><summary>Café, suco e comida contam?</summary><p>Contam para a hidratação. Frutas, verduras e outras bebidas também fornecem água ao corpo.</p></details>
</div>
HTML,
        ],
        'gestacao' => [
            'name' => 'Idade gestacional',
            'seo_title' => 'Calculadora de Idade Gestacional e Data do Parto',
            'symbol' => '🤰',
            'category' => 'saude',
            'description' => 'Semanas de gravidez e data provável do parto.',
            'keywords' => 'gravidez dpp dum',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule as semanas de gravidez e a data provável do parto a partir da última menstruação.',
            'explainer' => <<<'HTML'
<h2>Como é calculada a data provável do parto</h2>
<p>Pela regra de Naegele, a gestação dura cerca de 280 dias (40 semanas) contados a partir do primeiro dia da última menstruação.</p>
<p class="notice">É uma estimativa. O ultrassom e o acompanhamento pré-natal dão a data mais precisa.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>A data provável do parto é exata?</summary><p>Não. Poucos bebês nascem exatamente nela. O parto a termo acontece entre 37 e 42 semanas.</p></details>
  <details><summary>E se eu não souber a data da última menstruação?</summary><p>O ultrassom do primeiro trimestre é a forma mais precisa de estimar a idade gestacional. Converse com o seu médico.</p></details>
</div>
HTML,
        ],
        'unidades' => [
            'name' => 'Conversor de medidas',
            'seo_title' => 'Conversor de Unidades de Medida Online',
            'symbol' => '⇄',
            'category' => 'conversores',
            'description' => 'Comprimento, peso, volume, temperatura e mais.',
            'keywords' => 'metro quilo celsius litro polegada velocidade área',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Converta comprimento, peso, volume, temperatura, velocidade, área, dados e tempo.',
            'explainer' => <<<'HTML'
<h2>Conversões mais buscadas</h2>
<p>1 polegada = 2,54 cm · 1 libra = 453,6 g · 1 galão americano = 3,785 litros · 1 hectare = 10.000 m² · 1 alqueire paulista = 24.200 m² · 1 GB = 1.024 MB.</p>
<p class="formula">°C = (°F − 32) × 5 ÷ 9</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quantos centímetros tem uma polegada?</summary><p>Uma polegada tem exatamente 2,54 centímetros.</p></details>
  <details><summary>Quantos quilômetros tem uma milha?</summary><p>Uma milha terrestre tem cerca de 1,609 quilômetro.</p></details>
</div>
HTML,
        ],
        'idade' => [
            'name' => 'Calculadora de idade',
            'seo_title' => 'Calculadora de Idade Exata em Anos, Meses e Dias',
            'symbol' => '🎂',
            'category' => 'datas',
            'description' => 'Idade exata em anos, meses e dias.',
            'keywords' => 'aniversário nascimento',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra sua idade exata, quantos dias você já viveu e quanto falta para o próximo aniversário.',
            'explainer' => <<<'HTML'
<h2>Como calcular a idade exata</h2>
<p>Conte os anos completos entre as datas. Se o dia ainda não chegou no mês, desconte um mês e some os dias do mês anterior.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Como é calculada a idade exata?</summary><p>Conta os anos completos desde o nascimento e, depois, os meses e dias que faltam para o próximo aniversário.</p></details>
  <details><summary>E quem nasceu em 29 de fevereiro?</summary><p>Nos anos que não são bissextos, costuma-se considerar que o aniversário é em 1º de março, para a idade completa.</p></details>
</div>
HTML,
        ],
        'diferenca-datas' => [
            'name' => 'Diferença entre datas',
            'seo_title' => 'Calcular Diferença Entre Datas em Dias',
            'symbol' => 'Δd',
            'category' => 'datas',
            'description' => 'Dias, semanas e meses entre duas datas.',
            'keywords' => 'prazo contagem',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quantos dias, semanas e meses existem entre duas datas.',
            'explainer' => <<<'HTML'
<h2>Contagem de dias</h2>
<p>A contagem considera dias corridos, sem incluir o dia inicial. Para prazos que só contam dias de trabalho, use a calculadora de dias úteis.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O dia inicial entra na conta?</summary><p>Não: a contagem é em dias corridos, sem incluir o dia inicial.</p></details>
  <details><summary>E para contar só dias de trabalho?</summary><p>Use a calculadora de dias úteis, que tira sábados, domingos e feriados nacionais.</p></details>
</div>
HTML,
        ],
        'somar-dias' => [
            'name' => 'Somar dias a uma data',
            'seo_title' => 'Somar Dias a uma Data: Calculadora de Prazos',
            'symbol' => '+d',
            'category' => 'datas',
            'description' => 'Que dia será daqui a N dias.',
            'keywords' => 'vencimento prazo',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra que dia será daqui a um número de dias (ou quantos dias atrás).',
            'explainer' => <<<'HTML'
<h2>Para que serve</h2>
<p>Útil para calcular vencimentos, prazos de devolução, fim de garantia e contagem regressiva para datas especiais.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Serve para prazos de 30, 60 ou 90 dias?</summary><p>Sim. Informe a data inicial e a quantidade de dias corridos para ver a data final e o dia da semana.</p></details>
  <details><summary>E prazos em dias úteis?</summary><p>Prazos em dias úteis pulam fins de semana e feriados. Para eles, use a calculadora de dias úteis.</p></details>
</div>
HTML,
        ],
        'dias-uteis' => [
            'name' => 'Dias úteis',
            'seo_title' => 'Calculadora de Dias Úteis Entre Datas com Feriados',
            'symbol' => '📅',
            'category' => 'datas',
            'description' => 'Dias úteis entre datas, sem feriados nacionais.',
            'keywords' => 'prazo feriado',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Conte os dias úteis entre duas datas, sem sábados, domingos e feriados nacionais.',
            'explainer' => <<<'HTML'
<h2>Feriados considerados</h2>
<p>Confraternização Universal (1/1), Tiradentes (21/4), Dia do Trabalho (1/5), Independência (7/9), Nossa Senhora Aparecida (12/10), Finados (2/11), Proclamação da República (15/11), Consciência Negra (20/11), Natal (25/12) e Sexta-feira Santa.</p>
<p class="notice">Feriados estaduais e municipais não estão incluídos. Corpus Christi e Carnaval são pontos facultativos em muitos lugares.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Quais feriados são descontados?</summary><p>Os feriados nacionais. Feriados estaduais e municipais não estão incluídos e mudam de cidade para cidade.</p></details>
  <details><summary>Sábado é dia útil?</summary><p>Para a maioria dos prazos, não. A calculadora conta de segunda a sexta.</p></details>
</div>
HTML,
        ],
        'somar-horas' => [
            'name' => 'Soma de horas',
            'seo_title' => 'Calculadora de Soma de Horas e Minutos',
            'symbol' => '⏱',
            'category' => 'datas',
            'description' => 'Some e subtraia horas e minutos.',
            'keywords' => 'ponto jornada banco de horas',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Some ou subtraia horas e minutos, útil para ponto e banco de horas.',
            'explainer' => <<<'HTML'
<h2>Horas em decimal</h2>
<p>Na folha de pagamento, as horas costumam aparecer em decimal: 7h30 = 7,5 horas. Para converter, divida os minutos por 60.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Serve para somar o banco de horas?</summary><p>Sim. Some as horas de cada dia no formato horas:minutos para ver o total da semana ou do mês.</p></details>
  <details><summary>Como converter horas em decimal?</summary><p>Divida os minutos por 60. Por exemplo, 1:30 são 1,5 hora. A calculadora mostra os dois formatos.</p></details>
</div>
HTML,
        ],
        'consumo' => [
            'name' => 'Consumo de combustível',
            'seo_title' => 'Calculadora de Consumo de Combustível (km/l)',
            'symbol' => 'km/l',
            'category' => 'veiculos',
            'description' => 'Quilômetros por litro e custo por km.',
            'keywords' => 'gasolina carro média',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule quantos quilômetros seu carro faz por litro e quanto custa cada quilômetro.',
            'explainer' => <<<'HTML'
<h2>Como medir o consumo do carro</h2>
<p>Encha o tanque e zere o hodômetro parcial. No próximo abastecimento, encha de novo e divida os quilômetros rodados pelos litros colocados.</p>
<p class="formula">consumo = km rodados ÷ litros</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Como medir o consumo do carro?</summary><p>Encha o tanque e zere o hodômetro parcial. No próximo abastecimento, divida os quilômetros rodados pelos litros colocados.</p></details>
  <details><summary>O computador de bordo é confiável?</summary><p>Ele é uma boa referência, mas pode variar alguns por cento em relação à medição feita no tanque.</p></details>
</div>
HTML,
        ],
        'alcool-gasolina' => [
            'name' => 'Álcool ou gasolina',
            'seo_title' => 'Álcool ou Gasolina: Calculadora de Qual Compensa',
            'symbol' => '⛽',
            'category' => 'veiculos',
            'description' => 'Qual combustível compensa pela regra dos 70%.',
            'keywords' => 'etanol flex posto',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra se compensa abastecer com etanol ou gasolina pela regra dos 70%.',
            'explainer' => <<<'HTML'
<h2>A regra dos 70%</h2>
<p>Em média, o carro flex roda cerca de 30% menos com etanol. Por isso o etanol compensa quando custa até 70% do preço da gasolina.</p>
<p class="formula">preço do etanol ÷ preço da gasolina ≤ 0,70 → etanol</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>A regra dos 70% vale para todo carro?</summary><p>É uma média. Alguns motores flex rendem melhor com etanol. Se souber o consumo real do seu carro com cada combustível, ajuste o limite.</p></details>
  <details><summary>Posso misturar álcool e gasolina no tanque?</summary><p>Sim, em carros flex. O motor se ajusta à mistura, e o consumo fica entre os dois.</p></details>
</div>
HTML,
        ],
        'eletrico-vs-combustao' => [
            'name' => 'Carro elétrico × combustão',
            'seo_title' => 'Carro Elétrico ou a Combustão: Custo por Km',
            'symbol' => '⚡',
            'category' => 'veiculos',
            'description' => 'Custo por km na tomada contra gasolina ou etanol.',
            'keywords' => 'carro elétrico recarga kwh tomada gasolina etanol economia por km',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Compare quanto você gasta por quilômetro recarregando um carro elétrico na tomada e abastecendo com gasolina ou etanol, e veja a economia por mês e por ano.',
            'explainer' => <<<'HTML'
<h2>Como comparar o custo por quilômetro</h2>
<p><b>Elétrico:</b> divida o preço do kWh pelo consumo do carro (km por kWh), já descontando a energia que se perde na recarga.</p>
<p class="formula">R$/km elétrico = preço do kWh ÷ (km por kWh × (1 − perda))</p>
<p><b>Combustão:</b> divida o preço do litro pelo consumo (km por litro).</p>
<p class="formula">R$/km combustão = preço do litro ÷ km por litro</p>
<h2>Onde achar os números</h2>
<p><b>Preço do kWh:</b> na conta de luz, divida o valor total (com impostos e bandeira tarifária) pelos kWh consumidos no mês. <b>Consumo do carro:</b> a tabela do PBE Veicular (Inmetro) traz o consumo em km/l e o consumo de energia dos elétricos. O computador de bordo mostra o consumo real do seu jeito de dirigir.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Recarregar em eletroposto muda a conta?</summary><p>Muda bastante. Carregadores rápidos em shoppings e estradas costumam cobrar por kWh bem mais que a tarifa residencial. Se vai recarregar fora de casa com frequência, use o preço médio que você paga.</p></details>
  <details><summary>A economia paga o preço mais alto do elétrico?</summary><p>Preencha quanto o elétrico custa a mais na compra: a calculadora mostra em quantos anos a economia de energia cobre essa diferença. Lembre que seguro, IPVA, manutenção e revenda também pesam na decisão.</p></details>
</div>
HTML,
        ],
        'investimentos' => [
            'name' => 'CDB, LCI, Tesouro ou poupança',
            'seo_title' => 'CDB, LCI/LCA, Tesouro Selic ou Poupança: Qual Rende Mais?',
            'symbol' => '📈',
            'category' => 'financas',
            'description' => 'Compara o rendimento líquido de CDB, LCI/LCA, Tesouro Selic e poupança.',
            'keywords' => 'qual investimento rende mais cdb lci lca tesouro selic poupança rendimento líquido imposto de renda 100% do cdi quanto rende 10 mil simulador de investimentos renda fixa',
            'ready' => true,
            'reviewed' => '2026-10-01',
            'lead' => 'Compare quanto seu dinheiro rende, já descontando o imposto de renda, em CDB, LCI/LCA, Tesouro Selic e poupança.',
            'explainer' => <<<'HTML'
<h2>Como a comparação é feita</h2>
<p>Cada depósito (o valor inicial e cada aporte mensal) rende pelo tempo em que ficou aplicado. No fim, a calculadora desconta o imposto de renda de cada um pela tabela regressiva e mostra o valor líquido para resgatar.</p>
<ul>
  <li><b>CDB:</b> rende uma porcentagem do CDI e paga imposto de renda.</li>
  <li><b>LCI e LCA:</b> rendem uma porcentagem do CDI e são isentas de IR para pessoa física. Exigem pelo menos 6 meses de aplicação (as atreladas ao CDI, pela Resolução CMN 5.215/2025).</li>
  <li><b>Tesouro Selic:</b> rende a Selic (cerca de 0,10 ponto acima do CDI), paga IR e taxa de custódia da B3 de 0,20% ao ano só sobre o que passar de R$ 10 mil.</li>
  <li><b>Poupança:</b> isenta de IR. Com a Selic acima de 8,5% ao ano, rende 0,5% ao mês mais a TR.</li>
</ul>
<h2>Imposto de renda na renda fixa (tabela regressiva)</h2>
<table class="data-table">
  <thead><tr><th>Tempo aplicado</th><th>Alíquota sobre o rendimento</th></tr></thead>
  <tbody>
    <tr><td>Até 180 dias</td><td>22,5%</td></tr>
    <tr><td>De 181 a 360 dias</td><td>20%</td></tr>
    <tr><td>De 361 a 720 dias</td><td>17,5%</td></tr>
    <tr><td>Acima de 720 dias</td><td>15%</td></tr>
  </tbody>
</table>
<p class="notice">Simulação com as taxas de hoje mantidas durante todo o prazo; na prática o CDI e a poupança mudam. O CDI e a poupança vêm do Banco Central e podem ser alterados. Não considera IOF (só existe em resgates com menos de 30 dias) nem o preço de mercado do Tesouro em vendas antecipadas. Não é recomendação de investimento.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>LCI a 90% do CDI rende mais que CDB a 100%?</summary><p>Muitas vezes sim, porque a LCI não paga imposto de renda. Em 2 anos, um CDB de 100% do CDI paga 15% a 17,5% de IR sobre o rendimento; aí uma LCI acima de uns 83% a 85% do CDI já empata ou ganha. Coloque as taxas na calculadora para ver no seu caso.</p></details>
  <details><summary>A poupança ainda vale a pena?</summary><p>Ela é isenta de IR e tem liquidez, mas costuma render menos que um CDB de 100% do CDI ou o Tesouro Selic, principalmente com a Selic alta. Para a reserva de emergência, CDB com liquidez diária e Tesouro Selic são as alternativas mais comuns.</p></details>
  <details><summary>O que é o CDI?</summary><p>É a taxa dos empréstimos entre bancos, que anda colada na Selic. A maioria dos CDBs, LCIs e LCAs paga uma porcentagem dele, como "100% do CDI".</p></details>
  <details><summary>Esses investimentos têm garantia?</summary><p>CDB, LCI e LCA têm garantia do FGC até R$ 250 mil por CPF e por instituição. A poupança também. O Tesouro Selic é garantido pelo Tesouro Nacional.</p></details>
</div>
HTML,
        ],
        'fgts' => [
            'name' => 'FGTS: saldo e multa de 40%',
            'seo_title' => 'Calculadora do FGTS: Saldo Futuro, Depósitos e Multa de 40%',
            'symbol' => 'FG',
            'category' => 'trabalho',
            'description' => 'Quanto você terá no FGTS e quanto recebe com a multa de 40% na demissão.',
            'keywords' => 'fgts calcular saldo do fgts quanto vou ter de fgts multa de 40% demissão sem justa causa depósito 8% rendimento fgts saque rescisão',
            'ready' => true,
            'reviewed' => '2026-10-01',
            'lead' => 'Calcule quanto vai ter no FGTS daqui a alguns meses e quanto receberia numa demissão sem justa causa, com a multa de 40%.',
            'explainer' => <<<'HTML'
<h2>Como o FGTS é calculado</h2>
<p>Todo mês a empresa deposita <b>8% do salário bruto</b> na sua conta do FGTS (2% para jovem aprendiz). Esse valor não é descontado do seu salário. Também há depósito sobre o 13º e sobre o 1/3 de férias.</p>
<p>O saldo rende <b>3% ao ano mais a TR</b>, e o FGTS ainda distribui parte do lucro do fundo todo ano. A calculadora usa só os 3% (o mínimo garantido), então o saldo real tende a ser um pouco maior.</p>
<h2>Multa de 40% na demissão</h2>
<p>Na demissão sem justa causa, a empresa paga uma multa de 40% sobre todos os depósitos feitos durante aquele emprego, com correção. Na demissão por acordo, a multa é de 20% e o saque é de 80% do saldo.</p>
<p class="notice">Estimativa. A multa usa o saldo calculado como base (na prática, é a soma dos depósitos do emprego atual corrigidos). Confira o saldo real no app FGTS.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O FGTS é descontado do salário?</summary><p>Não. Os 8% são pagos pela empresa, além do salário. Por isso o valor não aparece como desconto no holerite, só como informação.</p></details>
  <details><summary>Quem está no saque-aniversário recebe o FGTS na demissão?</summary><p>Recebe só a multa de 40%. O saldo fica bloqueado e continua disponível apenas no saque-aniversário anual. Para voltar ao saque-rescisão, o pedido só vale depois de 24 meses.</p></details>
  <details><summary>Quanto o FGTS rende?</summary><p>3% ao ano mais a TR, mais a distribuição anual de lucros do fundo. Pela decisão do STF de 2024, se o total ficar abaixo da inflação (IPCA), a diferença precisa ser compensada.</p></details>
</div>
HTML,
        ],
        'saque-aniversario-fgts' => [
            'name' => 'Saque-aniversário do FGTS',
            'seo_title' => 'Saque-Aniversário FGTS: Calcule Quanto Você Pode Sacar',
            'symbol' => '🎂',
            'category' => 'trabalho',
            'description' => 'Valor do saque-aniversário pela tabela oficial e o prazo para sacar.',
            'keywords' => 'saque aniversário fgts quanto posso sacar tabela alíquota parcela adicional calcular saque aniversário vale a pena prazo para sacar',
            'ready' => true,
            'reviewed' => '2026-10-01',
            'lead' => 'Descubra quanto você pode sacar por ano no saque-aniversário do FGTS, pela tabela oficial, e até quando pode retirar.',
            'explainer' => <<<'HTML'
<h2>Tabela do saque-aniversário</h2>
<p>O valor é uma porcentagem do saldo total do FGTS (todas as contas somadas) mais uma parcela adicional fixa:</p>
<table class="data-table">
  <thead><tr><th>Saldo total</th><th>Alíquota</th><th>Parcela adicional</th></tr></thead>
  <tbody>
    <tr><td>Até R$ 500,00</td><td>50%</td><td>—</td></tr>
    <tr><td>De R$ 500,01 a R$ 1.000,00</td><td>40%</td><td>R$ 50,00</td></tr>
    <tr><td>De R$ 1.000,01 a R$ 5.000,00</td><td>30%</td><td>R$ 150,00</td></tr>
    <tr><td>De R$ 5.000,01 a R$ 10.000,00</td><td>20%</td><td>R$ 650,00</td></tr>
    <tr><td>De R$ 10.000,01 a R$ 15.000,00</td><td>15%</td><td>R$ 1.150,00</td></tr>
    <tr><td>De R$ 15.000,01 a R$ 20.000,00</td><td>10%</td><td>R$ 1.900,00</td></tr>
    <tr><td>Acima de R$ 20.000,00</td><td>5%</td><td>R$ 2.900,00</td></tr>
  </tbody>
</table>
<p>O dinheiro fica disponível do primeiro dia útil do mês do aniversário até o último dia útil do segundo mês seguinte.</p>
<h2>Vale a pena?</h2>
<p>O saque-aniversário dá um dinheiro todo ano, mas tem um custo: se você for <b>demitido sem justa causa</b>, recebe só a multa de 40% e o saldo fica preso. Para voltar ao saque-rescisão, o pedido só vale depois de 24 meses.</p>
<p>Desde 1º de novembro de 2025, a antecipação (empréstimo com o saque-aniversário como garantia) só pode ser feita 90 dias depois da adesão, com parcelas de R$ 100 a R$ 500. A partir de 1º de novembro de 2026, o limite cai de 5 para 3 parcelas por ano.</p>
<p class="notice">Valores pela tabela oficial (Caixa). Confira o saldo de todas as contas no app FGTS antes de decidir.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Como é calculado o saque-aniversário?</summary><p>Aplica-se a alíquota da faixa do saldo e soma-se a parcela adicional. Exemplo da Caixa: com R$ 1.000 de saldo, o saque é de 40% (R$ 400) mais R$ 50, total de R$ 450.</p></details>
  <details><summary>Quem aderiu e foi demitido pode sacar tudo?</summary><p>Não. Recebe a multa de 40% paga pela empresa, mas o saldo continua bloqueado. Só o saque anual segue liberado.</p></details>
  <details><summary>Posso desistir do saque-aniversário?</summary><p>Pode pedir a volta ao saque-rescisão a qualquer momento no app FGTS, mas ela só passa a valer no primeiro dia do 25º mês depois do pedido.</p></details>
</div>
HTML,
        ],
        'correcao-monetaria' => [
            'name' => 'Correção monetária',
            'seo_title' => 'Calculadora de Correção Monetária: IPCA, IGP-M, INPC, Selic e CDI',
            'symbol' => '%↑',
            'category' => 'financas',
            'description' => 'Corrige um valor por IPCA, IGP-M, INPC, Selic, CDI ou poupança.',
            'keywords' => 'correção monetária atualizar valor corrigir pela inflação ipca igpm reajuste de aluguel inpc igp-di selic cdi poupança acumulado 12 meses',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Informe um valor, escolha o índice (IPCA, IGP-M, INPC, IGP-DI, Selic, CDI ou poupança) e o período: veja o valor corrigido, a variação acumulada e o mês a mês.',
            'explainer' => <<<'HTML'
<h2>Como funciona a correção monetária</h2>
<p>A correção aplica, mês a mês, a variação do índice escolhido sobre o valor. A conta é composta: a variação de cada mês incide sobre o valor já corrigido nos meses anteriores.</p>
<p class="formula">valor corrigido = valor × (1 + variação mês 1) × (1 + variação mês 2) × …</p>
<p><b>Qual índice usar?</b> IPCA para inflação oficial e muitos contratos; IGP-M ou IPCA para aluguel (o que estiver no contrato); INPC para salários e benefícios do INSS; Selic, CDI e poupança para comparar com o rendimento de aplicações.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Como calcular o reajuste do aluguel?</summary><p>Veja no contrato o índice (IGP-M ou IPCA) e use o acumulado dos 12 meses anteriores ao aniversário do contrato, normalmente até o último mês divulgado. O botão "Últimos 12 meses" já faz essa conta.</p></details>
  <details><summary>O valor pode diminuir?</summary><p>Sim. Em meses de deflação o índice é negativo, e se o acumulado do período for negativo o valor corrigido fica menor. Muitos contratos preveem que, nesse caso, o valor não muda.</p></details>
  <details><summary>Qual a diferença entre IPCA e IGP-M?</summary><p>O IPCA (IBGE) mede os preços que as famílias pagam no dia a dia. O IGP-M (FGV) também inclui preços no atacado e na construção, por isso oscila mais com o dólar e as commodities.</p></details>
</div>
HTML,
        ],
        'custo-de-viagem' => [
            'name' => 'Custo de viagem',
            'seo_title' => 'Calculadora de Viagem: Combustível, Pedágios e Rota no Mapa',
            'symbol' => '🗺',
            'category' => 'veiculos',
            'description' => 'Rota no mapa, gasto com combustível e pedágios do caminho.',
            'keywords' => 'calculadora de viagem custo de viagem gasto de combustível viagem pedágio rota mapa distância entre cidades quanto gasto de gasolina',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Informe de onde sai, para onde vai, o preço do litro e o consumo do carro: veja a rota no mapa, a distância, o tempo, o gasto com combustível e os pedágios do caminho.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o custo da viagem</h2>
<p>A rota mais rápida de carro é calculada com os mapas do OpenStreetMap. Com a distância, a conta do combustível é simples:</p>
<p class="formula">combustível = distância ÷ consumo (km/l) × preço do litro</p>
<p>Os pedágios são as praças que ficam no caminho, com o valor para carro de passeio quando ele está informado no OpenStreetMap. Some tudo e, se quiser, divida entre os passageiros.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Os valores dos pedágios são oficiais?</summary><p>Não. São valores informados pela comunidade do OpenStreetMap e podem estar desatualizados ou faltar em algumas praças. Confira no site da concessionária, da ANTT ou da agência do seu estado antes de viajar.</p></details>
  <details><summary>Qual consumo devo usar?</summary><p>O consumo na estrada, que costuma ser melhor que na cidade. Veja no computador de bordo, no manual ou na tabela do Inmetro. Carro cheio, ar-condicionado e velocidade alta aumentam o gasto.</p></details>
  <details><summary>O tempo de viagem considera trânsito?</summary><p>Não. É o tempo ao volante em condições normais, sem paradas e sem trânsito. Para viagens longas, inclua paradas para descanso a cada 2 horas.</p></details>
</div>
HTML,
        ],
        'ipva' => [
            'name' => 'IPVA + licenciamento',
            'seo_title' => 'Calculadora de IPVA 2026 por Estado + Licenciamento',
            'symbol' => 'IPVA',
            'category' => 'veiculos',
            'description' => 'Estimativa do IPVA 2026 e da taxa do Detran por estado.',
            'keywords' => 'ipva 2026 licenciamento detran sefaz tabela fipe imposto carro moto alíquota',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Escolha o estado, informe o valor do veículo na tabela FIPE e veja a estimativa do IPVA 2026 com a taxa de licenciamento.',
            'explainer' => <<<'HTML'
<h2>Como é calculado o IPVA</h2>
<p>O IPVA é um imposto estadual: cada estado define a sua alíquota, que é aplicada sobre o valor venal do veículo, normalmente a tabela FIPE de referência do ano anterior.</p>
<p class="formula">IPVA = valor FIPE × alíquota do estado</p>
<p>Em 2026, as alíquotas de carros de passeio vão de 1,5% (Amazonas) e 1,9% (Paraná) até 4% (São Paulo, Minas Gerais e Rio de Janeiro). Motos costumam pagar menos. Vários estados cobram alíquotas diferentes conforme potência ou combustível: por isso o campo da alíquota pode ser ajustado.</p>
<h2>Licenciamento</h2>
<p>A taxa de licenciamento é cobrada pelo Detran todo ano para emitir o documento do veículo (CRLV digital). Ela é separada do IPVA e o valor muda de estado para estado.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Tem desconto para pagar à vista?</summary><p>Muitos estados dão desconto na cota única paga até o primeiro vencimento, e alguns dão desconto para bons condutores. Confira no site da Secretaria da Fazenda do seu estado.</p></details>
  <details><summary>Carro antigo paga IPVA?</summary><p>Depende do estado. Muitos isentam veículos com mais de 15 ou 20 anos de fabricação. Há também isenções para pessoas com deficiência, táxis e, em alguns estados, carros elétricos.</p></details>
</div>
HTML,
        ],
        'depreciacao-veiculo' => [
            'name' => 'Depreciação do carro',
            'seo_title' => 'Desvalorização do Carro na Tabela FIPE: Histórico de 5 Anos',
            'symbol' => '↘',
            'category' => 'veiculos',
            'description' => 'Histórico real na Tabela FIPE e quanto o carro perde por ano.',
            'keywords' => 'depreciação desvalorização tabela fipe histórico fipe valor do carro anos anteriores revenda perda de valor carro usado moto zero km',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Escolha o carro, moto ou caminhão e veja na Tabela FIPE quanto ele valia nos últimos 5 anos: gráfico, perda total e perda média por ano. Ou estime a desvalorização futura.',
            'explainer' => <<<'HTML'
<h2>Histórico na Tabela FIPE</h2>
<p>A consulta busca o preço do mesmo veículo (marca, modelo e ano) no mesmo mês de cada um dos últimos 5 anos e mostra se ele desvalorizou ou valorizou. A perda média por ano é a taxa composta entre o primeiro e o último valor.</p>
<p class="formula">perda média por ano = (valor de hoje ÷ valor de 5 anos atrás)^(1/5) − 1</p>
<h2>Como o carro perde valor</h2>
<p>A maior perda acontece no primeiro ano: o carro zero km deixa de ser novo assim que sai da concessionária. Depois, a desvalorização costuma ser menor e mais constante, ano a ano.</p>
<p class="formula">valor futuro = valor atual × (1 − perda do 1º ano) × (1 − perda anual)ⁿ⁻¹</p>
<p>Os percentuais sugeridos são médias aproximadas por categoria. Picapes e modelos muito procurados costumam perder menos; carros de luxo, importados e elétricos, mais.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Carro pode valorizar?</summary><p>Pode. Entre 2021 e 2022, com falta de carros novos, muitos usados subiram de preço na Tabela FIPE. O gráfico mostra esses períodos de alta.</p></details>
  <details><summary>A tabela FIPE vai mostrar exatamente esse valor?</summary><p>Não. A FIPE acompanha os preços médios anunciados a cada mês, e eles dependem da procura, de lançamentos e da economia. Use o resultado como estimativa para planejar a troca do carro.</p></details>
  <details><summary>Como diminuir a desvalorização?</summary><p>Revisões em dia na concessionária ou com nota fiscal, baixa quilometragem, cores neutras e versões mais procuradas ajudam o carro a valer mais na revenda.</p></details>
</div>
HTML,
        ],
        'custo-por-km' => [
            'name' => 'Custo por km (motorista de app)',
            'seo_title' => 'Custo por Km Rodado para Motorista de Uber e 99',
            'symbol' => 'R$/km',
            'category' => 'veiculos',
            'description' => 'Custo real de cada km e o lucro das corridas.',
            'keywords' => 'uber 99 motorista de aplicativo custo por quilômetro lucro corrida combustível pneu óleo depreciação',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quanto custa de verdade cada quilômetro rodado, com combustível, pneus, óleo, manutenção, seguro, IPVA e depreciação, e se a corrida do aplicativo dá lucro.',
            'explainer' => <<<'HTML'
<h2>Custos variáveis e custos fixos</h2>
<p><b>Variáveis</b> crescem com os km rodados: combustível, pneus e óleo. <b>Fixos</b> existem mesmo com o carro parado: seguro, IPVA, licenciamento, depreciação, parcela ou aluguel. Para saber o custo por km, os fixos do mês são divididos pelos km rodados.</p>
<p class="formula">custo por km = combustível/km + pneus/km + óleo/km + (custos fixos do mês ÷ km do mês)</p>
<h2>A corrida vale a pena?</h2>
<p>Divida o que você recebeu no mês pelos km rodados, <b>incluindo os km sem passageiro</b> (buscando o cliente ou voltando). Se o valor por km for menor que o custo por km, você está pagando para trabalhar, mesmo que o dinheiro entre todo dia.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Por que incluir a depreciação?</summary><p>Porque o carro vale menos a cada km rodado. Esse dinheiro não sai do bolso agora, mas aparece na hora de vender ou trocar de carro. Use a calculadora de depreciação para estimar o valor por ano.</p></details>
  <details><summary>Carro alugado ou próprio?</summary><p>No carro alugado, coloque o aluguel mensal e zere IPVA, seguro e depreciação se já estiverem incluídos. No próprio financiado, coloque a parcela.</p></details>
</div>
HTML,
        ],
        'salario-hora' => [
            'name' => 'Salário hora ↔ mensal',
            'seo_title' => 'Calculadora de Salário por Hora e Valor Hora Freelancer',
            'symbol' => 'R$/h',
            'category' => 'trabalho',
            'description' => 'Valor da sua hora e quanto cobrar como freelancer.',
            'keywords' => 'valor da hora salário por hora freelancer quanto cobrar hora trabalhada 220 horas pj',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Descubra quanto você ganha por hora trabalhada, converta o valor da hora em salário mensal ou calcule quanto cobrar por hora como freelancer.',
            'explainer' => <<<'HTML'
<h2>Como calcular o valor da hora</h2>
<p>Divida o salário mensal pela jornada mensal. Quem trabalha 44 horas por semana tem jornada mensal de 220 horas; 40 horas por semana dão 200 horas.</p>
<p class="formula">valor da hora = salário ÷ jornada mensal · jornada mensal = horas por semana × 5</p>
<h2>Quanto cobrar como freelancer</h2>
<p>O freelancer precisa cobrir com as horas cobradas a meta de ganho, os custos do trabalho, os impostos e as semanas sem trabalho (férias, feriados, doença). Por isso o valor da hora de um freelancer costuma ser bem maior que o de um empregado com o mesmo salário.</p>
<p class="formula">valor da hora = (meta + custos) ÷ (1 − impostos) ÷ horas cobráveis por mês</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Por que a jornada é 220 horas e não 176?</summary><p>Pela CLT, a jornada mensal considera 5 semanas por mês, já incluindo o descanso semanal remunerado. Por isso 44 horas × 5 = 220 horas. É esse número que as empresas usam para calcular hora extra.</p></details>
  <details><summary>Freelancer deve contar todas as horas trabalhadas?</summary><p>Não. Conte só as horas que o cliente paga. Prospecção, orçamentos, reuniões comerciais e tarefas administrativas também tomam tempo e precisam estar embutidas no valor da hora.</p></details>
</div>
HTML,
        ],
        'cpf-cnpj' => [
            'name' => 'Gerador e validador de CPF/CNPJ',
            'seo_title' => 'Gerador e Validador de CPF e CNPJ (Alfanumérico)',
            'symbol' => 'CPF',
            'category' => 'dev',
            'description' => 'Valida ou gera números para testes, inclusive CNPJ alfanumérico.',
            'keywords' => 'gerar cpf validar cpf gerador de cnpj validador cnpj alfanumérico dígito verificador teste desenvolvedor',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Valide se um CPF ou CNPJ é matematicamente correto ou gere números fictícios para testar sistemas, inclusive no novo formato de CNPJ com letras.',
            'explainer' => <<<'HTML'
<h2>Como funcionam os dígitos verificadores</h2>
<p>Os dois últimos dígitos do CPF e do CNPJ são calculados a partir dos anteriores (módulo 11). Cada dígito é multiplicado por um peso, a soma é dividida por 11 e o resto define o dígito verificador. Se o resto for 0 ou 1, o dígito é 0; senão, é 11 menos o resto.</p>
<p class="formula">dígito = soma(dígito × peso) mod 11 → resto &lt; 2 ? 0 : 11 − resto</p>
<h2>CNPJ alfanumérico</h2>
<p>A partir de julho de 2026, a Receita Federal passa a emitir CNPJs com letras nas 12 primeiras posições (Instrução Normativa RFB nº 2.229/2024). Os CNPJs antigos continuam válidos. No cálculo, cada caractere vale o seu código ASCII menos 48: os números continuam com o mesmo valor e a letra A vale 17, B vale 18, e assim por diante. Os dois dígitos verificadores continuam sendo números.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Um CPF válido aqui existe de verdade?</summary><p>Não necessariamente. A ferramenta confere só a matemática dos dígitos. Para saber se o CPF existe e está regular, só consultando a Receita Federal.</p></details>
  <details><summary>Posso usar os números gerados em cadastros?</summary><p>Não. Eles servem para testar sistemas em desenvolvimento. Usar documento falso para se passar por outra pessoa ou enganar alguém é crime.</p></details>
</div>
HTML,
        ],
        'timestamp' => [
            'name' => 'Timestamp Unix ↔ data',
            'seo_title' => 'Conversor de Timestamp Unix para Data e Hora',
            'symbol' => '⏲',
            'category' => 'dev',
            'description' => 'Converte timestamp em data legível e vice-versa, com fuso.',
            'keywords' => 'unix timestamp epoch converter data hora fuso horário milissegundos iso 8601',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Converta um timestamp Unix (como 1790784000) em data e hora legíveis, ou uma data em timestamp, escolhendo o fuso horário.',
            'explainer' => <<<'HTML'
<h2>O que é o timestamp Unix</h2>
<p>É a quantidade de segundos desde 1º de janeiro de 1970, 00:00:00 em UTC (a "era Unix"). Ele é o mesmo no mundo todo: o fuso horário só muda a forma de mostrar a data. JavaScript e várias APIs usam milissegundos (13 dígitos) em vez de segundos (10 dígitos).</p>
<p class="formula">data = 01/01/1970 00:00:00 UTC + timestamp segundos</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual o fuso horário de Brasília?</summary><p>UTC−3, sem horário de verão desde 2019. Amazonas e Mato Grosso ficam em UTC−4, o Acre em UTC−5 e Fernando de Noronha em UTC−2.</p></details>
  <details><summary>O que é o problema do ano 2038?</summary><p>Sistemas que guardam o timestamp em um inteiro de 32 bits com sinal só vão até 19/01/2038 03:14:07 UTC (2.147.483.647). Sistemas modernos usam 64 bits e não têm esse limite.</p></details>
</div>
HTML,
        ],
        'base64' => [
            'name' => 'Base64 encoder / decoder',
            'seo_title' => 'Base64 Encode e Decode Online (Texto e Imagem)',
            'symbol' => 'B64',
            'category' => 'dev',
            'description' => 'Codifica e decodifica textos, imagens e arquivos em Base64.',
            'keywords' => 'base64 encode decode codificar decodificar imagem data url base64url jwt api',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Codifique ou decodifique textos em Base64 (com acentos e emojis), converta imagens e arquivos em Base64 ou data URL e veja a prévia de imagens decodificadas.',
            'explainer' => <<<'HTML'
<h2>O que é Base64</h2>
<p>Base64 representa qualquer dado binário usando só 64 caracteres seguros (A–Z, a–z, 0–9, + e /). É usado para enviar arquivos dentro de JSON, em e-mails, em data URLs e em tokens. Cada 3 bytes viram 4 caracteres, por isso o resultado fica cerca de 33% maior.</p>
<p class="formula">tamanho em Base64 = ⌈bytes ÷ 3⌉ × 4</p>
<p><b>Base64URL</b> troca + e / por - e _ e tira o "=" do fim, para poder ir em URLs e em tokens JWT.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Base64 é criptografia?</summary><p>Não. É só uma forma de representar os dados: qualquer pessoa decodifica. Nunca use Base64 para "esconder" senhas ou dados sensíveis.</p></details>
  <details><summary>Meu arquivo é enviado para algum servidor?</summary><p>Não. Tudo é feito no seu navegador, e o arquivo não sai do seu computador.</p></details>
</div>
HTML,
        ],
        'contador-bytes' => [
            'name' => 'Contador de bytes (payload)',
            'seo_title' => 'Contador de Bytes: Tamanho de String e JSON',
            'symbol' => 'KB',
            'category' => 'dev',
            'description' => 'Tamanho exato de textos e JSON em bytes, KB e MB (UTF-8).',
            'keywords' => 'contador de bytes tamanho string payload json kb mb utf-8 utf-16 limite api',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Calcule o tamanho exato em bytes, KB ou MB de um texto ou JSON, contando acentos e emojis em UTF-8, para respeitar limites de APIs, bancos de dados e filas.',
            'explainer' => <<<'HTML'
<h2>Caracteres não são bytes</h2>
<p>Em UTF-8, o formato padrão da web, letras sem acento ocupam 1 byte, letras acentuadas (ç, ã, é) ocupam 2 bytes, símbolos como € ocupam 3 e emojis ocupam 4. Por isso um texto de 100 caracteres pode ter bem mais de 100 bytes.</p>
<p><b>KB ou KiB?</b> 1 KB = 1.000 bytes (padrão do sistema internacional, usado por muitos serviços). 1 KiB = 1.024 bytes (usado por vários sistemas operacionais). Confira qual a documentação da API usa.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Por que o .length do JavaScript dá outro número?</summary><p>O JavaScript guarda textos em UTF-16: o .length conta unidades de 16 bits, e um emoji conta como 2. O tamanho enviado pela rede é o de UTF-8.</p></details>
  <details><summary>Minificar o JSON diminui o tamanho?</summary><p>Sim: tirar espaços e quebras de linha pode reduzir bastante um JSON indentado. A compressão gzip/brotli do servidor reduz ainda mais na transferência.</p></details>
</div>
HTML,
        ],
        'cron' => [
            'name' => 'Gerador de expressão cron',
            'seo_title' => 'Gerador de Expressão Cron com Explicação',
            'symbol' => '* * *',
            'category' => 'dev',
            'description' => 'Monta e explica expressões cron em português.',
            'keywords' => 'cron crontab expressão cron agendamento cpanel linux gerador parser próximas execuções',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Monte uma expressão cron escolhendo o intervalo, ou cole uma expressão pronta para ver em português quando a rotina vai rodar e as próximas execuções.',
            'explainer' => <<<'HTML'
<h2>Os 5 campos do cron</h2>
<p>Uma expressão cron tem 5 campos separados por espaço: <b>minuto</b> (0–59), <b>hora</b> (0–23), <b>dia do mês</b> (1–31), <b>mês</b> (1–12) e <b>dia da semana</b> (0–7, em que 0 e 7 são domingo).</p>
<p class="formula">0 3 * * 1-5 → às 03:00, de segunda a sexta</p>
<p><b>*</b> = qualquer valor · <b>*/15</b> = a cada 15 · <b>1-5</b> = de 1 a 5 · <b>1,15</b> = no 1 e no 15. Atalhos como @daily e @hourly também são aceitos.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Em que fuso horário o cron roda?</summary><p>No fuso do servidor. Muitos servidores e serviços de nuvem usam UTC, que está 3 horas à frente de Brasília: um agendamento para 03:00 UTC roda à meia-noite no horário de Brasília.</p></details>
  <details><summary>Dia do mês e dia da semana juntos?</summary><p>No cron padrão, se os dois estiverem definidos, a rotina roda quando QUALQUER um deles bater. "0 0 1 * 1" roda todo dia 1 e também toda segunda-feira.</p></details>
</div>
HTML,
        ],
        'jwt' => [
            'name' => 'JWT decoder',
            'seo_title' => 'JWT Decoder Online: Decodificar Token JWT',
            'symbol' => 'JWT',
            'category' => 'dev',
            'description' => 'Decodifica cabeçalho e payload de tokens JWT no navegador.',
            'keywords' => 'jwt decoder decodificar token json web token payload header exp bearer',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Cole um token JWT e veja o cabeçalho, o payload e as datas de emissão e expiração, sem enviar nada para servidor nenhum.',
            'explainer' => <<<'HTML'
<h2>Como é um JWT</h2>
<p>Um JSON Web Token tem 3 partes separadas por ponto: <b>cabeçalho</b> (algoritmo e tipo), <b>payload</b> (os dados, chamados de claims) e <b>assinatura</b>. As duas primeiras são JSON em Base64URL: qualquer um consegue ler. A assinatura garante que ninguém alterou o token, e só quem tem a chave consegue conferir.</p>
<p class="formula">base64url(cabeçalho) . base64url(payload) . assinatura</p>
<p>Datas como <code>exp</code> (expiração), <code>iat</code> (emissão) e <code>nbf</code> (válido a partir de) vêm em timestamp Unix e são mostradas em data legível.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>É seguro colar meu token aqui?</summary><p>A decodificação acontece só no seu navegador; o token não é enviado. Mesmo assim, trate tokens de produção como senhas: eles dão acesso à conta enquanto não expiram.</p></details>
  <details><summary>A ferramenta verifica a assinatura?</summary><p>Não. Ela só lê o conteúdo. A verificação precisa da chave secreta ou pública e deve ser feita no seu servidor.</p></details>
</div>
HTML,
        ],
        'json-formatter' => [
            'name' => 'JSON formatter',
            'seo_title' => 'JSON Formatter e Validador Online',
            'symbol' => '{ }',
            'category' => 'dev',
            'description' => 'Indenta, valida, ordena e minifica JSON.',
            'keywords' => 'json formatter beautifier formatar json validar json minificar indentar pretty print',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Cole um JSON ou abra um arquivo de até 50 MB para indentar, validar (com a linha e a coluna do erro), ordenar as chaves ou minificar de volta.',
            'explainer' => <<<'HTML'
<h2>Formatar, validar e minificar</h2>
<p><b>Formatar</b> (beautify) adiciona quebras de linha e indentação para facilitar a leitura. <b>Minificar</b> remove os espaços para deixar o JSON menor para enviar. <b>Validar</b> confere se o texto segue as regras do JSON e mostra onde está o erro.</p>
<h2>Erros mais comuns</h2>
<p>Vírgula sobrando depois do último item, aspas simples no lugar de aspas duplas, chaves sem aspas, comentários (JSON não aceita) e valores como undefined ou NaN.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Meu JSON é enviado para algum lugar?</summary><p>Não. Tudo acontece no seu navegador. Mesmo assim, evite colar dados sensíveis em qualquer ferramenta on-line.</p></details>
  <details><summary>Ordenar as chaves muda o JSON?</summary><p>Não muda os dados: em JSON, a ordem das chaves de um objeto não tem significado. Ordenar ajuda a comparar dois JSONs parecidos. A ordem dos itens das listas é mantida.</p></details>
</div>
HTML,
        ],
        'caracteres' => [
            'name' => 'Contador de caracteres',
            'seo_title' => 'Contador de Caracteres e Palavras Online',
            'symbol' => 'Aa',
            'category' => 'texto',
            'description' => 'Caracteres, palavras e tempo de leitura.',
            'keywords' => 'palavras texto redação',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Conte caracteres, palavras e parágrafos, e veja quanto tempo leva para ler o texto.',
            'explainer' => <<<'HTML'
<h2>Para que serve</h2>
<p>Redações de vestibular e concursos costumam ter limite de linhas ou caracteres, assim como legendas de redes sociais. O tempo de leitura considera 200 palavras por minuto.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>Qual o limite de caracteres das redes sociais?</summary><p>Muda com frequência. Confira o limite atual da plataforma e use o contador para ajustar o texto.</p></details>
  <details><summary>Espaços contam como caracteres?</summary><p>Na maioria dos limites, sim. A calculadora mostra o total com e sem espaços.</p></details>
</div>
HTML,
        ],
        'maiusculas' => [
            'name' => 'Maiúsculas e minúsculas',
            'seo_title' => 'Converter Texto em Maiúsculas e Minúsculas',
            'symbol' => 'aA',
            'category' => 'texto',
            'description' => 'Converta o texto em um clique.',
            'keywords' => 'caixa alta baixa title case capitalizar primeira letra maiúscula',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Transforme o texto em TUDO MAIÚSCULO, tudo minúsculo, Primeira Letra Maiúscula (title case) ou formato de título.',
            'explainer' => <<<'HTML'
<h2>Quando usar cada formato</h2>
<p>Maiúsculas são comuns em títulos e placas; "Cada Palavra" em nomes próprios e títulos de música; "Início de frase" para corrigir textos digitados com o Caps Lock ligado.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O que é title case?</summary><p>É colocar a primeira letra de cada palavra em maiúscula. No modo &quot;Título&quot;, palavras como &quot;de&quot;, &quot;da&quot; e &quot;e&quot; ficam minúsculas, como em títulos em português.</p></details>
  <details><summary>Os acentos são mantidos?</summary><p>Sim. Letras acentuadas e o ç são convertidos corretamente.</p></details>
</div>
HTML,
        ],
        'link-whatsapp' => [
            'name' => 'Link do WhatsApp com mensagem',
            'seo_title' => 'Gerador de Link do WhatsApp com Mensagem e QR Code',
            'symbol' => 'wa',
            'category' => 'texto',
            'description' => 'Gera link wa.me com mensagem pronta e QR Code.',
            'keywords' => 'link whatsapp wa.me gerador de link mensagem pronta qr code whatsapp business botão anúncio',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Coloque o número e uma mensagem pronta (como "Olá, vi seu anúncio") e gere o link direto para o WhatsApp e o QR Code para baixar.',
            'explainer' => <<<'HTML'
<h2>Como funciona o link do WhatsApp</h2>
<p>O WhatsApp tem um endereço oficial, o <b>wa.me</b>, que abre a conversa com um número sem precisar salvar o contato. A mensagem vai no próprio link, já preenchida, e a pessoa só precisa tocar em enviar.</p>
<p class="formula">https://wa.me/5511912345678?text=Olá%2C%20vi%20seu%20anúncio</p>
<p>O número vai com o código do país e o DDD, só com dígitos: sem +, espaços, parênteses ou traço. A mensagem é codificada para funcionar em qualquer navegador (espaço vira %20, por exemplo).</p>
<h2>Onde usar</h2>
<p>No Instagram (link da bio), em anúncios, no site, em e-mails e no Google Meu Negócio. O QR Code pode ir em cardápios, cartões de visita, panfletos, vitrines e embalagens. Para imprimir, prefira o SVG: ele não perde qualidade em nenhum tamanho.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O link expira?</summary><p>Não. O link wa.me funciona enquanto o número tiver WhatsApp. Se mudar a mensagem, gere um link novo.</p></details>
  <details><summary>Funciona com WhatsApp Business e telefone fixo?</summary><p>Sim. Números fixos com WhatsApp Business funcionam do mesmo jeito: informe o DDD e os 8 dígitos do telefone.</p></details>
  <details><summary>O número que eu digito fica salvo no site?</summary><p>Não. O link e o QR Code são gerados no seu navegador.</p></details>
</div>
HTML,
        ],
        'senha' => [
            'name' => 'Gerador de senha',
            'seo_title' => 'Gerador de Senha Forte e Segura',
            'symbol' => '***',
            'category' => 'texto',
            'description' => 'Senhas fortes e aleatórias.',
            'keywords' => 'segurança password',
            'ready' => true,
            'reviewed' => '2026-09-30',
            'lead' => 'Gere senhas fortes e aleatórias. A senha é criada no seu navegador e não é enviada para lugar nenhum.',
            'explainer' => <<<'HTML'
<h2>O que faz uma senha ser forte</h2>
<p>Tamanho é o que mais importa: uma senha longa com letras, números e símbolos leva muito mais tempo para ser descoberta. Use uma senha diferente para cada site e um gerenciador de senhas para guardar todas.</p>
<p class="notice">Caracteres parecidos, como 0 e O ou 1 e l, foram retirados para evitar confusão.</p>
<h2>Perguntas frequentes</h2>
<div class="faq">
  <details><summary>O que é uma senha forte?</summary><p>Uma senha longa (12 caracteres ou mais) que mistura letras maiúsculas, minúsculas, números e símbolos, sem palavras óbvias.</p></details>
  <details><summary>A senha gerada é enviada para algum lugar?</summary><p>Não. Ela é criada no seu navegador e não sai do seu computador.</p></details>
</div>
HTML,
        ],
    ],
];
