<?php
/**
 * Layout do painel administrativo.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $activeTab
 * @var array $admin
 * @var int $newMessages
 */
$tabs = [
    'visits' => ['href' => '/painel/visitas', 'label' => '📊 Visitas'],
    'tools' => ['href' => '/painel/calculadoras', 'label' => '🧮 Calculadoras'],
    'messages' => ['href' => '/painel/mensagens', 'label' => '✉️ Mensagens'],
    'logs' => ['href' => '/painel/logs', 'label' => '📜 Logs'],
    'deploy' =>['href' => '/painel/atualizar', 'label' => '🚀 Atualizar site'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · <?= e($pageTitle) ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <!-- PWA do painel (public/manifest-painel.webmanifest + public/sw-painel.js) -->
  <link rel="manifest" href="/manifest-painel.webmanifest">
  <link rel="apple-touch-icon" href="/icons/painel-apple-touch.png">
  <meta name="apple-mobile-web-app-title" content="Painel iCalculei">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#1c2b24">
  <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
  <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
  <script src="<?= e(asset('js/app-shell.js')) ?>" defer></script>
</head>
<body>
  <div class="admin-shell">
    <header class="admin-topbar">
      <span class="admin-brand"><?= \App\Core\View::partial("logo-mark") ?> iCalculei <b>Painel</b></span>
      <!-- Menu sanduíche: só aparece no celular (admin.js abre e fecha o #admin-menu) -->
      <button type="button" class="admin-menu-button" aria-label="Abrir menu" aria-expanded="false" aria-controls="admin-menu">
        <span></span><span></span><span></span>
        <?php if ($newMessages > 0): ?><i class="admin-menu-dot" aria-hidden="true"></i><?php endif; ?>
      </button>
      <div class="admin-menu" id="admin-menu">
      <nav class="admin-nav" aria-label="Painel">
        <?php foreach ($tabs as $tabKey => $tab): ?>
          <a href="<?= e($tab['href']) ?>" <?= $activeTab === $tabKey ? 'aria-current="page"' : '' ?>>
            <?= e($tab['label']) ?><?php if ($tabKey === 'messages' && $newMessages > 0): ?> <span class="admin-badge"><?= $newMessages ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-topbar-actions">
        <a class="admin-link" href="/" target="_blank" rel="noopener">ver o site</a>
        <form method="post" action="/painel/sair">
          <?= csrf_field() ?>
          <button type="submit" class="admin-link">sair</button>
        </form>
      </div>
      </div>
    </header>
    <main class="admin-main">
      <?= $content ?>
    </main>
  </div>
  <!-- Navegação do app instalado (PWA): só aparece no app em telas de celular (assets/js/app-shell.js) -->
  <nav class="app-nav" id="app-nav" aria-label="Navegação do app" hidden>
    <button type="button" data-app-action="back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg><span>Voltar</span></button>
    <button type="button" data-app-action="forward"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg><span>Avançar</span></button>
    <a href="/painel/visitas"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg><span>Visitas</span></a>
    <button type="button" data-app-action="reload"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/></svg><span>Atualizar</span></button>
  </nav>
</body>
</html>
