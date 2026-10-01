<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\View;
use App\Services\Content;
use App\Services\Holidays;
use App\Services\StructuredData;

/**
 * Calendário de feriados:
 *   /feriados                       vai para o ano atual
 *   /feriados/2026                  nacionais e pontos facultativos
 *   /feriados/2026/sp               + feriados do estado e da capital
 *   /feriados/2026/novembro         feriados do mês (nacionais e de todos os estados e capitais)
 *   /feriados/2026/agenda           arquivo .ics (Google Agenda, iPhone, Outlook); /feriados/2026/sp/agenda com o estado
 */
class HolidayController
{
    public function index(): void
    {
        Http::redirect('/feriados/' . date('Y'));
    }

    public function year(string $year): void
    {
        $this->show($year, null);
    }

    public function state(string $year, string $stateCode): void
    {
        // /feriados/2026/novembro usa a mesma rota dos estados (/feriados/2026/sp)
        $month = array_search($stateCode, Holidays::MONTH_SLUGS, true);
        if ($month !== false) {
            $this->showMonth($year, $month);
            return;
        }
        $this->show($year, $stateCode);
    }

    public function agenda(string $year): void
    {
        $this->sendAgenda($year, null);
    }

    public function stateAgenda(string $year, string $stateCode): void
    {
        $this->sendAgenda($year, $stateCode);
    }

    /**
     * Ano e estado válidos, ou a página de "não encontrado".
     */
    private function validate(string $year, ?string $stateCode): ?int
    {
        $yearNumber = (int) $year;
        $validYear = ctype_digit($year) && $yearNumber >= Holidays::MIN_YEAR && $yearNumber <= Holidays::MAX_YEAR;
        $validState = $stateCode === null || isset(Holidays::states()[$stateCode]);
        if (!$validYear || !$validState) {
            (new SiteController())->notFound();
            return null;
        }

        return $yearNumber;
    }

    private function show(string $year, ?string $stateCode): void
    {
        $yearNumber = $this->validate($year, $stateCode);
        if ($yearNumber === null) {
            return;
        }
        $states = Holidays::states();
        $state = $stateCode !== null ? $states[$stateCode] : null;
        $holidays = Holidays::forYear($yearNumber, $stateCode);
        $realHolidays = array_filter($holidays, fn (array $holiday) => $holiday['type'] !== 'facultativo');
        $currentYear = (int) date('Y');
        $place = $state ? $state['name'] . ' (' . strtoupper($stateCode) . ')' : '';

        http_response_code(200);
        header('Cache-Control: public, max-age=0, s-maxage=600');
        echo View::render('site/holidays', [
            'ogImage' => share_banner('feriados'),
            'pageTitle' => $state
                ? "Feriados {$yearNumber} {$state['in']} {$place}: Estaduais e da Capital | Vibe2000"
                : "Feriados {$yearNumber}: Calendário Completo dos Feriados Nacionais | Vibe2000",
            'metaDescription' => $state
                ? "Todos os feriados de {$yearNumber} {$state['in']} {$state['name']}: nacionais, estaduais e da capital {$state['capital']}, com dia da semana, feriadões e calendário para baixar."
                : "Todos os feriados nacionais e pontos facultativos de {$yearNumber}, com dia da semana, feriadões, dias úteis de cada mês e calendário para baixar.",
            'canonicalPath' => '/feriados/' . $yearNumber . ($stateCode ? '/' . $stateCode : ''),
            'pageKey' => 'feriados',
            'categories' => Content::categories(),
            'searchTerm' => '',
            'activeCategory' => null,
            'year' => $yearNumber,
            'stateCode' => $stateCode,
            'state' => $state,
            'states' => $states,
            'holidays' => $holidays,
            'workdays' => Holidays::workdaysByMonth($yearNumber, $holidays),
            'realHolidayCount' => count($realHolidays),
            'onWorkdays' => count(array_filter($realHolidays, fn (array $holiday) => $holiday['isWorkday'])),
            'longWeekends' => count(array_filter($realHolidays, fn (array $holiday) => $holiday['bridge'] !== '')),
            'next' => in_array($yearNumber, [$currentYear, $currentYear + 1], true) ? Holidays::next($stateCode) : null,
            'reviewed' => Holidays::reviewed(),
        ]);
    }

    /**
     * Feriados de um mês (/feriados/2026/novembro): nacionais, pontos facultativos,
     * estaduais e das capitais de todos os estados, dias úteis e feriadões.
     */
    private function showMonth(string $year, int $month): void
    {
        $yearNumber = $this->validate($year, null);
        if ($yearNumber === null) {
            return;
        }
        $monthName = Holidays::MONTH_NAMES[$month];
        $monthTitle = mb_convert_case($monthName, MB_CASE_TITLE);
        $path = '/feriados/' . $yearNumber . '/' . Holidays::MONTH_SLUGS[$month];
        $yearHolidays = Holidays::forYear($yearNumber);
        $holidays = array_values(array_filter($yearHolidays, fn (array $holiday) => (int) substr($holiday['date'], 5, 2) === $month));
        $national = array_values(array_filter($holidays, fn (array $holiday) => $holiday['type'] === 'nacional'));
        $longWeekends = array_values(array_filter($national, fn (array $holiday) => $holiday['bridge'] !== ''));
        $localHolidays = Holidays::localForMonth($yearNumber, $month);
        $workdays = Holidays::workdaysByMonth($yearNumber, $yearHolidays)[$month];
        $states = Holidays::states();

        $describe = fn (array $holiday) => implode(' / ', $holiday['names']) . ' (' . $holiday['weekday'] . ', ' . (new \DateTimeImmutable($holiday['date']))->format('d/m') . ')';
        $joinList = fn (array $items) => count($items) > 1 ? implode(', ', array_slice($items, 0, -1)) . ' e ' . end($items) : (string) ($items[0] ?? '');
        $statesWithHoliday = array_values(array_unique(array_map(fn (array $holiday) => $states[$holiday['stateCode']]['name'], array_filter($localHolidays, fn (array $holiday) => $holiday['type'] === 'estadual'))));
        $questions = [
            ["Quais são os feriados de {$monthName} de {$yearNumber}?", $national === []
                ? "{$monthTitle} de {$yearNumber} não tem feriado nacional." . (count($holidays) > 0 ? ' Há ponto facultativo: ' . $joinList(array_map($describe, $holidays)) . '.' : '')
                : (count($national) === 1 ? 'O feriado nacional de ' : 'Os feriados nacionais de ') . "{$monthName} de {$yearNumber} " . (count($national) === 1 ? 'é ' : 'são ') . $joinList(array_map($describe, $national)) . '.'],
            ["{$monthTitle} de {$yearNumber} tem feriadão?", $longWeekends === []
                ? "Não. Nenhum feriado nacional de {$monthName} de {$yearNumber} cai numa segunda, terça, quinta ou sexta-feira."
                : 'Sim. ' . implode(' ', array_map(fn (array $holiday) => implode(' / ', $holiday['names']) . ' cai numa ' . $holiday['weekday'] . ', ' . (new \DateTimeImmutable($holiday['date']))->format('d/m') . ': ' . $holiday['bridge'] . '.', $longWeekends))],
            ["Quantos dias úteis tem {$monthName} de {$yearNumber}?", "{$monthTitle} de {$yearNumber} tem {$workdays} dias úteis, contando de segunda a sexta e tirando os feriados nacionais. Feriados estaduais e municipais diminuem esse número na sua cidade."],
            ["Tem feriado estadual em {$monthName} de {$yearNumber}?", $statesWithHoliday === []
                ? "Nenhum estado tem feriado estadual próprio em {$monthName}, mas algumas capitais podem ter feriado municipal; veja a lista nesta página."
                : 'Sim, em ' . count($statesWithHoliday) . (count($statesWithHoliday) === 1 ? ' estado: ' : ' estados: ') . $joinList($statesWithHoliday) . '.'],
        ];

        http_response_code(200);
        header('Cache-Control: public, max-age=0, s-maxage=600');
        echo View::render('site/holidays-month', [
            'ogImage' => share_banner('feriados'),
            'pageTitle' => "Feriados de {$monthTitle} de {$yearNumber}: Datas, Feriadões e Dias Úteis | Vibe2000",
            'metaDescription' => $questions[0][1] . ' ' . $questions[2][1],
            'canonicalPath' => $path,
            'structuredData' => StructuredData::holidayMonth($yearNumber, $monthName, $path, $questions),
            'pageKey' => 'feriados',
            'categories' => Content::categories(),
            'searchTerm' => '',
            'activeCategory' => null,
            'year' => $yearNumber,
            'month' => $month,
            'monthTitle' => $monthTitle,
            'holidays' => $holidays,
            'localHolidays' => $localHolidays,
            'workdays' => $workdays,
            'nationalCount' => count($national),
            'longWeekendCount' => count($longWeekends),
            'questions' => $questions,
            'reviewed' => Holidays::reviewed(),
        ]);
    }

    /**
     * Calendário no formato iCalendar (.ics): um evento de dia inteiro para cada feriado.
     */
    private function sendAgenda(string $year, ?string $stateCode): void
    {
        $yearNumber = $this->validate($year, $stateCode);
        if ($yearNumber === null) {
            return;
        }
        $escape = fn (string $text) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vibe2000//Feriados//PT-BR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $escape("Feriados {$yearNumber}" . ($stateCode ? ' - ' . strtoupper($stateCode) : '') . ' (Vibe2000)')];
        foreach (Holidays::forYear($yearNumber, $stateCode) as $holiday) {
            $start = str_replace('-', '', $holiday['date']);
            $end = (new \DateTimeImmutable($holiday['date']))->modify('+1 day')->format('Ymd');
            $title = implode(' / ', $holiday['names']) . ($holiday['type'] === 'facultativo' ? ' (ponto facultativo)' : '');
            array_push($lines, 'BEGIN:VEVENT', 'UID:' . $start . '-' . md5($title) . '@vibe2000.com.br', 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:' . $start, 'DTEND;VALUE=DATE:' . $end, 'SUMMARY:' . $escape($title), 'TRANSP:TRANSPARENT', 'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="feriados-' . $yearNumber . ($stateCode ? '-' . $stateCode : '') . '.ics"');
        header('Cache-Control: public, max-age=0, s-maxage=86400');
        echo implode("\r\n", $lines) . "\r\n";
    }
}
