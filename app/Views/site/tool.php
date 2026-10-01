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
        <section class="calculator" id="calculator" data-tool="<?= e($tool['id']) ?>" aria-live="polite">
          <noscript>
            <div class="coming-soon">Esta calculadora precisa do JavaScript ativado no navegador.</div>
          </noscript>
        </section>
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