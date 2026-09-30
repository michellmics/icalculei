<?php
/**
 * Painel → Visitas (mesmo modelo do painel do Pote Político).
 *
 * @var int $days
 * @var array|null $stats
 * @var array|null $online
 * @var string|null $databaseError
 */
use App\Services\VisitStats;

$periodName = VisitStats::PERIODS[$days];
$weekdayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$monthNames = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/** Selo ▲/▼ com a variação em %. */
$changeBadge = function (?float $change, string $comparedTo): string {
    if ($change === null) {
        return '<span class="change is-flat">— sem base <small>' . e($comparedTo) . '</small></span>';
    }
    $direction = $change > 0.5 ? 'is-up' : ($change < -0.5 ? 'is-down' : 'is-flat');
    $arrow = $change > 0.5 ? '▲' : ($change < -0.5 ? '▼' : '●');

    return '<span class="change ' . $direction . '">' . $arrow . ' ' . ($change > 0 ? '+' : '') . format_number($change, 1) . '% <small>' . e($comparedTo) . '</small></span>';
};

/** Lista de barras (rankings). */
$barList = function (array $items, string $color, string $unit): string {
    $items = array_filter($items);
    if ($items === []) {
        return '<p class="muted">Sem dados no período.</p>';
    }
    $highest = max($items);
    $total = array_sum($items);
    $html = '<ul class="bar-list">';
    foreach ($items as $name => $count) {
        $html .= '<li><div class="bar-top"><span>' . e((string) $name) . '</span><b>' . format_number($count) . ' <small>' . $unit . ' · ' . round($count / $total * 100) . '%</small></b></div>'
            . '<div class="bar-track"><i style="width:' . max(2, round($count / $highest * 100, 1)) . '%;background:' . $color . '"></i></div></li>';
    }

    return $html . '</ul>';
};
?>
<?php if ($databaseError): ?>
  <p class="admin-flash"><?= e($databaseError) ?></p>
<?php else: ?>
  <?php
  $period = $stats['period'];
  $previous = $stats['previous_period'];
  $today = $stats['today'];
  $trend = $stats['trend'];
  $periodChange = VisitStats::percentChange($period['visitors'], $previous['visitors']);
  if ($trend === null) {
      [$verdictIcon, $verdictClass, $verdictText] = ['⏳', '', 'Ainda há poucos dias de dados para medir a tendência. Volte em alguns dias.'];
  } elseif ($trend >= 3) {
      [$verdictIcon, $verdictClass, $verdictText] = ['📈', 'is-up', 'O tráfego está <b>subindo</b>: cerca de <b>+' . format_number($trend, 1) . '% por semana</b> no ritmo dos últimos ' . $periodName . '.'];
  } elseif ($trend <= -3) {
      [$verdictIcon, $verdictClass, $verdictText] = ['📉', 'is-down', 'O tráfego está <b>caindo</b>: cerca de <b>' . format_number($trend, 1) . '% por semana</b> no ritmo dos últimos ' . $periodName . '.'];
  } else {
      [$verdictIcon, $verdictClass, $verdictText] = ['➡️', '', 'O tráfego está <b>estável</b> nos últimos ' . $periodName . ' (' . ($trend > 0 ? '+' : '') . format_number($trend, 1) . '% por semana).'];
  }
  $heatMax = max(1, ...array_map('max', $stats['heatmap']));
  $deviceNames = ['celular' => '📱 Celular', 'computador' => '💻 Computador', 'tablet' => '📟 Tablet'];
  $devices = [];
  foreach ($stats['devices'] as $device => $count) {
      $devices[$deviceNames[$device] ?? $device] = $count;
  }
  ?>
  <div class="admin-head">
    <div><h1>Visitas do site</h1><span class="muted"><?= $stats['first_visit'] ? 'contando desde ' . e(format_date($stats['first_visit'])) : 'ainda sem visitas registradas' ?></span></div>
    <nav class="period-picker" aria-label="Período">
      <?php foreach (VisitStats::PERIODS as $periodDays => $periodLabel): ?>
        <a href="/painel/visitas<?= $periodDays === 30 ? '' : '?dias=' . $periodDays ?>" <?= $periodDays === $days ? 'aria-current="page"' : '' ?>><?= e($periodLabel) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <section class="admin-card live-card" id="live-card" aria-live="polite">
    <div class="live-number">
      <span class="live-pulse" aria-hidden="true"></span>
      <div>
        <small>Online agora</small>
        <strong id="live-now"><?= format_number($online['now']) ?></strong>
        <span class="muted"><b id="live-half-hour"><?= format_number($online['half_hour']) ?></b> nos últimos 30 min · atualizado às <b id="live-time"><?= e($online['time']) ?></b></span>
      </div>
    </div>
    <div class="live-chart">
      <div class="chart-box chart-small"><canvas id="chart-live" aria-label="Pessoas online nos últimos minutos"></canvas></div>
      <small class="muted">desde que você abriu esta página (a cada 10 s)</small>
    </div>
    <div class="live-pages">
      <small>Onde estão</small>
      <ul id="live-pages">
        <?php foreach ($online['pages'] as $page => $count): ?><li><span><?= e($page) ?></span><b><?= $count ?></b></li><?php endforeach; ?>
        <?php if ($online['pages'] === []): ?><li class="muted">Ninguém no site agora.</li><?php endif; ?>
      </ul>
    </div>
  </section>

  <section class="kpi-grid">
    <article class="admin-card kpi"><small>Visitantes hoje</small><strong><?= format_number($today['visitors']) ?></strong>
      <?= $changeBadge(VisitStats::percentChange($today['visitors'], $stats['yesterday_until_now']['visitors']), 'vs ontem até ' . date('H:i')) ?>
      <span class="muted"><?= format_number($today['page_views']) ?> páginas vistas · ontem inteiro: <?= format_number($stats['yesterday']['visitors']) ?></span></article>
    <article class="admin-card kpi"><small>Visitantes · <?= e($periodName) ?></small><strong><?= format_number($period['visitors']) ?></strong>
      <?= $changeBadge($periodChange, 'vs ' . $periodName . ' anteriores') ?>
      <span class="muted">média de <?= format_number($period['visitors'] / $days) ?> por dia</span></article>
    <article class="admin-card kpi"><small>Páginas vistas · <?= e($periodName) ?></small><strong><?= format_number($period['page_views']) ?></strong>
      <?= $changeBadge(VisitStats::percentChange($period['page_views'], $previous['page_views']), 'vs anteriores') ?>
      <span class="muted"><?= $period['visitors'] ? format_number($period['page_views'] / $period['visitors'], 1) : '0' ?> páginas por visitante</span></article>
    <article class="admin-card kpi"><small>Visitantes novos · <?= e($periodName) ?></small><strong><?= format_number($period['new_visitors']) ?></strong>
      <?= $changeBadge(VisitStats::percentChange($period['new_visitors'], $previous['new_visitors']), 'vs anteriores') ?>
      <span class="muted"><?= format_number($stats['returning']) ?> voltaram (<?= $period['visitors'] ? round($stats['returning'] / $period['visitors'] * 100) : 0 ?>% recorrentes)</span></article>
  </section>

  <p class="verdict <?= $verdictClass ?>"><span aria-hidden="true"><?= $verdictIcon ?></span><span><?= $verdictText ?>
    <?php if ($periodChange !== null): ?> Comparando com os <?= e($periodName) ?> anteriores: <b><?= ($periodChange > 0 ? '+' : '') . format_number($periodChange, 1) ?>%</b> de visitantes.<?php endif; ?>
    <?php if ($stats['month_projection'] !== null && $days >= 30): ?> Projeção para <?= $monthNames[(int) date('n') - 1] ?>: <b>~<?= format_number($stats['month_projection']) ?> visitantes</b>.<?php endif; ?>
  </span></p>

  <section class="admin-card">
    <div class="card-head"><h3>Visitas por dia</h3>
      <p class="chart-legend"><span><i class="legend-1"></i>Visitantes</span><span><i class="legend-dash"></i>Média de 7 dias</span><span><i class="legend-2"></i>Páginas vistas</span></p>
    </div>
    <div class="chart-box chart-tall"><canvas id="chart-days" aria-label="Visitantes e páginas vistas por dia"></canvas></div>
  </section>

  <div class="admin-two">
    <section class="admin-card">
      <div class="card-head"><h3>Últimos 12 meses</h3><p class="chart-legend"><span><i class="legend-1"></i>Visitantes</span><span><i class="legend-projection"></i>Projeção do mês</span></p></div>
      <div class="chart-box"><canvas id="chart-months" aria-label="Visitantes por mês"></canvas></div>
    </section>
    <section class="admin-card">
      <div class="card-head"><h3>Por hora</h3><p class="chart-legend"><span><i class="legend-1"></i>Hoje</span><span><i class="legend-3"></i>Ontem</span></p></div>
      <div class="chart-box"><canvas id="chart-hours" aria-label="Visitantes por hora, hoje e ontem"></canvas></div>
    </section>
  </div>

  <section class="admin-card">
    <div class="card-head"><h3>Quando o site mais recebe gente</h3><span class="muted">páginas vistas por dia da semana e hora · últimos <?= $stats['heatmap_days'] ?> dias</span></div>
    <div class="heatmap-wrap">
      <table class="heatmap">
        <thead><tr><th></th><?php for ($hour = 0; $hour < 24; $hour++): ?><th><?= $hour % 3 === 0 ? $hour . 'h' : '' ?></th><?php endfor; ?></tr></thead>
        <tbody>
          <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday): ?>
            <tr><th><?= $weekdayNames[$weekday] ?></th>
              <?php for ($hour = 0; $hour < 24; $hour++): $cellValue = $stats['heatmap'][$weekday][$hour]; ?>
                <td style="--level:<?= round($cellValue / $heatMax, 3) ?>" title="<?= $weekdayNames[$weekday] ?> <?= $hour ?>h–<?= $hour + 1 ?>h: <?= format_number($cellValue) ?> páginas vistas"></td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="chart-legend heatmap-legend"><span>menos</span><i style="--level:.08"></i><i style="--level:.3"></i><i style="--level:.55"></i><i style="--level:.8"></i><i style="--level:1"></i><span>mais</span></p>
  </section>

  <div class="admin-three">
    <section class="admin-card"><h3>Páginas mais vistas</h3><?= $barList($stats['pages'], 'var(--vis-2)', 'vistas') ?></section>
    <section class="admin-card"><h3>De onde vêm</h3><?= $barList($stats['sources'], 'var(--vis-1)', 'pessoas') ?></section>
    <section class="admin-card"><h3>Aparelhos</h3><?= $barList($devices, 'var(--vis-4)', 'pessoas') ?>
      <h3 class="admin-subtitle">Novos × voltaram</h3><?= $barList(['✨ Primeira vez' => $period['new_visitors'], '🔁 Voltaram' => $stats['returning']], 'var(--vis-3)', 'pessoas') ?></section>
  </div>

  <p class="admin-footnote">Cada navegador conta como um visitante (cookie anônimo, sem guardar IP). Robôs e o navegador com o painel aberto não entram na conta. "Online" = com o site aberto nos últimos <?= VisitStats::ONLINE_MINUTES ?> minutos.</p>

  <?php
  $chartData = [
      'days' => array_map(fn (string $day, array $values) => ['date' => $day] + $values, array_keys($stats['by_day']), $stats['by_day']),
      'months' => array_map(fn (string $month, int $visitors) => ['label' => $monthNames[(int) substr($month, 5) - 1] . '/' . substr($month, 2, 2), 'visitors' => $visitors], array_keys($stats['months']), $stats['months']),
      'projection' => $stats['month_projection'],
      'hours' => $stats['hours'],
      'hourNow' => $stats['hour_now'],
      'online' => $online['now'],
  ];
  ?>
  <script type="application/json" id="visits-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
