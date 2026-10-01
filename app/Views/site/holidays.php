<?php
/**
 * Calendário de feriados: /feriados/{ano} e /feriados/{ano}/{uf}
 *
 * @var int $year
 * @var string|null $stateCode
 * @var array|null $state       name, capital, state, city
 * @var array $states
 * @var array $holidays         ver App\Services\Holidays::forYear
 * @var array $workdays         [mês => dias úteis]
 * @var int $realHolidayCount
 * @var int $onWorkdays
 * @var int $longWeekends
 * @var array|null $next        holiday, daysUntil
 * @var string $reviewed
 */
$monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$typeLabels = ['nacional' => 'Nacional', 'estadual' => 'Estadual', 'municipal' => 'Municipal', 'facultativo' => 'Ponto facultativo'];
$holidaysByDate = array_column($holidays, null, 'date');
$basePath = '/feriados/';
$stateSuffix = $stateCode ? '/' . $stateCode : '';
$formatDay = fn (string $date) => (new DateTimeImmutable($date))->format('d/m');
// "no Rio de Janeiro" → "do Rio de Janeiro"; "na Bahia" → "da Bahia"; "em São Paulo" → "de São Paulo"
$ofState = $state ? ['no' => 'do', 'na' => 'da', 'em' => 'de'][$state['in']] . ' ' . $state['name'] : '';
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em">
    <a href="/">Início</a> › <a href="/feriados/<?= $year ?>">Feriados <?= $year ?></a><?php if ($state): ?> › <span><?= e($state['name']) ?></span><?php endif; ?>
  </nav>
  <h1 class="tool-title">Feriados <?= $year ?><?= $state ? ' ' . e($state['in'] . ' ' . $state['name']) : '' ?></h1>
  <p class="tool-lead"><?= $state
      ? 'Feriados nacionais, estaduais ' . e($ofState) . ' e da capital ' . e($state['capital']) . ', com dia da semana, feriadões e calendário para baixar.'
      : 'Todos os feriados nacionais e pontos facultativos do ano, com dia da semana, feriadões e os dias úteis de cada mês. Escolha o estado para ver também os estaduais e da capital.' ?></p>

  <div class="holiday-controls">
    <div class="holiday-year-nav" aria-label="Escolher o ano">
      <?php if ($year > \App\Services\Holidays::MIN_YEAR): ?><a class="secondary-button" href="<?= $basePath . ($year - 1) . $stateSuffix ?>">← <?= $year - 1 ?></a><?php endif; ?>
      <b><?= $year ?></b>
      <?php if ($year < \App\Services\Holidays::MAX_YEAR): ?><a class="secondary-button" href="<?= $basePath . ($year + 1) . $stateSuffix ?>"><?= $year + 1 ?> →</a><?php endif; ?>
    </div>
    <div class="field holiday-state-field">
      <label for="holiday-state">Estado</label>
      <select id="holiday-state" data-year="<?= $year ?>">
        <option value="">Só feriados nacionais</option>
        <?php foreach ($states as $code => $item): ?>
          <option value="<?= e($code) ?>" <?= $code === $stateCode ? 'selected' : '' ?>><?= e($item['name']) ?> (<?= e(strtoupper($code)) ?>) · <?= e($item['capital']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <a class="action-button holiday-agenda" href="<?= $basePath . $year . $stateSuffix ?>/agenda" download>📅 Adicionar à minha agenda</a>
  </div>

  <section class="holiday-summary" aria-label="Resumo">
    <div><b><?= $realHolidayCount ?></b><span>feriados<?= $state ? '' : ' nacionais' ?></span></div>
    <div><b><?= $onWorkdays ?></b><span>em dias úteis</span></div>
    <div><b><?= $longWeekends ?></b><span>feriadões ou emendas</span></div>
    <?php if ($next !== null): ?>
      <div class="holiday-next"><b><?= $next['daysUntil'] === 0 ? 'Hoje!' : ($next['daysUntil'] === 1 ? 'Amanhã' : 'em ' . $next['daysUntil'] . ' dias') ?></b><span>próximo: <?= e($next['holiday']['names'][0]) ?> (<?= e($next['holiday']['weekday']) ?>, <?= e($formatDay($next['holiday']['date'])) ?>)</span></div>
    <?php endif; ?>
  </section>

  <div class="two-columns">
    <div class="main-column">
      <section class="admin-card holiday-list-card">
        <h2>Lista de feriados de <?= $year ?></h2>
        <div class="table-scroll">
          <table class="data-table holiday-table">
            <thead><tr><th>Data</th><th>Feriado</th><th>Tipo</th></tr></thead>
            <tbody>
              <?php foreach ($holidays as $holiday): ?>
                <tr class="holiday-row is-<?= e($holiday['type']) ?>">
                  <td><b><?= e($formatDay($holiday['date'])) ?></b><small><?= e($holiday['weekday']) ?></small></td>
                  <td><?= e(implode(' · ', $holiday['names'])) ?><?php if ($holiday['bridge'] !== '' && $holiday['isWorkday']): ?><small class="holiday-bridge"><?= e($holiday['bridge']) ?></small><?php endif; ?></td>
                  <td><span class="holiday-tag is-<?= e($holiday['type']) ?>"><?= e($typeLabels[$holiday['type']]) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="admin-card">
        <h2>Calendário <?= $year ?></h2>
        <div class="holiday-legend">
          <span class="holiday-tag is-nacional">Nacional</span>
          <?php if ($state): ?><span class="holiday-tag is-estadual">Estadual</span><span class="holiday-tag is-municipal">Municipal (<?= e($state['capital']) ?>)</span><?php endif; ?>
          <span class="holiday-tag is-facultativo">Ponto facultativo</span>
        </div>
        <div class="holiday-calendar">
          <?php for ($month = 1; $month <= 12; $month++): ?>
            <?php
            $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $daysInMonth = (int) $firstDay->format('t');
            $offset = (int) $firstDay->format('w');
            ?>
            <div class="holiday-month">
              <h3><?= $monthNames[$month] ?> <small><?= $workdays[$month] ?> dias úteis</small></h3>
              <div class="holiday-grid">
                <?php foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekdayLetter): ?><span class="holiday-weekday"><?= $weekdayLetter ?></span><?php endforeach; ?>
                <?php for ($blank = 0; $blank < $offset; $blank++): ?><span></span><?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
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
          <?php endfor; ?>
        </div>
      </section>

      <section class="explainer">
        <h2>Feriados nacionais, estaduais e pontos facultativos</h2>
        <p><b>Feriados nacionais</b> são definidos por lei federal e valem no país inteiro. A Sexta-feira Santa muda de data todo ano, porque depende da Páscoa.</p>
        <p><b>Pontos facultativos</b> (Carnaval, Quarta-feira de Cinzas e Corpus Christi) não são feriados nacionais: cada empresa, estado ou cidade decide se haverá folga. Corpus Christi é feriado municipal em muitas cidades.</p>
        <p><b>Feriados estaduais e municipais</b> são criados por lei de cada estado e de cada cidade. Esta página mostra os do estado e da capital; outras cidades têm os seus próprios.</p>
        <p class="notice">Datas estaduais e municipais conferidas em <?= e(format_date($reviewed)) ?> (levantamento da CNC, com correções). Leis locais podem mudar: confirme no site do governo do estado ou da prefeitura. Precisa contar prazos? Use a <a href="/calculadoras/dias-uteis">calculadora de dias úteis</a>. Encontrou um erro? <a href="/contato?assunto=erro">Avise a gente</a>.</p>
      </section>
    </div>

    <aside class="side-column">
      <section class="side-box">
        <h3>Feriados <?= $year ?> por estado</h3>
        <ul class="holiday-state-links">
          <?php foreach ($states as $code => $item): ?>
            <li><a href="<?= $basePath . $year . '/' . e($code) ?>"<?= $code === $stateCode ? ' aria-current="page"' : '' ?>><?= e($item['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="side-box">
        <h3>Outros anos</h3>
        <ul class="holiday-state-links">
          <?php foreach ([$year - 1, $year + 1, $year + 2] as $otherYear): ?>
            <?php if ($otherYear >= \App\Services\Holidays::MIN_YEAR && $otherYear <= \App\Services\Holidays::MAX_YEAR): ?>
              <li><a href="<?= $basePath . $otherYear . $stateSuffix ?>">Feriados <?= $otherYear ?></a></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
</main>
