<?php
/**
 * Lista de notícias: /noticias
 *
 * @var array $news
 * @var array $newsCategories
 * @var string|null $activeNewsCategory
 */
use App\Core\View;
use App\Services\Ads;
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em"><a href="/">Início</a> › <span>Notícias</span></nav>
  <h1 class="tool-title">Notícias e artigos</h1>
  <p class="tool-lead">Explicações sobre dinheiro, trabalho, saúde e o dia a dia, sempre com a calculadora certa do lado.</p>
  <div class="popular">
    <a class="pill-button" href="/noticias" aria-pressed="<?= $activeNewsCategory === null ? 'true' : 'false' ?>">Todas</a>
    <?php foreach ($newsCategories as $category): ?>
      <a class="pill-button" href="/noticias?categoria=<?= e(rawurlencode($category)) ?>" aria-pressed="<?= $activeNewsCategory === $category ? 'true' : 'false' ?>"><?= e($category) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="two-columns">
    <div class="news-list">
      <?php foreach ($news as $article): ?><?= View::partial('news-card', ['article' => $article]) ?><?php endforeach; ?>
    </div>
    <aside class="side-column"></aside>
  </div>
</main>
