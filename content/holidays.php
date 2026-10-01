<?php

declare(strict_types=1);

/**
 * FERIADOS DO BRASIL (página /feriados/{ano} e /feriados/{ano}/{uf})
 *
 * Datas no formato "mm-dd" (todo ano) ou ['easter' => N] (N dias depois do Domingo de Páscoa;
 * negativo = antes). O ano é calculado sozinho, então a página vale para qualquer ano.
 *
 * Nacionais: leis federais 662/1949, 6.802/1980, 10.607/2002 e 14.759/2023 (Consciência Negra).
 * Estaduais e das capitais: levantamento da CNC divulgado em 2026, conferido e corrigido
 * (São Paulo não tem feriado em 8/12; Nossa Senhora da Penha/ES é 8 dias após a Páscoa).
 * "in": preposição do estado (no Rio de Janeiro, na Bahia, em São Paulo).
 * Ao revisar, atualize "reviewed". Leis estaduais e municipais mudam: na dúvida, confirme na fonte oficial.
 */

return [
    'reviewed' => '2026-09-30',

    // Feriados nacionais (lei federal): valem no país inteiro
    'national' => [
        ['date' => '01-01', 'name' => 'Confraternização Universal'],
        ['date' => ['easter' => -2], 'name' => 'Sexta-feira Santa (Paixão de Cristo)'],
        ['date' => '04-21', 'name' => 'Tiradentes'],
        ['date' => '05-01', 'name' => 'Dia do Trabalho'],
        ['date' => '09-07', 'name' => 'Independência do Brasil'],
        ['date' => '10-12', 'name' => 'Nossa Senhora Aparecida'],
        ['date' => '11-02', 'name' => 'Finados'],
        ['date' => '11-15', 'name' => 'Proclamação da República'],
        ['date' => '11-20', 'name' => 'Dia Nacional de Zumbi e da Consciência Negra'],
        ['date' => '12-25', 'name' => 'Natal'],
    ],

    // Pontos facultativos nacionais: cada empresa, estado ou cidade decide se folga
    'optional' => [
        ['date' => ['easter' => -48], 'name' => 'Carnaval (segunda-feira)'],
        ['date' => ['easter' => -47], 'name' => 'Carnaval (terça-feira)'],
        ['date' => ['easter' => -46], 'name' => 'Quarta-feira de Cinzas (até as 14h)'],
        ['date' => ['easter' => 60], 'name' => 'Corpus Christi (feriado em várias cidades)'],
    ],

    // Feriados estaduais e da capital de cada estado
    'states' => [
        'ac' => ['in' => 'no', 'name' => 'Acre', 'capital' => 'Rio Branco',
            'state' => [['01-20', 'Dia do Católico'], ['01-23', 'Dia do Evangélico'], ['03-08', 'Dia Internacional da Mulher'], ['06-15', 'Aniversário do Acre'], ['09-05', 'Dia da Amazônia'], ['11-17', 'Tratado de Petrópolis']],
            'city' => [['12-28', 'Aniversário de Rio Branco']]],
        'al' => ['in' => 'em', 'name' => 'Alagoas', 'capital' => 'Maceió',
            'state' => [['06-24', 'São João'], ['06-29', 'São Pedro'], ['09-16', 'Emancipação Política de Alagoas'], ['11-30', 'Dia do Evangélico']],
            'city' => [['08-27', 'Nossa Senhora dos Prazeres'], ['12-08', 'Nossa Senhora da Conceição']]],
        'ap' => ['in' => 'no', 'name' => 'Amapá', 'capital' => 'Macapá',
            'state' => [['03-19', 'São José'], ['05-15', 'Dia de Cabralzinho'], ['09-13', 'Criação do Território do Amapá']],
            'city' => [['02-04', 'Aniversário de Macapá']]],
        'am' => ['in' => 'no', 'name' => 'Amazonas', 'capital' => 'Manaus',
            'state' => [['09-05', 'Elevação do Amazonas à categoria de província']],
            'city' => [['10-24', 'Aniversário de Manaus'], ['12-08', 'Nossa Senhora da Conceição']]],
        'ba' => ['in' => 'na', 'name' => 'Bahia', 'capital' => 'Salvador',
            'state' => [['07-02', 'Independência da Bahia']],
            'city' => [['06-24', 'São João'], ['12-08', 'Nossa Senhora da Conceição da Praia']]],
        'ce' => ['in' => 'no', 'name' => 'Ceará', 'capital' => 'Fortaleza',
            'state' => [['03-19', 'São José'], ['03-25', 'Data Magna do Ceará (abolição da escravidão)']],
            'city' => [['08-15', 'Nossa Senhora da Assunção']]],
        'df' => ['in' => 'no', 'name' => 'Distrito Federal', 'capital' => 'Brasília',
            'state' => [['04-21', 'Fundação de Brasília'], ['11-30', 'Dia do Evangélico']],
            'city' => []],
        'es' => ['in' => 'no', 'name' => 'Espírito Santo', 'capital' => 'Vitória',
            'state' => [[['easter' => 8], 'Nossa Senhora da Penha']],
            'city' => [['09-08', 'Aniversário de Vitória e Nossa Senhora da Vitória']]],
        'go' => ['in' => 'em', 'name' => 'Goiás', 'capital' => 'Goiânia',
            'state' => [],
            'city' => [['05-24', 'Nossa Senhora Auxiliadora'], ['10-24', 'Aniversário de Goiânia']]],
        'ma' => ['in' => 'no', 'name' => 'Maranhão', 'capital' => 'São Luís',
            'state' => [['07-28', 'Adesão do Maranhão à Independência']],
            'city' => [['06-29', 'São Pedro'], ['09-08', 'Aniversário de São Luís e Natividade de Nossa Senhora'], ['12-08', 'Nossa Senhora da Conceição']]],
        'mt' => ['in' => 'em', 'name' => 'Mato Grosso', 'capital' => 'Cuiabá',
            'state' => [],
            'city' => [['04-08', 'Aniversário de Cuiabá'], ['12-08', 'Nossa Senhora da Conceição']]],
        'ms' => ['in' => 'em', 'name' => 'Mato Grosso do Sul', 'capital' => 'Campo Grande',
            'state' => [['10-11', 'Criação do Estado de Mato Grosso do Sul']],
            'city' => []],
        'mg' => ['in' => 'em', 'name' => 'Minas Gerais', 'capital' => 'Belo Horizonte',
            'state' => [],
            'city' => [['08-15', 'Nossa Senhora da Boa Viagem'], ['12-08', 'Nossa Senhora da Conceição']]],
        'pa' => ['in' => 'no', 'name' => 'Pará', 'capital' => 'Belém',
            'state' => [['08-15', 'Adesão do Pará à Independência']],
            'city' => [['12-08', 'Nossa Senhora da Conceição']]],
        'pb' => ['in' => 'na', 'name' => 'Paraíba', 'capital' => 'João Pessoa',
            'state' => [['08-05', 'Fundação do Estado da Paraíba']],
            'city' => [['06-24', 'São João'], ['08-05', 'Nossa Senhora das Neves e aniversário de João Pessoa'], ['12-08', 'Nossa Senhora da Conceição']]],
        'pr' => ['in' => 'no', 'name' => 'Paraná', 'capital' => 'Curitiba',
            'state' => [],
            'city' => [['09-08', 'Nossa Senhora da Luz dos Pinhais']]],
        'pe' => ['in' => 'em', 'name' => 'Pernambuco', 'capital' => 'Recife',
            'state' => [['03-06', 'Revolução Pernambucana de 1817']],
            'city' => [['06-24', 'São João'], ['07-16', 'Nossa Senhora do Carmo'], ['12-08', 'Nossa Senhora da Conceição']]],
        'pi' => ['in' => 'no', 'name' => 'Piauí', 'capital' => 'Teresina',
            'state' => [['10-19', 'Dia do Piauí']],
            'city' => [['08-16', 'Aniversário de Teresina'], ['12-08', 'Nossa Senhora da Conceição']]],
        'rj' => ['in' => 'no', 'name' => 'Rio de Janeiro', 'capital' => 'Rio de Janeiro',
            'state' => [['04-23', 'São Jorge']],
            'city' => [['01-20', 'São Sebastião']]],
        'rn' => ['in' => 'no', 'name' => 'Rio Grande do Norte', 'capital' => 'Natal',
            'state' => [['10-03', 'Mártires de Cunhaú e Uruaçu']],
            'city' => [['01-06', 'Santos Reis'], ['11-21', 'Nossa Senhora da Apresentação']]],
        'rs' => ['in' => 'no', 'name' => 'Rio Grande do Sul', 'capital' => 'Porto Alegre',
            'state' => [['09-20', 'Revolução Farroupilha (Dia do Gaúcho)']],
            'city' => [['02-02', 'Nossa Senhora dos Navegantes']]],
        'ro' => ['in' => 'em', 'name' => 'Rondônia', 'capital' => 'Porto Velho',
            'state' => [['01-04', 'Criação do Estado de Rondônia']],
            'city' => [['01-24', 'Instalação de Porto Velho'], ['05-24', 'Nossa Senhora Auxiliadora'], ['10-02', 'Criação do município de Porto Velho']]],
        'rr' => ['in' => 'em', 'name' => 'Roraima', 'capital' => 'Boa Vista',
            'state' => [['10-05', 'Criação do Estado de Roraima']],
            'city' => [['01-20', 'São Sebastião'], ['06-29', 'São Pedro'], ['07-09', 'Aniversário de Boa Vista']]],
        'sc' => ['in' => 'em', 'name' => 'Santa Catarina', 'capital' => 'Florianópolis',
            'state' => [],
            'city' => [['03-23', 'Aniversário de Florianópolis']]],
        'sp' => ['in' => 'em', 'name' => 'São Paulo', 'capital' => 'São Paulo',
            'state' => [['07-09', 'Revolução Constitucionalista de 1932']],
            'city' => [['01-25', 'Aniversário de São Paulo']]],
        'se' => ['in' => 'em', 'name' => 'Sergipe', 'capital' => 'Aracaju',
            'state' => [['07-08', 'Emancipação Política de Sergipe']],
            'city' => [['03-17', 'Aniversário de Aracaju'], ['06-24', 'São João'], ['12-08', 'Nossa Senhora da Conceição']]],
        'to' => ['in' => 'no', 'name' => 'Tocantins', 'capital' => 'Palmas',
            'state' => [['09-08', 'Nossa Senhora da Natividade'], ['10-05', 'Criação do Estado do Tocantins']],
            'city' => [['03-19', 'São José'], ['05-20', 'Aniversário de Palmas']]],
    ],
];
