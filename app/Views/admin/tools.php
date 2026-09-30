<?php
/**
 * Painel → Uso das calculadoras.
 *
 * @var array $rows       cada item: tool, uses, change
 * @var array $categories
 */
$monthsUntilReview = 6;
?>
<div class="admin-head"><div><h1>Uso das calculadoras</h1><span class="muted">últimos 30 dias · cálculos feitos em cada ferramenta (um por página aberta)</span></div></div>
<section class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Calculadora</th><th>Categoria</th><th>Usos</th><th>vs 30 dias anteriores</th><th>Revisão</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
          $tool = $row['tool'];
          $reviewTimestamp = $tool['reviewed'] !== '' ? strtotime($tool['reviewed']) : false;
          $needsReview = $reviewTimestamp === false || $reviewTimestamp < strtotime("-{$monthsUntilReview} months");
          $change = $row['change'];
          ?>
          <tr>
            <td><a href="/calculadoras/<?= e($tool['id']) ?>" target="_blank" rel="noopener"><?= e($tool['symbol'] . ' ' . $tool['name']) ?></a></td>
            <td><?= e($categories[$tool['category']]) ?></td>
            <td class="number"><?= format_number($row['uses']) ?></td>
            <td><?php if ($change === null): ?><span class="change is-flat">—</span><?php else: ?><span class="change <?= $change > 0.5 ? 'is-up' : ($change < -0.5 ? 'is-down' : 'is-flat') ?>"><?= $change > 0 ? '▲ +' : ($change < 0 ? '▼ ' : '● ') ?><?= format_number($change, 1) ?>%</span><?php endif; ?></td>
            <td><span class="status-chip <?= $needsReview ? 'status-warn' : 'status-ok' ?>"><?= $needsReview ? 'revisar' : e(format_date($tool['reviewed'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<p class="admin-footnote">"Revisar" aparece quando a última revisão tem mais de <?= $monthsUntilReview ?> meses. Calculadoras com tabelas oficiais (INSS, IR) precisam de revisão sempre que a lei muda.</p>
