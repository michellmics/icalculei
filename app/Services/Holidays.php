<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Calendário de feriados de qualquer ano: nacionais, pontos facultativos, estaduais e da capital
 * (dados em content/holidays.php). As datas móveis saem da Páscoa.
 */
class Holidays
{
    public const MIN_YEAR = 2000;
    public const MAX_YEAR = 2100;
    private const WEEKDAYS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    // Endereço das páginas de cada mês: /feriados/2026/novembro
    public const MONTH_SLUGS = [1 => 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    public const MONTH_NAMES = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    private static ?array $data = null;

    private static function data(): array
    {
        return self::$data ??= require BASE_PATH . '/content/holidays.php';
    }

    public static function states(): array
    {
        return self::data()['states'];
    }

    public static function reviewed(): string
    {
        return self::data()['reviewed'];
    }

    /**
     * Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher, calendário gregoriano).
     */
    public static function easter(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private static function resolveDate(int $year, string|array $date): DateTimeImmutable
    {
        if (is_array($date)) {
            return self::easter($year)->modify(sprintf('%+d days', $date['easter']));
        }

        return new DateTimeImmutable("{$year}-{$date}");
    }

    /**
     * Feriados do ano, em ordem de data. Com $stateCode, inclui os do estado e da capital.
     * Cada item: date (Y-m-d), weekday, names (lista: no mesmo dia pode haver mais de um), type
     * ("nacional", "facultativo", "estadual", "municipal"), isWorkday (cai de segunda a sexta), bridge.
     */
    public static function forYear(int $year, ?string $stateCode = null): array
    {
        $data = self::data();
        $entries = [];
        $add = function (DateTimeImmutable $date, string $name, string $type) use (&$entries) {
            $key = $date->format('Y-m-d');
            // Mesmo dia com mais de um motivo: junta os nomes e fica o tipo "mais forte"
            $priority = ['nacional' => 4, 'estadual' => 3, 'municipal' => 2, 'facultativo' => 1];
            if (isset($entries[$key])) {
                if (!in_array($name, $entries[$key]['names'], true)) {
                    $entries[$key]['names'][] = $name;
                }
                if ($priority[$type] > $priority[$entries[$key]['type']]) {
                    $entries[$key]['type'] = $type;
                }
                return;
            }
            $entries[$key] = ['date' => $key, 'names' => [$name], 'type' => $type];
        };

        foreach ($data['national'] as $holiday) {
            $add(self::resolveDate($year, $holiday['date']), $holiday['name'], 'nacional');
        }
        foreach ($data['optional'] as $holiday) {
            $add(self::resolveDate($year, $holiday['date']), $holiday['name'], 'facultativo');
        }
        if ($stateCode !== null && isset($data['states'][$stateCode])) {
            $state = $data['states'][$stateCode];
            foreach ($state['state'] as [$date, $name]) {
                $add(self::resolveDate($year, $date), $name . ' (' . $state['name'] . ')', 'estadual');
            }
            foreach ($state['city'] as [$date, $name]) {
                $add(self::resolveDate($year, $date), $name . ' (' . $state['capital'] . ')', 'municipal');
            }
        }
        ksort($entries);

        return array_map(function (array $entry) {
            $date = new DateTimeImmutable($entry['date']);
            $weekdayNumber = (int) $date->format('w');
            $entry['weekday'] = self::WEEKDAYS[$weekdayNumber];
            $entry['isWorkday'] = $weekdayNumber >= 1 && $weekdayNumber <= 5;
            $entry['bridge'] = self::bridgeText($weekdayNumber, $entry['type']);

            return $entry;
        }, array_values($entries));
    }

    /**
     * Feriados estaduais e das capitais de todos os estados num mês (para as páginas de cada mês).
     * Datas que já são feriado nacional ficam de fora. Cada item: date, weekday, name, place, stateCode, type.
     */
    public static function localForMonth(int $year, int $month): array
    {
        $nationalDates = array_column(array_filter(self::forYear($year), fn (array $holiday) => $holiday['type'] === 'nacional'), 'date');
        $entries = [];
        foreach (self::states() as $stateCode => $state) {
            $places = [['estadual', $state['state'], $state['name']], ['municipal', $state['city'], $state['capital'] . ' (' . strtoupper($stateCode) . ')']];
            foreach ($places as [$type, $list, $place]) {
                foreach ($list as [$date, $name]) {
                    $resolved = self::resolveDate($year, $date);
                    $isoDate = $resolved->format('Y-m-d');
                    if ((int) $resolved->format('n') !== $month || in_array($isoDate, $nationalDates, true)) {
                        continue;
                    }
                    $entries[] = ['date' => $isoDate, 'weekday' => self::WEEKDAYS[(int) $resolved->format('w')], 'name' => $name,
                        'place' => $place, 'stateCode' => $stateCode, 'type' => $type];
                }
            }
        }
        usort($entries, fn (array $first, array $second) => [$first['date'], $first['place']] <=> [$second['date'], $second['place']]);

        return $entries;
    }

    /**
     * Feriadão: segunda ou sexta dão 3 dias seguidos; terça ou quinta permitem "emendar" e ter 4 dias.
     */
    private static function bridgeText(int $weekdayNumber, string $type): string
    {
        if ($type === 'facultativo') {
            return '';
        }

        return match ($weekdayNumber) {
            1, 5 => 'feriadão de 3 dias',
            2 => 'emenda com a segunda: 4 dias',
            4 => 'emenda com a sexta: 4 dias',
            default => '',
        };
    }

    /**
     * Próximo feriado (não facultativo) a partir de hoje: ['holiday' => ..., 'daysUntil' => N] ou null.
     */
    public static function next(?string $stateCode = null, ?DateTimeImmutable $today = null): ?array
    {
        $today ??= new DateTimeImmutable('today');
        foreach ([(int) $today->format('Y'), (int) $today->format('Y') + 1] as $year) {
            foreach (self::forYear($year, $stateCode) as $holiday) {
                if ($holiday['type'] !== 'facultativo' && $holiday['date'] >= $today->format('Y-m-d')) {
                    return ['holiday' => $holiday, 'daysUntil' => (int) $today->diff(new DateTimeImmutable($holiday['date']))->days];
                }
            }
        }

        return null;
    }

    /**
     * Dias úteis de cada mês (segunda a sexta, sem os feriados; pontos facultativos contam como úteis).
     */
    public static function workdaysByMonth(int $year, array $holidays): array
    {
        $holidayDates = array_column(array_filter($holidays, fn (array $holiday) => $holiday['type'] !== 'facultativo'), 'date');
        $workdays = array_fill(1, 12, 0);
        for ($date = new DateTimeImmutable("{$year}-01-01"); (int) $date->format('Y') === $year; $date = $date->modify('+1 day')) {
            $weekdayNumber = (int) $date->format('w');
            if ($weekdayNumber >= 1 && $weekdayNumber <= 5 && !in_array($date->format('Y-m-d'), $holidayDates, true)) {
                $workdays[(int) $date->format('n')]++;
            }
        }

        return $workdays;
    }
}
