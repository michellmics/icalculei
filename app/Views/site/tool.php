<?php

/**
 * Página de uma calculadora: /calculadoras/{id}
 * Os campos são montados pelo calculators.js; o texto explicativo vem do servidor (bom para o Google).
 *
 * @var array $tool
 * @var array $categories
 * @var array $relatedTools
 * @var array $relatedNews
 */

use App\Core\View;
use App\Services\Ads;
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em">
    <a href="/">Início</a> › <a href="/?categoria=<?= e($tool['category']) ?>"><?= e($categories[$tool['category']]) ?></a> › <span><?= e($tool['name']) ?></span>
  </nav>
  <h1 class="tool-title"><?= e($tool['name'] === 'IMC' ? 'Calculadora de IMC' : $tool['name']) ?></h1>
  <p class="tool-lead"><?= e($tool['lead']) ?></p>
  <span class="reviewed"><?= $tool['reviewed'] !== '' ? '✓ revisada em ' . e(format_date($tool['reviewed'])) : 'em preparação' ?></span>

  <div class="two-columns">
    <div class="main-column">
      <?php if ($tool['ready']): ?>
        <h2 class="visually-hidden">Calcule aqui</h2>
        <section class="calculator" id="calculator" data-tool="<?= e($tool['id']) ?>"<?php if ($tool['id'] === 'depreciacao-veiculo' && config('fipe_proxy_url') !== ''): ?> data-fipe-worker="<?= e(config('fipe_proxy_url')) ?>"<?php endif; ?> aria-live="polite">
          <noscript>
            <div class="coming-soon">Esta calculadora precisa do JavaScript ativado no navegador.</div>
          </noscript>
        </section>
        <!-- Compartilhar: o calculators.js mostra a barra e monta o link com os números preenchidos -->
        <div class="share-bar" id="share-bar" hidden>
          <span>Gostou da conta?</span>
          <button type="button" class="share-whatsapp" id="share-whatsapp"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.1 5.1 0 0 0 1.1 2.7 11.6 11.6 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>Compartilhar no WhatsApp</button>
          <button type="button" class="secondary-button" id="share-copy">Copiar link</button>
        </div>
        <?php // Sem "defer" de propósito: a calculadora é desenhada antes do texto de baixo aparecer, e a página não "pula" (CLS) 
        ?>
        <script src="<?= e(asset('js/calculators.js')) ?>"></script>
      <?php else: ?>
        <section class="calculator">
          <div class="coming-soon"><b>Em breve.</b> Esta calculadora ainda está em preparação.</div>
        </section>
      <?php endif; ?>


      <article class="explainer">
        <?= $tool['explainer'] /* HTML do content/tools.php, escrito por nós (não vem de usuário) */ ?>
      </article>

      <p class="field-hint">Encontrou algum problema nesta calculadora? <a href="/contato?assunto=erro&amp;calculadora=<?= e($tool['id']) ?>">Avise a gente</a>.</p>
    </div>

    <aside class="side-column">
      <section class="side-box">
        <h3>Ferramentas parecidas</h3>
        <ul class="link-list">
          <?php foreach ($relatedTools as $relatedTool): ?>
            <?= View::partial('link-item', ['href' => '/calculadoras/' . $relatedTool['id'], 'symbol' => $relatedTool['symbol'], 'title' => $relatedTool['name'], 'subtitle' => '']) ?>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="side-box">
        <h3>Notícias </h3>
        <ul class="link-list">
          <?php foreach ($relatedNews as $article): ?>
            <?= View::partial('link-item', ['href' => '/noticias/' . $article['id'], 'image' => news_image_small($article), 'title' => $article['title'], 'subtitle' => format_date($article['date'])]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
</main>