<?php
/**
 * Página de uma notícia: /noticias/{id}
 *
 * @var array $article
 * @var array $relatedTools
 * @var array $moreNews
 */
use App\Core\View;
use App\Services\Ads;
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em"><a href="/">Início</a> › <a href="/noticias">Notícias</a> › <span><?= e($article['category']) ?></span></nav>
  <div class="article-page">
    <article class="article-body">
      <h1><?= e($article['title']) ?></h1>
      <p class="lead"><?= e($article['summary']) ?></p>
      <div class="article-byline">Redação Vibe2000 · <?= e(format_date($article['date'])) ?> · <?= e($article['category']) ?></div>
      <figure class="article-photo">
        <img src="<?= e(news_image($article)) ?>" alt="<?= e($article['image_alt']) ?>" width="960" height="640" fetchpriority="high">
        <figcaption><?= e($article['image_credit']) ?></figcaption>
      </figure>

      <?php foreach ($article['body'] as $block): ?>
        <?php if (is_string($block)): ?>
          <p><?= e($block) ?></p>
        <?php elseif (isset($block['heading'])): ?>
          <h2><?= e($block['heading']) ?></h2>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($relatedTools !== []): ?>
        <div class="article-tools">
          <b>Faça as contas:</b>
          <div>
            <?php foreach ($relatedTools as $tool): ?>
              <a class="pill-button" href="/calculadoras/<?= e($tool['id']) ?>"><?= e($tool['symbol'] . ' ' . $tool['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </article>
    <aside class="side-column">
      <section class="side-box">
        <h3>Leia também</h3>
        <ul class="link-list">
          <?php foreach ($moreNews as $otherArticle): ?>
            <?= View::partial('link-item', ['href' => '/noticias/' . $otherArticle['id'], 'image' => news_image($otherArticle), 'title' => $otherArticle['title'], 'subtitle' => format_date($otherArticle['date'])]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
</main>
