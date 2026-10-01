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
 * Índices econômicos da página inicial, do Banco Central (SGS, API pública e gratuita):
 *   IPCA (série 433) e IGP-M (série 189): variação do último mês e acumulado de 12 meses
 *   Selic: meta atual (série 432) e quanto rendeu em 12 meses (série 11, taxa diária)
 * Os índices saem uma vez por mês: o resultado fica 6 horas em cache (storage/cache/indicators).
 */
class EconomicIndicators
{
    private const SGS_URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados?formato=json&dataInicial=%s&dataFinal=%s';
    private const CACHE_SECONDS = 6 * 3600;
    private const MONTH_NAMES = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public static function all(): array
    {
        $cache = new FileCache('indicators');
        $cached = $cache->get('home', self::CACHE_SECONDS);
        if ($cached !== null) {
            return $cached;
        }

        $indicators = [];
        foreach (['ipca' => 433, 'igpm' => 189] as $key => $seriesCode) {
            try {
                $indicators[$key] = self::monthlyIndex($seriesCode);
            } catch (Throwable $exception) {
                ErrorHandler::log("[índices] {$key}: " . $exception->getMessage());
                $indicators[$key] = null;
            }
        }
        try {
            $indicators['selic'] = self::selic();
        } catch (Throwable $exception) {
            ErrorHandler::log('[índices] selic: ' . $exception->getMessage());
            $indicators['selic'] = null;
        }
        $indicators['updatedAt'] = date('c');

        // Só guarda se veio pelo menos um índice. Se o Banco Central não respondeu nada,
        // usa o último resultado guardado, mesmo que antigo (melhor que deixar o quadro vazio)
        if (array_filter([$indicators['ipca'], $indicators['igpm'], $indicators['selic']])) {
            $cache->set('home', $indicators);

            return $indicators;
        }

        return $cache->get('home') ?? $indicators;
    }

    /**
     * Valores de uma série do SGS entre duas datas: [['date' => DateTimeImmutable, 'value' => float], ...].
     */
    private static function series(int $seriesCode, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $url = sprintf(self::SGS_URL, $seriesCode, $from->format('d/m/Y'), $to->format('d/m/Y'));
        [$status, $body] = HttpClient::request('GET', $url, ['Accept: application/json'], null, 15);
        $rows = json_decode($body, true);
        if ($status !== 200 || !is_array($rows) || isset($rows['erro'])) {
            throw new RuntimeException("SGS {$seriesCode} respondeu {$status}");
        }

        return array_map(fn (array $row) => [
            'date' => DateTimeImmutable::createFromFormat('!d/m/Y', $row['data']),
            'value' => (float) $row['valor'],
        ], $rows);
    }

    /**
     * Juros compostos de uma lista de taxas em %: (1 + t1) × (1 + t2) × ... − 1, em %.
     */
    private static function compound(array $percentRates): float
    {
        return (array_product(array_map(fn (float $rate) => 1 + $rate / 100, $percentRates)) - 1) * 100;
    }

    /**
     * Índice mensal (IPCA, IGP-M): último mês divulgado e acumulado dos últimos 12 meses.
     */
    private static function monthlyIndex(int $seriesCode): array
    {
        $today = new DateTimeImmutable('today');
        $rows = self::series($seriesCode, $today->modify('first day of -15 months'), $today);
        if (count($rows) < 12) {
            throw new RuntimeException('menos de 12 meses na resposta');
        }
        $lastTwelve = array_slice($rows, -12);
        $last = end($lastTwelve);

        return [
            'month' => self::MONTH_NAMES[(int) $last['date']->format('n')] . '/' . $last['date']->format('Y'),
            'monthly' => round($last['value'], 2),
            'twelveMonths' => round(self::compound(array_column($lastTwelve, 'value')), 2),
        ];
    }

    /**
     * Selic: meta definida pelo Copom que vale hoje e quanto ela rendeu nos últimos 12 meses.
     */
    private static function selic(): array
    {
        $today = new DateTimeImmutable('today');
        // A série da meta já traz os dias futuros até a próxima reunião: vale a última até hoje
        $targets = array_filter(self::series(432, $today->modify('-60 days'), $today), fn (array $row) => $row['date'] <= $today);
        $target = end($targets);
        $daily = self::series(11, $today->modify('-1 year'), $today);
        if ($target === false || $daily === []) {
            throw new RuntimeException('sem dados da Selic');
        }

        return [
            'target' => round($target['value'], 2),
            'twelveMonths' => round(self::compound(array_column($daily, 'value')), 2),
        ];
    }
}
