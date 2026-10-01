<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\View;
use App\Services\Content;
use App\Services\Holidays;

/**
 * Calendário de feriados:
 *   /feriados                       vai para o ano atual
 *   /feriados/2026                  nacionais e pontos facultativos
 *   /feriados/2026/sp               + feriados do estado e da capital
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
            'pageTitle' => $state
                ? "Feriados {$yearNumber} em {$place}: Estaduais e de {$state['capital']} | Vibe2000"
                : "Feriados {$yearNumber}: Calendário Completo dos Feriados Nacionais | Vibe2000",
            'metaDescription' => $state
                ? "Todos os feriados de {$yearNumber} em {$state['name']}: nacionais, estaduais e da capital {$state['capital']}, com dia da semana, feriadões e calendário para baixar."
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
