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
  <?= Ads::slot('top_banner') ?>
  <nav class="breadcrumb" aria-label="Você está em">
    <a href="/">Início</a> › <a href="/?categoria=<?= e($tool['category']) ?>"><?= e($categories[$tool['category']]) ?></a> › <span><?= e($tool['name']) ?></span>
  </nav>
  <h1 class="tool-title"><?= e($tool['name'] === 'IMC' ? 'Calculadora de IMC' : $tool['name']) ?></h1>
  <p class="tool-lead"><?= e($tool['lead']) ?></p>
  <span class="reviewed"><?= $tool['reviewed'] !== '' ? '✓ revisada em ' . e(format_date($tool['reviewed'])) : 'em preparação' ?></span>

  <div class="two-columns">
    <div class="main-column">
      <?php if ($tool['ready']): ?>
        <section class="calculator" id="calculator" data-tool="<?= e($tool['id']) ?>" aria-live="polite">
          <noscript><div class="coming-soon">Esta calculadora precisa do JavaScript ativado no navegador.</div></noscript>
        </section>
      <?php else: ?>
        <section class="calculator"><div class="coming-soon"><b>Em breve.</b> Esta calculadora ainda está em preparação.</div></section>
      <?php endif; ?>

      <?= Ads::slot('tool_after_calculator') ?>

      <article class="explainer">
        <?= $tool['explainer'] /* HTML do content/tools.php, escrito por nós (não vem de usuário) */ ?>
      </article>

      <?= Ads::slot('tool_bottom') ?>
      <p class="field-hint">Encontrou algum problema nesta calculadora? <a href="/contato?assunto=erro&amp;calculadora=<?= e($tool['id']) ?>">Avise a gente</a>.</p>
    </div>

    <aside class="side-column">
      <?= Ads::slot('tool_sidebar') ?>
      <section class="side-box">
        <h3>Ferramentas parecidas</h3>
        <ul class="link-list">
          <?php foreach ($relatedTools as $relatedTool): ?>
            <?= View::partial('link-item', ['href' => '/calculadoras/' . $relatedTool['id'], 'symbol' => $relatedTool['symbol'], 'title' => $relatedTool['name'], 'subtitle' => '']) ?>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="side-box">
        <h3>Notícias</h3>
        <ul class="link-list">
          <?php foreach ($relatedNews as $article): ?>
            <?= View::partial('link-item', ['href' => '/noticias/' . $article['id'], 'image' => news_image($article), 'title' => $article['title'], 'subtitle' => format_date($article['date'])]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
</main>
