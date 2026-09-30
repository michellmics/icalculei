<?php
/**
 * Cartão de uma notícia.
 *
 * @var array $article
 */
?>
<a class="news-card" href="/noticias/<?= e($article['id']) ?>">
  <img class="news-cover" src="<?= e(news_image($article)) ?>" srcset="<?= e(news_image_srcset($article)) ?>" sizes="(max-width: 600px) 92vw, (max-width: 900px) 46vw, 400px" alt="<?= e($article['image_alt']) ?>" width="960" height="640" loading="lazy">
  <span class="news-card-body">
    <span class="news-meta"><?= e($article['category']) ?> · <?= e(format_date($article['date'])) ?></span>
    <h3><?= e($article['title']) ?></h3>
    <p><?= e($article['summary']) ?></p>
  </span>
</a>
