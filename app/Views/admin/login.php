<?php

/**
 * Login do painel: /painel
 *
 * @var string|null $error
 * @var bool $canRemember   ADMIN_REMEMBER_KEY preenchida no .env
 * @var int $rememberDays
 */
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Entrar</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <!-- PWA do painel: a tela de login também pode ser instalada -->
  <link rel="manifest" href="/manifest-painel.webmanifest">
  <link rel="apple-touch-icon" href="/icons/painel-apple-touch.png">
  <meta name="apple-mobile-web-app-title" content="Painel V2K">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#1c2b24">
  <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
  <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>

<body>
  <main class="admin-login">
    <form class="admin-login-card" method="post" action="/painel">
      <?= csrf_field() ?>
      <div class="logo admin-logo"><?= \App\Core\View::partial("logo-mark") ?>Vibe2000 <small>painel</small></div>
      <?php if ($error): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
      <div class="field"><label for="admin-user">Usuário</label><input id="admin-user" name="user" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required></div>
      <div class="field"><label for="admin-password">Senha</label><input id="admin-password" name="password" type="password" autocomplete="current-password" required></div>
      <?php if ($canRemember): ?>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> Manter conectado</label>
      <?php endif; ?>
      <button type="submit" class="action-button">entrar</button>
      <a class="secondary-button" href="/">voltar ao site</a>
    </form>
  </main>
</body>

</html>