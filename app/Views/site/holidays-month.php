<?php
/**
 * Feriados de um mês: /feriados/{ano}/{mês}, ex.: /feriados/2026/novembro
 *
 * @var int $year
 * @var int $month
 * @var string $monthTitle      "Novembro"
 * @var array $holidays         nacionais e pontos facultativos do mês (ver App\Services\Holidays::forYear)
 * @var array $localHolidays    estaduais e das capitais (ver App\Services\Holidays::localForMonth)
 * @var int $workdays
 * @var int $nationalCount
 * @var int $longWeekendCount
 * @var array $questions        [pergunta, resposta]
 * @var string $reviewed
 */
use App\Services\Holidays;

$typeLabels = ['nacional' => 'Nacional', 'estadual' => 'Estadual', 'municipal' => 'Municipal', 'facultativo' => 'Ponto facultativo'];
$formatDay = fn (string $date) => (new DateTimeImmutable($date))->format('d/m');
$holidaysByDate = array_column($holidays, null, 'date');
$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$offset = (int) $firstDay->format('w');
$previous = $firstDay->modify('-1 month');
$next = $firstDay->modify('+1 month');
$monthLink = function (DateTimeImmutable $date): ?string {
    $linkYear = (int) $date->format('Y');
    if ($linkYear < Holidays::MIN_YEAR || $linkYear > Holidays::MAX_YEAR) {
        return null;
    }
    return '/feriados/' . $linkYear . '/' . Holidays::MONTH_SLUGS[(int) $date->format('n')];
};
// "Outubro" (mesmo ano) ou "Janeiro 2027" (outro ano)
$monthLabel = fn (DateTimeImmutable $date) => mb_convert_case(Holidays::MONTH_NAMES[(int) $date->format('n')], MB_CASE_TITLE) . ((int) $date->format('Y') !== $year ? ' ' . $date->format('Y') : '');
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em">
    <a href="/">Início</a> › <a href="/feriados/<?= $year ?>">Feriados <?= $year ?></a> › <span><?= e($monthTitle) ?></span>
  </nav>
  <h1 class="tool-title">Feriados de <?= e($monthTitle) ?> de <?= $year ?></h1>
  <p class="tool-lead"><?= e($questions[0][1]) ?> Veja o dia da semana, os feriadões, os dias úteis e os feriados estaduais e das capitais.</p>

  <div class="holiday-controls">
    <div class="holiday-year-nav" aria-label="Escolher o mês">
      <?php if ($previousLink = $monthLink($previous)): ?><a class="secondary-button" href="<?= e($previousLink) ?>">← <?= e($monthLabel($previous)) ?></a><?php endif; ?>
      <b><?= e($monthTitle) ?></b>
      <?php if ($nextLink = $monthLink($next)): ?><a class="secondary-button" href="<?= e($nextLink) ?>"><?= e($monthLabel($next)) ?> →</a><?php endif; ?>
    </div>
    <a class="action-button holiday-agenda" href="/feriados/<?= $year ?>/agenda" download>📅 Feriados de <?= $year ?> na agenda</a>
  </div>

  <section class="holiday-summary" aria-label="Resumo">
    <div><b><?= $nationalCount ?></b><span><?= $nationalCount === 1 ? 'feriado nacional' : 'feriados nacionais' ?></span></div>
    <div><b><?= $longWeekendCount ?></b><span>feriadões ou emendas</span></div>
    <div><b><?= $workdays ?></b><span>dias úteis</span></div>
    <div><b><?= count($localHolidays) ?></b><span>feriados estaduais e das capitais</span></div>
  </section>

  <div class="two-columns">
    <div class="main-column">
      <section class="admin-card holiday-list-card">
        <h2>Feriados nacionais de <?= e(mb_strtolower($monthTitle)) ?> de <?= $year ?></h2>
        <?php if ($holidays === []): ?>
          <p><?= e($monthTitle) ?> de <?= $year ?> não tem feriado nacional nem ponto facultativo.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table holiday-table">
              <thead><tr><th>Data</th><th>Feriado</th><th>Tipo</th></tr></thead>
              <tbody>
                <?php foreach ($holidays as $holiday): ?>
                  <tr class="holiday-row is-<?= e($holiday['type']) ?>">
                    <td><b><?= e($formatDay($holiday['date'])) ?></b><small><?= e($holiday['weekday']) ?></small></td>
                    <td><?= e(implode(' · ', $holiday['names'])) ?><?php if ($holiday['bridge'] !== ''): ?><small class="holiday-bridge"><?= e($holiday['bridge']) ?></small><?php endif; ?></td>
                    <td><span class="holiday-tag is-<?= e($holiday['type']) ?>"><?= e($typeLabels[$holiday['type']]) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="holiday-month holiday-month-single">
          <h3><?= e($monthTitle) ?> <?= $year ?> <small><?= $workdays ?> dias úteis</small></h3>
          <div class="holiday-grid">
            <?php foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekdayLetter): ?><span class="holiday-weekday"><?= $weekdayLetter ?></span><?php endforeach; ?>
            <?php for ($blank = 0; $blank < $offset; $blank++): ?><span></span><?php endfor; ?>
            <?php for ($day = 1; $day <= (int) $firstDay->format('t'); $day++): ?>
              <?php
              $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
              $holiday = $holidaysByDate[$date] ?? null;
              $weekdayNumber = ($offset + $day - 1) % 7;
              $classes = 'holiday-day' . ($weekdayNumber === 0 || $weekdayNumber === 6 ? ' is-weekend' : '') . ($holiday ? ' is-' . $holiday['type'] : '');
              ?>
              <span class="<?= $classes ?>"<?= $holiday ? ' title="' . e(implode(' · ', $holiday['names'])) . '"' : '' ?>><?= $day ?></span>
            <?php endfor; ?>
          </div>
        </div>
      </section>

      <section class="admin-card holiday-list-card">
        <h2>Feriados estaduais e das capitais em <?= e(mb_strtolower($monthTitle)) ?></h2>
        <?php if ($localHolidays === []): ?>
          <p>Nenhum estado ou capital tem feriado próprio em <?= e(mb_strtolower($monthTitle)) ?>.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table holiday-table">
              <thead><tr><th>Data</th><th>Feriado</th><th>Onde</th></tr></thead>
              <tbody>
                <?php foreach ($localHolidays as $holiday): ?>
                  <tr>
                    <td><b><?= e($formatDay($holiday['date'])) ?></b><small><?= e($holiday['weekday']) ?></small></td>
                    <td><?= e($holiday['name']) ?></td>
                    <td><a href="/feriados/<?= $year ?>/<?= e($holiday['stateCode']) ?>"><?= e($holiday['place']) ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="explainer">
        <h2>Perguntas frequentes</h2>
        <div class="faq">
          <?php foreach ($questions as [$question, $answer]): ?>
            <details><summary><?= e($question) ?></summary><p><?= e($answer) ?></p></details>
          <?php endforeach; ?>
        </div>
        <p class="notice">Datas estaduais e municipais conferidas em <?= e(format_date($reviewed)) ?>. Leis locais podem mudar: confirme no site do governo do estado ou da prefeitura. Precisa contar prazos? Use a <a href="/calculadoras/dias-uteis">calculadora de dias úteis</a>.</p>
      </section>
    </div>

    <aside class="side-column">
      <section class="side-box">
        <h3>Feriados de <?= $year ?> por mês</h3>
        <ul class="holiday-state-links">
          <?php foreach (Holidays::MONTH_SLUGS as $monthNumber => $slug): ?>
            <li><a href="/feriados/<?= $year ?>/<?= $slug ?>"<?= $monthNumber === $month ? ' aria-current="page"' : '' ?>><?= e(mb_convert_case(Holidays::MONTH_NAMES[$monthNumber], MB_CASE_TITLE)) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="side-box">
        <h3>Calendário completo</h3>
        <ul class="link-list-plain">
          <li><a href="/feriados/<?= $year ?>">Todos os feriados de <?= $year ?></a></li>
          <li><a href="/calculadoras/dias-ate-data">Quantos dias faltam para uma data</a></li>
          <li><a href="/calculadoras/custo-de-viagem">Custo de viagem no feriadão</a></li>
        </ul>
      </section>
    </aside>
  </div>
</main>
