<?php
/**
 * Painel → Atualizar site (baixa o código do GitHub e roda as migrations). Ver app/Services/Deployer.php.
 *
 * @var array $settings      repo, branch, token
 * @var array|null $lastDeploy
 * @var array|null $remoteCommit
 * @var string|null $remoteError
 * @var bool $isBlockedHere
 * @var array|null $result   ok, steps, commit
 * @var array $migrations    [nome => já rodou?]
 */
$shortSha = fn (?string $sha): string => $sha ? substr($sha, 0, 7) : '—';
$isUpToDate = $remoteCommit !== null && $lastDeploy !== null && $lastDeploy['commit'] === $remoteCommit['sha'];
$pendingMigrations = array_keys(array_filter($migrations, fn (bool $alreadyRan) => !$alreadyRan));
?>
<div class="admin-head"><div><h1>Atualizar site</h1><span class="muted"><?= e($settings['repo']) ?> · branch <?= e($settings['branch']) ?></span></div></div>

<?php if ($result !== null): ?>
  <section class="admin-card deploy-result <?= $result['ok'] ? 'is-ok' : 'is-error' ?>">
    <h3><?= $result['ok'] ? 'Atualização concluída' : 'A atualização não terminou' ?></h3>
    <ol><?php foreach ($result['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
  </section>
<?php endif; ?>

<section class="admin-card">
  <div class="deploy-versions">
    <div>
      <small>No servidor</small>
      <b><?= e($shortSha($lastDeploy['commit'] ?? null)) ?></b>
      <span><?= $lastDeploy !== null
          ? e($lastDeploy['message'] ?? '') . '<br>atualizado em ' . e(date('d/m/Y H:i', strtotime($lastDeploy['date'])))
          : 'Nenhuma atualização feita pelo painel ainda.' ?></span>
    </div>
    <div>
      <small>No GitHub</small>
      <?php if ($remoteCommit !== null): ?>
        <b><?= e($shortSha($remoteCommit['sha'])) ?></b>
        <span><?= e($remoteCommit['message']) ?><br><?= e($remoteCommit['author']) ?><?= $remoteCommit['date'] ? ' · ' . e(date('d/m/Y H:i', strtotime($remoteCommit['date']))) : '' ?></span>
      <?php else: ?>
        <b>?</b>
        <span class="deploy-error"><?= e((string) $remoteError) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isBlockedHere): ?>
    <p class="admin-flash">Você está no computador de desenvolvimento (APP_ENV=local): aqui o botão fica bloqueado para não apagar o que ainda não foi para o GitHub. Use-o no painel do servidor.</p>
  <?php elseif ($isUpToDate): ?>
    <p class="form-success">✓ O servidor já está com o último commit.</p>
  <?php endif; ?>

  <form method="post" action="/painel/atualizar" data-confirm="Atualizar o site com o commit <?= e($shortSha($remoteCommit['sha'] ?? null)) ?> do GitHub? Os arquivos serão trocados e as migrations pendentes vão rodar.">
    <?= csrf_field() ?>
    <button type="submit" class="action-button" <?= $remoteCommit === null || $isBlockedHere ? 'disabled' : '' ?>><?= $isUpToDate ? 'Aplicar de novo' : '🚀 Atualizar agora' ?></button>
  </form>

  <p class="muted deploy-note">Baixa o código da branch <b><?= e($settings['branch']) ?></b>, troca os arquivos, apaga os que saíram do repositório e roda as migrations pendentes.
    Nunca mexe no <code>.env</code> nem na pasta <code>storage/</code> (sessões e logs). O passo a passo fica registrado em <code>storage/logs</code>.</p>
  <p class="muted deploy-note">Configuração (.env) lida de: <code><?= e(\App\Core\Env::loadedFile() ?? 'nenhum .env encontrado') ?></code></p>
  <p class="muted deploy-note">Migrations: <?= $pendingMigrations === [] ? 'nenhuma pendente' : e(count($pendingMigrations) . ' pendente(s): ' . implode(', ', $pendingMigrations)) ?>.</p>
</section>
