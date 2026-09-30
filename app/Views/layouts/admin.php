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
    'deploy' => ['href' => '/painel/atualizar', 'label' => '🚀 Atualizar site'],
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
  <!-- PWA do painel (public/painel.webmanifest + public/sw-painel.js) -->
  <link rel="manifest" href="/painel.webmanifest">
  <link rel="apple-touch-icon" href="/icons/painel-apple-touch.png">
  <meta name="apple-mobile-web-app-title" content="Painel V2K">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#1c2b24">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=IBM+Plex+Mono:wght@500;600&family=Source+Sans+3:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
  <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body>
  <div class="admin-shell">
    <header class="admin-topbar">
      <span class="admin-brand"><?= \App\Core\View::partial("logo-mark") ?> Vibe2000 <b>Painel</b></span>
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
    </header>
    <main class="admin-main">
      <?= $content ?>
    </main>
  </div>
</body>
</html>
