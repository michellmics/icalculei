<?php

/**
 * Layout das páginas públicas.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $metaDescription
 * @var string $canonicalPath
 * @var string $pageKey        identifica a página para o contador de visitas
 * @var array $categories
 * @var string $searchTerm
 * @var string|null $activeCategory
 */

use App\Services\Ads;

$weekdays = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$todayLabel = $weekdays[(int) date('w')] . ', ' . date('j') . ' de ' . $months[(int) date('n') - 1] . ' de ' . date('Y');
$usesCalculators = $pageKey === 'inicio' || str_starts_with($pageKey, 'calculadora:');
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <meta name="description" content="<?= e($metaDescription) ?>">
  <meta name="robots" content="<?= e($robotsMeta ?? 'index, follow') ?>">
  <link rel="canonical" href="<?= e(url($canonicalPath)) ?>">
  <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
  <meta property="og:site_name" content="Vibe2000">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($metaDescription) ?>">
  <meta property="og:url" content="<?= e(url($canonicalPath)) ?>">
  <meta property="og:image" content="<?= e($ogImage ?? url('/icons/site-512.png')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <?php foreach ($structuredData ?? [] as $schema): ?>
    <!-- Dados estruturados (Schema.org) para o Google: app/Services/StructuredData.php -->
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php endforeach; ?>
  <meta name="theme-color" content="#0e6b4f">
  <!-- PWA do site (public/manifest.webmanifest + public/sw.js) -->
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="apple-touch-icon" href="/icons/site-apple-touch.png">
  <meta name="apple-mobile-web-app-title" content="Vibe2000">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="google-adsense-account" content="ca-pub-1658139075721224">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=IBM+Plex+Mono:wght@500;600&family=Source+Sans+3:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
  <?php if (Ads::isEnabled()): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e(config('adsense_client')) ?>" crossorigin="anonymous"></script>
  <?php endif; ?>
</head>

<body data-page="<?= e($pageKey) ?>">
  <div class="utility-bar">
    <div class="utility-inner">
      <span id="today-label"><?= e($todayLabel) ?></span>
      <span class="utility-links"><button type="button" class="install-pill" data-install-app hidden>📲 Instalar o app</button> Calculadoras · Conversores · Notícias · <a href="/contato?assunto=anuncie">Anuncie</a></span>
    </div>
  </div>

  <header class="site-header">
    <div class="header-inner">
      <a class="logo" href="/"><?= \App\Core\View::partial("logo-mark") ?>Vibe2000</a>
      <form class="header-search" action="/" method="get" role="search">
        <span class="search-icon" aria-hidden="true">⌕</span>
        <input class="search-input" name="busca" type="search" value="<?= e($searchTerm) ?>" placeholder="Buscar..." aria-label="Buscar calculadora" maxlength="60">
      </form>
    </div>
    <div class="nav-bar">
      <nav class="header-nav" aria-label="Categorias">
        <a class="nav-chip nav-news" href="/noticias">📰 Notícias</a>
        <?php foreach ($categories as $categoryKey => $categoryName): ?>
          <a class="nav-chip" href="/?categoria=<?= e($categoryKey) ?>" aria-pressed="<?= $activeCategory === $categoryKey ? 'true' : 'false' ?>"><?= e($categoryName) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>

  <?= $content ?>

  <footer class="site-footer">
    <div class="install-footer" data-install-area hidden>
      <button type="button" class="install-footer-button" data-install-app hidden>📲 Instalar o app do Vibe2000</button>
    </div>
    <div class="footer-disclaimer">
      <h2>Aviso importante</h2>
      <p>O Vibe2000 é um site <b>gratuito</b>, de caráter <b>informativo e educativo</b>. As calculadoras e conversores apresentam <b>estimativas</b> baseadas nas informações digitadas por você e nas regras e tabelas oficiais conhecidas na data de revisão indicada em cada ferramenta. Apesar do cuidado na elaboração, <b>podem ocorrer erros de cálculo, desatualizações ou diferenças</b> em relação ao seu caso específico (convenções coletivas, acordos, benefícios, particularidades da empresa ou da legislação local, entre outros).</p>
      <p>Os resultados <b>não substituem</b> a orientação de profissionais habilitados, como contador, advogado, consultor financeiro, médico ou nutricionista, nem os documentos e cálculos oficiais do empregador, de bancos ou de órgãos públicos. Confira sempre os valores antes de tomar qualquer decisão. As notícias e artigos têm finalidade informativa e não representam posição oficial de nenhum órgão.</p>
      <p>Ao utilizar o site, você concorda que o uso das informações é de sua inteira responsabilidade e que o Vibe2000 e seus responsáveis <b>não se responsabilizam por decisões tomadas, prejuízos ou danos</b> de qualquer natureza decorrentes do uso dos resultados apresentados. Leia os <a href="/termos-de-uso">Termos de uso</a> e a <a href="/privacidade">Política de privacidade</a>. Encontrou um erro? <a href="/contato?assunto=erro">Avise pelo contato</a> para que possamos corrigir.</p>
    </div>
    <div class="footer-inner">
      <span>© <?= date('Y') ?> Vibe2000 · site gratuito · resultados estimados</span>
      <nav aria-label="Institucional">
        <a href="/sobre">Sobre</a><a href="/contato">Contato</a><a href="/privacidade">Privacidade</a><a href="/termos-de-uso">Termos de uso</a>
        <button type="button" class="footer-link" id="cookie-settings-link">Preferências de cookies</button>
      </nav>
    </div>
  </footer>

  <section class="cookie-banner" id="cookie-banner" role="dialog" aria-labelledby="cookie-title" hidden>
    <div class="cookie-inner">
      <div class="cookie-text">
        <h2 id="cookie-title">Este site usa cookies</h2>
        <p>Usamos cookies necessários para o site funcionar, um contador de visitas próprio e anônimo e, com a sua permissão, cookies de publicidade personalizada (Google AdSense) para manter o Vibe2000 gratuito. Você pode mudar sua escolha quando quiser em "Preferências de cookies", no rodapé. <a href="/privacidade">Saiba mais</a>.</p>
        <div class="cookie-options" id="cookie-options" hidden>
          <label class="check"><input type="checkbox" checked disabled> <span><b>Necessários</b> · funcionamento, segurança e contagem anônima de visitas (sempre ativos)</span></label>
          <label class="check"><input type="checkbox" id="cookie-advertising"> <span><b>Publicidade personalizada</b> · anúncios do Google de acordo com seus interesses</span></label>
        </div>
      </div>
      <div class="cookie-buttons">
        <button type="button" class="action-button" id="cookie-accept-all">Aceitar todos</button>
        <button type="button" class="secondary-button" id="cookie-necessary-only">Só os necessários</button>
        <button type="button" class="secondary-button" id="cookie-customize">Personalizar</button>
        <button type="button" class="action-button" id="cookie-save" hidden>Salvar escolha</button>
      </div>
    </div>
  </section>

  <script src="<?= e(asset('js/site.js')) ?>" defer></script>
  <script src="<?= e(asset('js/install-app.js')) ?>" defer></script>
  <?php if ($usesCalculators): ?>
    <script src="<?= e(asset('js/calculators.js')) ?>" defer></script>
  <?php endif; ?>
</body>

</html>