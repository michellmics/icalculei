<?php
/**
 * Página inicial (vitrine do portal) e resultados da busca.
 *
 * @var array $categories
 * @var string $searchTerm
 * @var string|null $activeCategory
 * @var bool $isSearching
 * @var array $searchResults
 * @var array $news
 * @var array $tools
 * @var array $popularTools
 * @var array $trendingTools
 * @var array $trendingStrip
 * @var array $mostReadNews
 */
use App\Core\View;
use App\Services\Ads;
use App\Services\Content;

[$mainArticle] = $news + [null];
$readyCount = count(array_filter($tools, fn (array $tool) => $tool['ready']));
?>
<main class="page">
  <div class="trending" aria-label="Em alta">
    <b>EM ALTA</b>
    <div class="trending-links">
      <?php foreach ($trendingStrip as $item): ?>
        <?php if ($item['type'] === 'tool' && ($tool = Content::tool($item['id'])) !== null): ?>
          <a href="/calculadoras/<?= e($tool['id']) ?>"><?= e($tool['symbol'] . ' ' . $tool['name']) ?></a>
        <?php elseif ($item['type'] === 'news' && ($article = Content::article($item['id'])) !== null): ?>
          <a href="/noticias/<?= e($article['id']) ?>">📰 <?= e($article['title']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="portal-grid">
    <div class="portal-main">
      <?php if ($isSearching): ?>
        <div class="section-head">
          <h2><?= $searchTerm === '' ? e($categories[$activeCategory]) : count($searchResults) . ' resultado(s) para "' . e($searchTerm) . '"' ?></h2>
          <a class="pill-button" href="/">limpar</a>
        </div>
        <?php if ($searchResults === []): ?>
          <p class="empty-message">Nenhuma calculadora encontrada. Tente outra palavra, como "juros" ou "data".</p>
        <?php else: ?>
          <div class="tool-grid">
            <?php foreach ($searchResults as $tool): ?><?= View::partial('tool-card', ['tool' => $tool]) ?><?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($mainArticle !== null): ?>
          <section class="featured" aria-label="Destaques">
            <a class="featured-main" href="/noticias/<?= e($mainArticle['id']) ?>">
              <img class="news-cover" src="<?= e(news_image($mainArticle)) ?>" alt="<?= e($mainArticle['image_alt']) ?>" width="960" height="640" fetchpriority="high">
              <span class="featured-text">
                <span class="news-meta"><?= e($mainArticle['category']) ?> · <?= e(format_date($mainArticle['date'])) ?></span>
                <h2><?= e($mainArticle['title']) ?></h2>
                <p><?= e($mainArticle['summary']) ?></p>
              </span>
            </a>
            <div class="featured-side">
              <?php foreach (array_slice($news, 1, 3) as $article): ?>
                <a href="/noticias/<?= e($article['id']) ?>">
                  <span class="news-meta"><?= e($article['category']) ?> · <?= e(format_date($article['date'])) ?></span>
                  <h3><?= e($article['title']) ?></h3>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <div class="section-head"><h2>Calculadoras mais usadas</h2><span><?= $readyCount ?> calculadoras · <?= count($news) ?> artigos</span></div>
        <div class="tool-grid">
          <?php foreach ($popularTools as $tool): ?><?= View::partial('tool-card', ['tool' => $tool]) ?><?php endforeach; ?>
        </div>

        <div class="section-head"><h2>Últimas notícias</h2><a class="pill-button" href="/noticias">ver todas</a></div>
        <div class="news-grid news-grid-home">
          <?php foreach (array_slice($news, 0, 4) as $article): ?><?= View::partial('news-card', ['article' => $article]) ?><?php endforeach; ?>
        </div>

        <div class="section-head"><h2>Todas as calculadoras</h2><span>por categoria</span></div>
        <div class="directory">
          <?php foreach ($categories as $categoryKey => $categoryName): ?>
            <?php $toolsInCategory = array_filter($tools, fn (array $tool) => $tool['category'] === $categoryKey); ?>
            <section class="directory-box">
              <h3><?= e($categoryName) ?><span><?= count($toolsInCategory) ?></span></h3>
              <ul>
                <?php foreach ($toolsInCategory as $tool): ?>
                  <li><a href="/calculadoras/<?= e($tool['id']) ?>"><?= e($tool['name']) ?><?= $tool['ready'] ? '' : ' (em breve)' ?></a></li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="portal-side">
      <section class="side-box exchange-box" id="exchange-rates" aria-live="polite">
        <h3>Cotação do dia</h3>
        <div class="exchange-list">
          <div class="exchange-row" data-currency="USD">
            <span class="exchange-name">💵 Dólar</span>
            <b class="exchange-value">carregando…</b>
            <span class="exchange-change"></span>
            <small class="exchange-range"></small>
          </div>
          <div class="exchange-row" data-currency="EUR">
            <span class="exchange-name">💶 Euro</span>
            <b class="exchange-value">carregando…</b>
            <span class="exchange-change"></span>
            <small class="exchange-range"></small>
          </div>
        </div>
        <p class="exchange-footer"><span id="exchange-updated">buscando cotação…</span> · <a href="/calculadoras/moedas">converter valores</a></p>
      </section>
      <section class="side-box install-side-box" data-install-area hidden>
        <h3>Vibe2000 no celular</h3>
        <div class="install-side-row">
          <img src="/icons/site-192.png" alt="" width="48" height="48">
          <p>Instale o app: abre num toque, em tela cheia, e as calculadoras funcionam até sem internet.</p>
        </div>
        <button type="button" class="action-button" data-install-app hidden>📲 Instalar o app</button>
      </section>
      <section class="side-box">
        <h3>Mais lidas</h3>
        <ol class="ranked-list">
          <?php foreach ($mostReadNews as $article): ?><li><a href="/noticias/<?= e($article['id']) ?>"><?= e($article['title']) ?></a></li><?php endforeach; ?>
        </ol>
      </section>
      <section class="side-box">
        <h3>Ferramentas em alta</h3>
        <ul class="link-list">
          <?php foreach ($trendingTools as $tool): ?>
            <?= View::partial('link-item', ['href' => '/calculadoras/' . $tool['id'], 'symbol' => $tool['symbol'], 'title' => $tool['name'], 'subtitle' => $tool['description']]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="side-box quick-box">
        <h3>Conta rápida</h3>
        <div class="field"><label for="quick-percent">Quanto é</label>
          <div class="sentence"><input id="quick-percent" inputmode="decimal" value="20" aria-label="Porcentagem">% de<input id="quick-value" inputmode="decimal" value="150" aria-label="Valor"></div>
        </div>
        <p class="quick-result">= <b id="quick-result">30</b></p>
      </section>
    </aside>
  </div>
</main>
