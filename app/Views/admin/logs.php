<?php
/**
 * Painel → Logs (storage/logs e error_log do PHP). Ver app/Services/LogViewer.php.
 *
 * @var array $files        [chave => label, path, size, modified]
 * @var string $selectedKey
 * @var string $search
 * @var array|null $log     lines (text, kind), truncated
 */
$formatSize = fn (int $bytes): string => $bytes >= 1048576
    ? format_number($bytes / 1048576, 1) . ' MB'
    : format_number(max(1, (int) ceil($bytes / 1024))) . ' KB';
$totalSize = array_sum(array_column($files, 'size'));
$kindLabels = ['erro' => 'erro', 'deploy' => 'deploy', 'info' => 'info'];
?>
<div class="admin-head"><div><h1>Logs</h1><span class="muted"><?= count($files) ?> arquivo(s) · <?= e($formatSize($totalSize)) ?> no total</span></div></div>

<section class="admin-card">
  <?php if ($files === []): ?>
    <p class="muted">Nenhum log ainda. Bom sinal: nenhum erro foi registrado.</p>
  <?php else: ?>
    <form method="get" action="/painel/logs" class="log-filters">
      <label>Arquivo
        <select name="arquivo" data-autosubmit>
          <?php foreach ($files as $key => $file): ?>
            <option value="<?= e($key) ?>" <?= $key === $selectedKey ? 'selected' : '' ?>><?= e($file['label']) ?> · <?= e($formatSize($file['size'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Buscar
        <input type="search" name="busca" value="<?= e($search) ?>" placeholder="ex.: deploy, SQLSTATE, fipe">
      </label>
      <button type="submit" class="secondary-button">Filtrar</button>
    </form>

    <?php $selected = $files[$selectedKey]; ?>
    <p class="muted log-meta">
      <code><?= e(substr($selected['path'], strlen(BASE_PATH) + 1)) ?></code> · última gravação em <?= e(date('d/m/Y H:i', $selected['modified'])) ?>
      · <?= count($log['lines']) ?> linha(s)<?= $search !== '' ? ' com “' . e($search) . '”' : '' ?>, a mais nova primeiro
    </p>
    <?php if ($log['truncated']): ?>
      <p class="admin-footnote">Arquivo grande: mostrando só os registros mais recentes (até 1.000 linhas do último 1 MB).</p>
    <?php endif; ?>

    <?php if ($log['lines'] === []): ?>
      <p class="muted">Nenhuma linha<?= $search !== '' ? ' com esse texto' : '' ?>.</p>
    <?php else: ?>
      <ol class="log-lines">
        <?php foreach ($log['lines'] as $line): ?>
          <li class="log-<?= e($line['kind']) ?>"><span class="log-kind"><?= e($kindLabels[$line['kind']]) ?></span><span><?= e($line['text']) ?></span></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  <?php endif; ?>
  <p class="admin-footnote log-note">Um arquivo por dia em <code>storage/logs</code>, guardado por <?= \App\Core\ErrorHandler::LOG_RETENTION_DAYS ?> dias (os mais velhos são apagados sozinhos).
    Cada dia grava no máximo <?= e($formatSize(\App\Core\ErrorHandler::LOG_MAX_BYTES_PER_DAY)) ?>; passou disso, o resto do dia não é gravado.
    O <code>error_log</code> do PHP é do cPanel: se crescer muito, apague pelo Gerenciador de Arquivos.</p>
</section>
