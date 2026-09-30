<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Números do painel de visitas (mesmo modelo do painel do Pote Político).
 */
class VisitStats
{
    public const PERIODS = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '1 ano'];
    public const ONLINE_MINUTES = 2; // visto nos últimos 2 min = online (o navegador avisa a cada 30 s)

    /**
     * Quem está no site agora: total, por página e quantos nos últimos 30 minutos.
     */
    public static function online(): array
    {
        $onlineRows = Database::fetchAll(
            'SELECT page, device FROM visits_online WHERE seen_at >= NOW() - INTERVAL ' . self::ONLINE_MINUTES . ' MINUTE ORDER BY entered_at LIMIT 500'
        );
        $lastHalfHour = (int) Database::fetchValue('SELECT COUNT(*) FROM visits_online WHERE seen_at >= NOW() - INTERVAL 30 MINUTE');

        $pages = [];
        foreach ($onlineRows as $row) {
            $pages[$row['page']] = ($pages[$row['page']] ?? 0) + 1;
        }
        arsort($pages);

        return ['now' => count($onlineRows), 'half_hour' => $lastHalfHour, 'pages' => $pages, 'time' => date('H:i:s')];
    }

    /**
     * Visitantes únicos, páginas vistas e visitantes novos entre duas datas.
     */
    public static function summary(string $from, string $until): array
    {
        $row = Database::fetchOne(
            'SELECT COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS page_views, COUNT(DISTINCT IF(is_new = 1, visitor, NULL)) AS new_visitors
             FROM visits WHERE created_at >= :from AND created_at < :until',
            ['from' => $from, 'until' => $until]
        );

        return ['visitors' => (int) $row['visitors'], 'page_views' => (int) $row['page_views'], 'new_visitors' => (int) $row['new_visitors']];
    }

    public static function percentChange(int|float $current, int|float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /**
     * Tudo o que a página de visitas mostra, para o período escolhido (em dias).
     */
    public static function dashboard(int $days): array
    {
        $now = date('Y-m-d H:i:s');
        $inAMinute = date('Y-m-d H:i:s', time() + 60);
        $todayStart = date('Y-m-d 00:00:00');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdaySameTime = date('Y-m-d H:i:s', strtotime('-1 day'));
        $periodStart = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $previousPeriodStart = date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));

        // Hoje × ontem até a mesma hora (comparação justa no meio do dia)
        $today = self::summary($todayStart, $inAMinute);
        $yesterdayUntilNow = self::summary($yesterdayStart, $yesterdaySameTime);
        $yesterday = self::summary($yesterdayStart, $todayStart);
        $period = self::summary($periodStart, $inAMinute);
        $previousPeriod = self::summary($previousPeriodStart, $periodStart);

        // Por dia (dias sem visita entram com zero)
        $byDay = [];
        for ($daysAgo = $days - 1; $daysAgo >= 0; $daysAgo--) {
            $byDay[date('Y-m-d', strtotime("-{$daysAgo} days"))] = ['visitors' => 0, 'page_views' => 0, 'new_visitors' => 0];
        }
        $dailyRows = Database::fetchAll(
            'SELECT DATE(created_at) AS day, COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS page_views, COUNT(DISTINCT IF(is_new = 1, visitor, NULL)) AS new_visitors
             FROM visits WHERE created_at >= :from GROUP BY day',
            ['from' => $periodStart]
        );
        foreach ($dailyRows as $row) {
            if (isset($byDay[$row['day']])) {
                $byDay[$row['day']] = ['visitors' => (int) $row['visitors'], 'page_views' => (int) $row['page_views'], 'new_visitors' => (int) $row['new_visitors']];
            }
        }

        // Últimos 12 meses (visitantes únicos no mês) + projeção do mês atual
        $months = [];
        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $months[date('Y-m', strtotime(date('Y-m-01') . " -{$monthsAgo} months"))] = 0;
        }
        $monthlyRows = Database::fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE created_at >= :from GROUP BY month",
            ['from' => array_key_first($months) . '-01 00:00:00']
        );
        foreach ($monthlyRows as $row) {
            if (isset($months[$row['month']])) {
                $months[$row['month']] = (int) $row['visitors'];
            }
        }
        $daysElapsedInMonth = (int) date('j') - 1 + ((int) date('G') * 60 + (int) date('i')) / 1440;
        $monthProjection = $daysElapsedInMonth > 0.5 ? (int) round(end($months) / $daysElapsedInMonth * (int) date('t')) : null;

        // Por hora: hoje × ontem
        $hours = ['today' => array_fill(0, 24, 0), 'yesterday' => array_fill(0, 24, 0)];
        $hourlyRows = Database::fetchAll(
            'SELECT DATE(created_at) = CURDATE() AS is_today, HOUR(created_at) AS hour, COUNT(DISTINCT visitor) AS visitors
             FROM visits WHERE created_at >= :from GROUP BY is_today, hour',
            ['from' => $yesterdayStart]
        );
        foreach ($hourlyRows as $row) {
            $hours[$row['is_today'] ? 'today' : 'yesterday'][(int) $row['hour']] = (int) $row['visitors'];
        }

        // Mapa de calor: dia da semana × hora (mínimo de 4 semanas para ter padrão)
        $heatmapDays = max($days, 28);
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        $heatRows = Database::fetchAll(
            'SELECT DAYOFWEEK(created_at) - 1 AS weekday, HOUR(created_at) AS hour, COUNT(*) AS page_views FROM visits WHERE created_at >= :from GROUP BY weekday, hour',
            ['from' => date('Y-m-d 00:00:00', strtotime('-' . ($heatmapDays - 1) . ' days'))]
        );
        foreach ($heatRows as $row) {
            $heatmap[(int) $row['weekday']][(int) $row['hour']] = (int) $row['page_views'];
        }

        return [
            'today' => $today,
            'yesterday_until_now' => $yesterdayUntilNow,
            'yesterday' => $yesterday,
            'period' => $period,
            'previous_period' => $previousPeriod,
            'returning' => max(0, $period['visitors'] - $period['new_visitors']),
            'by_day' => $byDay,
            'trend' => self::weeklyTrend(array_column(array_slice(array_values($byDay), 0, -1), 'visitors')),
            'months' => $months,
            'month_projection' => $monthProjection,
            'hours' => $hours,
            'hour_now' => (int) date('G'),
            'heatmap' => $heatmap,
            'heatmap_days' => $heatmapDays,
            'pages' => Database::fetchPairs('SELECT page, COUNT(*) AS total FROM visits WHERE created_at >= :from GROUP BY page ORDER BY total DESC LIMIT 10', ['from' => $periodStart]),
            'sources' => Database::fetchPairs("SELECT source, COUNT(DISTINCT visitor) AS total FROM visits WHERE created_at >= :from AND source <> 'interno' GROUP BY source ORDER BY total DESC LIMIT 10", ['from' => $periodStart]),
            'devices' => Database::fetchPairs('SELECT device, COUNT(DISTINCT visitor) AS total FROM visits WHERE created_at >= :from GROUP BY device ORDER BY total DESC', ['from' => $periodStart]),
            'first_visit' => Database::fetchValue('SELECT MIN(created_at) FROM visits'),
            'now' => $now,
        ];
    }

    /**
     * Tendência: reta dos mínimos quadrados sobre os visitantes por dia, em % por semana.
     */
    public static function weeklyTrend(array $dailyVisitors): ?float
    {
        $count = count($dailyVisitors);
        $total = array_sum($dailyVisitors);
        if ($count < 5 || $total === 0) {
            return null;
        }
        $meanX = ($count - 1) / 2;
        $meanY = $total / $count;
        $numerator = 0;
        $denominator = 0;
        foreach ($dailyVisitors as $dayIndex => $visitors) {
            $numerator += ($dayIndex - $meanX) * ($visitors - $meanY);
            $denominator += ($dayIndex - $meanX) ** 2;
        }

        return round(($numerator / $denominator) * 7 / $meanY * 100, 1);
    }

    /**
     * Uso de cada calculadora: últimos 30 dias × 30 dias anteriores.
     */
    public static function toolUsage(): array
    {
        $currentStart = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $previousStart = date('Y-m-d 00:00:00', strtotime('-59 days'));
        $current = Database::fetchPairs('SELECT tool_id, COUNT(*) FROM tool_uses WHERE created_at >= :from GROUP BY tool_id', ['from' => $currentStart]);
        $previous = Database::fetchPairs(
            'SELECT tool_id, COUNT(*) FROM tool_uses WHERE created_at >= :from AND created_at < :until GROUP BY tool_id',
            ['from' => $previousStart, 'until' => $currentStart]
        );

        return ['current' => $current, 'previous' => $previous];
    }
}
