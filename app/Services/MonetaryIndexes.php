<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Core\FileCache;
use App\Core\HttpClient;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Séries mensais de índices para a calculadora de correção monetária, do Banco Central (SGS, gratuito).
 * Cada série vem inteira (desde 1995) e fica 12 horas em cache; se o Banco Central não responder,
 * usa a última versão guardada.
 */
class MonetaryIndexes
{
    // Código da série no SGS e textos da calculadora
    public const INDEXES = [
        'ipca' => ['series' => 433, 'name' => 'IPCA', 'description' => 'inflação oficial (IBGE)'],
        'igpm' => ['series' => 189, 'name' => 'IGP-M', 'description' => 'reajuste de aluguel (FGV)'],
        'inpc' => ['series' => 188, 'name' => 'INPC', 'description' => 'salários e benefícios do INSS (IBGE)'],
        'igpdi' => ['series' => 190, 'name' => 'IGP-DI', 'description' => 'contratos e tarifas (FGV)'],
        'selic' => ['series' => 4390, 'name' => 'Selic', 'description' => 'juros básicos acumulados no mês'],
        'cdi' => ['series' => 4391, 'name' => 'CDI', 'description' => 'rendimento de referência da renda fixa'],
        'poupanca' => ['series' => 195, 'name' => 'Poupança', 'description' => 'rendimento da poupança (aniversário no dia 1º)'],
    ];
    private const SGS_URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados?formato=json&dataInicial=%s&dataFinal=%s';
    private const CACHE_SECONDS = 12 * 3600;
    private const HISTORY_START = '1995-01-01';
    // A poupança vem dia a dia (um valor por dia de aniversário): busca a partir da regra atual, em pedaços de 2 anos
    private const SAVINGS_START = '2012-05-01';

    /**
     * Série mensal: ['name' => ..., 'description' => ..., 'months' => [['2024-01', 0.42], ...]] (variação em %).
     */
    public static function monthly(string $indexKey): array
    {
        if (!isset(self::INDEXES[$indexKey])) {
            throw new RuntimeException('Índice desconhecido');
        }
        $cache = new FileCache('indexes');
        $cached = $cache->get($indexKey, self::CACHE_SECONDS);
        if ($cached !== null) {
            return $cached;
        }

        $index = self::INDEXES[$indexKey];
        $stale = $cache->get($indexKey); // versão antiga guardada (pode não existir)
        try {
            if ($stale !== null) {
                // Atualização rápida: só os meses a partir do último guardado (uma consulta pequena)
                $lastMonth = end($stale['months'])[0];
                $from = (new DateTimeImmutable($lastMonth . '-01'))->modify('-1 month');
                $recent = self::seriesMonths($index['series'], $from, new DateTimeImmutable('today'), $indexKey === 'poupanca');
                $merged = array_column($stale['months'], 1, 0);
                foreach ($recent as [$month, $rate]) {
                    $merged[$month] = $rate;
                }
                ksort($merged);
                $months = array_map(fn (string $month, float $rate) => [$month, $rate], array_keys($merged), $merged);
            } else {
                $months = $indexKey === 'poupanca' ? self::savingsMonths() : self::seriesMonths($index['series'], new DateTimeImmutable(self::HISTORY_START), new DateTimeImmutable('today'));
            }
            if (count($months) < 12) {
                throw new RuntimeException('série incompleta');
            }
        } catch (Throwable $exception) {
            ErrorHandler::log("[correção] {$indexKey}: " . $exception->getMessage());
            if ($stale !== null) { // usa a última versão guardada, mesmo antiga
                return $stale;
            }
            throw new RuntimeException('Banco Central indisponível');
        }

        $result = ['name' => $index['name'], 'description' => $index['description'], 'months' => $months];
        $cache->set($indexKey, $result);

        return $result;
    }

    /**
     * [['2024-01', 0.42], ...] de uma série mensal do SGS.
     */
    private static function seriesMonths(int $seriesCode, DateTimeImmutable $from, DateTimeImmutable $to, bool $onlyFirstDay = false): array
    {
        $url = sprintf(self::SGS_URL, $seriesCode, $from->format('d/m/Y'), $to->format('d/m/Y'));
        [$status, $body] = HttpClient::request('GET', $url, ['Accept: application/json'], null, 30);
        $rows = json_decode($body, true);
        if ($status !== 200 || !is_array($rows) || isset($rows['erro'])) {
            throw new RuntimeException("SGS {$seriesCode} respondeu {$status}");
        }
        $months = [];
        foreach ($rows as $row) {
            [$day, $month, $year] = explode('/', $row['data']);
            if ($onlyFirstDay && $day !== '01') {
                continue;
            }
            $months["{$year}-{$month}"] = round((float) $row['valor'], 4);
        }

        return array_map(fn (string $month, float $rate) => [$month, $rate], array_keys($months), $months);
    }

    /**
     * Poupança: rendimento de quem tem aniversário no dia 1º de cada mês, em pedaços de 2 anos.
     */
    private static function savingsMonths(): array
    {
        $months = [];
        $today = new DateTimeImmutable('today');
        for ($from = new DateTimeImmutable(self::SAVINGS_START); $from <= $today; $from = $from->modify('+2 years')) {
            $to = min($from->modify('+2 years -1 day'), $today);
            $months = array_merge($months, self::seriesMonths(self::INDEXES['poupanca']['series'], $from, $to, true));
        }

        return $months;
    }
}
