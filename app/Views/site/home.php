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
 * @var array $mostReadNews
 */
use App\Core\View;
use App\Services\Ads;
use App\Services\Content;

[$mainArticle] = $news + [null];
$readyCount = count(array_filter($tools, fn (array $tool) => $tool['ready']));
?>
<main class="page">
  <!-- Faixa de indicadores (Bitcoin, CDI, combustíveis, poupança, Focus): preenchida pelo site.js a partir de /api/indicadores -->
  <div class="trending ticker" aria-label="Indicadores">
    <b>INDICADORES</b>
    <div class="ticker-viewport">
      <div class="ticker-track" id="market-ticker"><span class="ticker-loading">carregando indicadores…</span></div>
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
            <!-- Destaque principal: calculadora de viagem (mesmo visual do quadro da lateral, maior) -->
            <a class="trip-hero" href="/calculadoras/custo-de-viagem">
              <svg class="trip-hero-map" viewBox="0 0 560 250" aria-hidden="true">
                <rect width="560" height="250" fill="#e6efe9"/>
                <path d="M0 175 Q110 140 220 182 T440 160 T560 190 V250 H0 Z" fill="#cfe3f0"/>
                <path d="M30 50 H230 M300 30 V140 M380 70 H540 M80 115 H190 M420 120 V210" stroke="#ffffff" stroke-width="9" stroke-linecap="round"/>
                <path class="trip-promo-route trip-hero-route" d="M60 68 C140 68 160 140 240 130 S380 60 496 166" fill="none" stroke="#0e6b4f" stroke-width="9" stroke-linecap="round"/>
                <circle cx="60" cy="68" r="14" fill="#0e6b4f" stroke="#ffffff" stroke-width="5"/>
                <circle class="trip-hero-toll" cx="300" cy="111" r="12" fill="#f0b429" stroke="#1d1600" stroke-width="3"/>
                <circle cx="496" cy="166" r="14" fill="#b3261e" stroke="#ffffff" stroke-width="5"/>
              </svg>
              <span class="trip-promo-tag">NOVO</span>
              <span class="trip-hero-text">
                <span class="news-meta">Calculadora de viagem</span>
                <h2>Quanto vai custar sua viagem?</h2>
                <p>Digite a partida e o destino: veja a rota no mapa, o gasto com combustível e os pedágios do caminho. Divida entre os amigos.</p>
                <span class="trip-promo-button"><svg class="trip-promo-car" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11h.5A1.5 1.5 0 0 1 21 12.5V17a1 1 0 0 1-1 1h-1a2 2 0 0 1-4 0H9a2 2 0 0 1-4 0H4a1 1 0 0 1-1-1v-4.5A1.5 1.5 0 0 1 4.5 11H5zm2.1 0h9.8l-1.2-3.6a.6.6 0 0 0-.6-.4H8.9a.6.6 0 0 0-.6.4L7.1 11zM7 15.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5zm10 0a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5z"/></svg> Calcular minha viagem</span>
              </span>
            </a>
            <div class="featured-side">
              <!-- Índices econômicos (Banco Central), preenchidos pelo site.js a partir de /api/indices -->
              <section class="indicators-box" id="indicators" aria-live="polite">
                <h3>Índices econômicos</h3>
                <table class="indicators-table">
                  <thead><tr><th></th><th>Último</th><th>12 meses</th></tr></thead>
                  <tbody>
                    <tr data-indicator="ipca"><th scope="row">IPCA <small>inflação oficial</small></th><td class="indicator-current">…</td><td class="indicator-year">…</td></tr>
                    <tr data-indicator="igpm"><th scope="row">IGP-M <small>reajuste de aluguel</small></th><td class="indicator-current">…</td><td class="indicator-year">…</td></tr>
                    <tr data-indicator="selic"><th scope="row">Selic <small>juros básicos</small></th><td class="indicator-current">…</td><td class="indicator-year">…</td></tr>
                  </tbody>
                </table>
                <p class="indicators-source">Fonte: Banco Central · <a href="/calculadoras/juros-compostos">simular rendimento</a></p>
              </section>
              <!-- Atalho: consulta da desvalorização na Tabela FIPE -->
              <a class="fipe-promo" href="/calculadoras/depreciacao-veiculo">
                <svg class="fipe-promo-chart" viewBox="0 0 64 40" aria-hidden="true"><polyline points="4,26 18,12 32,18 46,24 60,30" fill="none" stroke="#0e6b4f" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="18" cy="12" r="3.5" fill="#f0b429"/><circle cx="60" cy="30" r="3.5" fill="#b3261e"/></svg>
                <span><b>Seu carro desvalorizou quanto?</b><small>Histórico de 5 anos na Tabela FIPE, com gráfico</small></span>
              </a>
              <!-- Atalho: correção de valores por IPCA, IGP-M, INPC, Selic, CDI e poupança -->
              <a class="fipe-promo correction-promo" href="/calculadoras/correcao-monetaria">
                <svg class="fipe-promo-chart" viewBox="0 0 64 40" aria-hidden="true"><rect x="8" y="24" width="10" height="10" rx="2" fill="#cfe3d8"/><rect x="27" y="16" width="10" height="18" rx="2" fill="#7ab89b"/><rect x="46" y="6" width="10" height="28" rx="2" fill="#0e6b4f"/><path d="M9 18 L28 10 L46 4" fill="none" stroke="#f0b429" stroke-width="3" stroke-linecap="round"/></svg>
                <span><b>Quanto vale hoje com a inflação?</b><small>Corrija um valor por IPCA, IGP-M, INPC, Selic, CDI ou poupança</small></span>
              </a>
            </div>
          </section>
        <?php endif; ?>

        <div class="section-head"><h2>Calculadoras mais usadas</h2><span><?= $readyCount ?> calculadoras · <?= count($news) ?> artigos</span></div>
        <div class="tool-grid">
          <?php foreach ($popularTools as $tool): ?><?= View::partial('tool-card', ['tool' => $tool]) ?><?php endforeach; ?>
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
      <!-- Destaque: álcool ou gasolina (desenho em SVG, sem imagem externa) -->
      <a class="side-box trip-promo fuel-promo" href="/calculadoras/alcool-gasolina">
        <svg class="trip-promo-map" viewBox="0 0 280 110" aria-hidden="true">
          <rect width="280" height="110" rx="10" fill="#e6efe9"/>
          <rect x="0" y="92" width="280" height="18" fill="#d7ded9"/>
          <!-- bomba de etanol -->
          <rect x="24" y="34" width="52" height="60" rx="7" fill="#0e6b4f"/>
          <rect x="32" y="41" width="36" height="14" rx="3" fill="#ffffff"/>
          <text x="50" y="84" text-anchor="middle" font-size="20" font-weight="800" fill="#ffffff" font-family="sans-serif">E</text>
          <path d="M76 52 q14 0 14 14 v14" fill="none" stroke="#0e6b4f" stroke-width="4" stroke-linecap="round"/>
          <!-- bomba de gasolina -->
          <rect x="204" y="34" width="52" height="60" rx="7" fill="#b3261e"/>
          <rect x="212" y="41" width="36" height="14" rx="3" fill="#ffffff"/>
          <text x="230" y="84" text-anchor="middle" font-size="20" font-weight="800" fill="#ffffff" font-family="sans-serif">G</text>
          <path d="M204 52 q-14 0 -14 14 v14" fill="none" stroke="#b3261e" stroke-width="4" stroke-linecap="round"/>
          <!-- régua da regra dos 70% -->
          <rect x="100" y="58" width="80" height="16" rx="8" fill="#ffffff" stroke="#c4cfc8"/>
          <rect class="fuel-promo-bar" x="100" y="58" width="56" height="16" rx="8" fill="#f0b429"/>
          <line x1="156" y1="52" x2="156" y2="80" stroke="#1d1600" stroke-width="2" stroke-dasharray="3 2"/>
          <text x="156" y="47" text-anchor="middle" font-size="12" font-weight="800" fill="#1d1600" font-family="sans-serif">70%</text>
        </svg>
        <span class="trip-promo-tag">NO POSTO</span>
        <h3>Álcool ou gasolina?</h3>
        <p>Digite os preços do posto e descubra na hora qual compensa abastecer, pela regra dos 70%.</p>
        <span class="trip-promo-button"><svg class="trip-promo-car" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 2h9a1 1 0 0 1 1 1v8h1a2 2 0 0 1 2 2v4.5a1 1 0 0 0 2 0V9.4l-2.7-2.7 1.4-1.4 2.7 2.7a2 2 0 0 1 .6 1.4v8.1a3 3 0 0 1-6 0V13h-1v7h1v2H3v-2h1V3a1 1 0 0 1 1-1zm1 2v6h7V4H6z"/></svg> Ver qual compensa</span>
      </a>
      <!-- Chamada do calendário de feriados, com o próximo feriado nacional numa "folhinha" -->
      <a class="side-box holiday-promo" href="/feriados/<?= date('Y') ?>">
        <div class="holiday-promo-top">
          <?php if ($nextHoliday !== null): ?>
            <?php $nextDate = new DateTimeImmutable($nextHoliday['holiday']['date']); ?>
            <span class="holiday-promo-icon" aria-hidden="true"><span><?= ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][(int) $nextDate->format('n') - 1] ?></span><b><?= $nextDate->format('j') ?></b></span>
            <div>
              <h3>Próximo feriado: <?= e($nextHoliday['holiday']['names'][0]) ?></h3>
              <p><?= e($nextHoliday['holiday']['weekday']) ?>, <?= $nextHoliday['daysUntil'] === 0 ? 'é hoje!' : ($nextHoliday['daysUntil'] === 1 ? 'é amanhã' : 'daqui a ' . $nextHoliday['daysUntil'] . ' dias') ?><?= $nextHoliday['holiday']['bridge'] !== '' ? ' · ' . e($nextHoliday['holiday']['bridge']) : '' ?></p>
            </div>
          <?php else: ?>
            <div><h3>Calendário de feriados</h3></div>
          <?php endif; ?>
        </div>
        <p>Todos os feriados de <?= date('Y') ?>: nacionais, do seu estado e da sua capital, com os feriadões do ano.</p>
        <span class="trip-promo-button">📅 Ver calendário de feriados</span>
      </a>
      <section class="side-box install-side-box" data-install-area hidden>
        <h3>Vibe2000 no celular</h3>
        <div class="install-side-row">
          <img src="/icons/site-192.png" alt="" width="48" height="48" loading="lazy">
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
    </aside>
  </div>
</main>
