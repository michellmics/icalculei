<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Http;
use App\Core\Session;
use App\Core\View;
use App\Models\ContactMessage;
use App\Models\RateLimit;
use App\Services\Content;
use App\Services\Holidays;
use App\Services\StructuredData;

/**
 * Páginas públicas do site.
 */
class SiteController
{
    private const MAX_CONTACT_MESSAGES_PER_HOUR = 5;

    /**
     * Mostra uma página com o layout do site. $pageKey identifica a página para o contador de visitas.
     */
    private function render(string $viewName, array $data, string $pageKey, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        // Páginas públicas podem ficar 10 minutos no cache do Cloudflare (s-maxage), que responde muito
        // mais rápido que a hospedagem. O navegador sempre confere de novo (max-age=0).
        // Páginas com sessão (contato) não entram: o PHP já as marca como "não guardar".
        if ($statusCode === 200 && session_status() !== PHP_SESSION_ACTIVE) {
            header('Cache-Control: public, max-age=0, s-maxage=600');
        }
        // Tempo que o PHP levou para montar a página (aparece no DevTools → Network → Timing)
        header(sprintf('Server-Timing: app;dur=%.1f', (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000));
        $data += [
            'categories' => Content::categories(),
            'pageKey' => $pageKey,
            'metaDescription' => 'Calculadoras gratuitas de porcentagem, rescisão, férias, 13º, salário líquido, juros, IMC, datas e muito mais, com explicação passo a passo.',
            'canonicalPath' => '/',
            'structuredData' => [],
            'ogType' => 'website',
            'robotsMeta' => 'index, follow, max-image-preview:large',
            'searchTerm' => '',
            'activeCategory' => null,
        ];
        echo View::render($viewName, $data);
    }

    public function home(): void
    {
        $searchTerm = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 60);
        $requestedCategory = (string) ($_GET['categoria'] ?? '');
        $activeCategory = isset(Content::categories()[$requestedCategory]) ? $requestedCategory : null;
        $isSearching = $searchTerm !== '' || $activeCategory !== null;
        $showcase = Content::showcase();

        $this->render('site/home', [
            'pageTitle' => $activeCategory !== null && $searchTerm === ''
                ? 'Calculadoras de ' . Content::categories()[$activeCategory] . ' Online e Grátis | iCalculei'
                : 'Calculadoras Online Grátis: Trabalhistas, Financeiras e Mais | iCalculei',
            'robotsMeta' => $searchTerm !== '' ? 'noindex, follow' : 'index, follow, max-image-preview:large',
            'structuredData' => $isSearching ? [] : StructuredData::home(),
            'searchTerm' => $searchTerm,
            'activeCategory' => $activeCategory,
            'isSearching' => $isSearching,
            'searchResults' => $isSearching ? Content::searchTools($searchTerm, $activeCategory) : [],
            'news' => Content::news(),
            'tools' => Content::tools(),
            'popularTools' => Content::toolsByIds($showcase['popular_tools']),
            'trendingTools' => Content::toolsByIds($showcase['trending_tools']),
            'mostReadNews' => Content::newsByIds($showcase['most_read_news']),
            'canonicalPath' => $activeCategory !== null && $searchTerm === '' ? '/?categoria=' . $activeCategory : '/',
            'nextHoliday' => Holidays::next(),
        ], 'inicio');
    }

    public function tool(string $toolId): void
    {
        $tool = Content::tool($toolId);
        if ($tool === null) {
            $this->notFound();
            return;
        }

        $readyTools = array_filter(Content::tools(), fn (array $otherTool) => $otherTool['ready'] && $otherTool['id'] !== $toolId);
        $sameCategory = array_filter($readyTools, fn (array $otherTool) => $otherTool['category'] === $tool['category']);
        $otherCategories = array_diff_key($readyTools, $sameCategory);
        $relatedNews = array_values(array_filter(Content::news(), fn (array $article) => in_array($toolId, $article['related_tools'], true)));

        $this->render('site/tool', [
            'pageTitle' => tool_title($tool) . ' | iCalculei',
            'metaDescription' => $tool['lead'],
            'ogImage' => share_banner($tool['id']),
            'structuredData' => StructuredData::tool($tool, Content::categories()[$tool['category']] ?? ''),
            'tool' => $tool,
            'relatedTools' => array_slice(array_values($sameCategory + $otherCategories), 0, 6),
            'relatedNews' => array_slice($relatedNews ?: Content::news(), 0, 3),
            'canonicalPath' => '/calculadoras/' . $toolId,
        ], 'calculadora:' . $toolId);
    }

    public function newsList(): void
    {
        $allNews = Content::news();
        $newsCategories = array_values(array_unique(array_column($allNews, 'category')));
        $requestedCategory = (string) ($_GET['categoria'] ?? '');
        $activeNewsCategory = in_array($requestedCategory, $newsCategories, true) ? $requestedCategory : null;

        $this->render('site/news-list', [
            'pageTitle' => 'Notícias sobre Dinheiro, Trabalho e Saúde | iCalculei',
            'metaDescription' => 'Explicações sobre dinheiro, trabalho, saúde e o dia a dia, com a calculadora certa do lado.',
            'newsCategories' => $newsCategories,
            'activeNewsCategory' => $activeNewsCategory,
            'news' => array_values(array_filter($allNews, fn (array $article) => $activeNewsCategory === null || $article['category'] === $activeNewsCategory)),
            'canonicalPath' => '/noticias',
        ], 'noticias');
    }

    public function article(string $articleId): void
    {
        $article = Content::article($articleId);
        if ($article === null) {
            $this->notFound();
            return;
        }

        $this->render('site/article', [
            'pageTitle' => $article['title'] . ' | iCalculei',
            'metaDescription' => $article['summary'],
            'ogType' => 'article',
            'ogImage' => url(news_image($article, false)),
            'structuredData' => StructuredData::article($article, url(news_image($article, false))),
            'article' => $article,
            'relatedTools' => Content::toolsByIds($article['related_tools']),
            'moreNews' => array_slice(array_values(array_filter(Content::news(), fn (array $otherArticle) => $otherArticle['id'] !== $articleId)), 0, 4),
            'canonicalPath' => '/noticias/' . $articleId,
        ], 'noticia:' . $articleId);
    }

    public function about(): void
    {
        $this->render('pages/about', ['pageTitle' => 'Sobre o iCalculei | Calculadoras Online Grátis', 'metaDescription' => 'Conheça o iCalculei: calculadoras e conversores gratuitos, feitos para resolver contas do dia a dia com explicação clara.', 'canonicalPath' => '/sobre'], 'sobre');
    }

    public function terms(): void
    {
        $this->render('pages/terms', ['pageTitle' => 'Termos de Uso | iCalculei', 'metaDescription' => 'Termos de uso do iCalculei: regras de uso do site, limites de responsabilidade e natureza estimativa dos resultados.', 'canonicalPath' => '/termos-de-uso'], 'termos');
    }

    public function privacy(): void
    {
        $this->render('pages/privacy', ['pageTitle' => 'Política de Privacidade | iCalculei', 'metaDescription' => 'Como o iCalculei trata dados pessoais, cookies e anúncios, de acordo com a LGPD.', 'canonicalPath' => '/privacidade'], 'privacidade');
    }

    public function contact(): void
    {
        Session::start();
        $this->render('pages/contact', [
            'pageTitle' => 'Contato | iCalculei',
            'metaDescription' => 'Fale com o iCalculei: sugestões, erros em calculadoras, anúncios e pedidos sobre dados pessoais.',
            'canonicalPath' => '/contato',
            'subjects' => ContactMessage::SUBJECTS,
            'tools' => Content::tools(),
            'errors' => Session::pullFlash('contact_errors', []),
            'oldInput' => Session::pullFlash('contact_old_input', []),
            'sent' => Session::pullFlash('contact_sent', false),
            'selectedTool' => (string) ($_GET['calculadora'] ?? ''),
            'selectedSubject' => isset(ContactMessage::SUBJECTS[$_GET['assunto'] ?? '']) ? (string) $_GET['assunto'] : 'erro',
        ], 'contato');
    }

    public function sendContact(): void
    {
        if (!Csrf::isValid($_POST['_csrf_token'] ?? null)) {
            Session::flash('contact_errors', ['form' => 'A página expirou. Escreva a mensagem de novo.']);
            Http::redirect('/contato');
        }

        // Campo invisível: pessoas não preenchem, robôs de spam sim. Finge que deu certo.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            Session::flash('contact_sent', true);
            Http::redirect('/contato');
        }

        $input = [
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80),
            'email' => mb_substr(strtolower(trim((string) ($_POST['email'] ?? ''))), 0, 120),
            'subject' => (string) ($_POST['subject'] ?? ''),
            'tool_id' => (string) ($_POST['tool_id'] ?? ''),
            'message' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 2000),
        ];

        $errors = [];
        if ($input['name'] === '') {
            $errors['name'] = 'Escreva seu nome.';
        }
        if (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Digite um e-mail válido para podermos responder.';
        }
        if (!isset(ContactMessage::SUBJECTS[$input['subject']])) {
            $errors['subject'] = 'Escolha um assunto.';
        }
        if (mb_strlen($input['message']) < 10) {
            $errors['message'] = 'Escreva uma mensagem com pelo menos 10 caracteres.';
        }

        $limitKey = RateLimit::key('contact', $_SERVER['REMOTE_ADDR'] ?? '');
        if (RateLimit::tooManyAttempts($limitKey, self::MAX_CONTACT_MESSAGES_PER_HOUR)) {
            $errors['form'] = 'Você enviou muitas mensagens em pouco tempo. Tente de novo mais tarde.';
        }

        if ($errors !== []) {
            Session::flash('contact_errors', $errors);
            Session::flash('contact_old_input', $input);
            Http::redirect('/contato');
        }

        RateLimit::hit($limitKey, 60);
        // Só guarda a calculadora se o assunto for "erro" e o id existir de verdade
        $toolId = $input['subject'] === 'erro' && Content::tool($input['tool_id']) !== null ? $input['tool_id'] : null;
        ContactMessage::create(array_merge($input, ['tool_id' => $toolId]));
        $this->notifyByEmail($input, $toolId);

        Session::flash('contact_sent', true);
        Http::redirect('/contato');
    }

    /**
     * Avisa por e-mail que chegou mensagem (se CONTACT_EMAIL estiver no .env).
     * A mensagem já está salva no painel mesmo se o e-mail falhar.
     */
    private function notifyByEmail(array $input, ?string $toolId): void
    {
        $contactEmail = (string) config('contact_email');
        if ($contactEmail === '' || config('env') === 'local') {
            return;
        }
        $subjectLine = 'iCalculei · ' . ContactMessage::SUBJECTS[$input['subject']] . ($toolId ? ' · ' . $toolId : '');
        $body = "Nova mensagem no iCalculei\n\nNome: {$input['name']}\nE-mail: {$input['email']}\n\n{$input['message']}\n\nVeja no painel: " . url('/painel/mensagens');
        // O e-mail da pessoa vai no Reply-To (sem quebras de linha, contra injeção de cabeçalho)
        $replyTo = str_replace(["\r", "\n"], '', $input['email']);
        if (!@mail($contactEmail, '=?UTF-8?B?' . base64_encode($subjectLine) . '?=', $body, "Content-Type: text/plain; charset=UTF-8\r\nReply-To: {$replyTo}")) {
            ErrorHandler::log('Falha ao enviar e-mail de aviso de contato');
        }
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        // Mapa do site para o Google Search Console: endereço + data da última mudança (lastmod)
        $news = Content::news();
        $readyTools = array_filter(Content::tools(), fn (array $tool) => $tool['ready']);
        $latestReview = max(array_merge(array_column($readyTools, 'reviewed'), array_column($news, 'date')));
        $latestNews = $news[0]['date'] ?? $latestReview;

        $pages = [['/', $latestReview], ['/noticias', $latestNews]];
        foreach (array_keys(Content::categories()) as $categoryKey) {
            $pages[] = ['/?categoria=' . $categoryKey, $latestReview];
        }
        foreach ($readyTools as $tool) {
            $pages[] = ['/calculadoras/' . $tool['id'], $tool['reviewed']];
        }
        foreach ($news as $article) {
            $pages[] = ['/noticias/' . $article['id'], $article['date']];
        }
        // Calendário de feriados: ano atual e o próximo, nacional, por mês e por estado
        foreach ([(int) date('Y'), (int) date('Y') + 1] as $holidayYear) {
            $pages[] = ['/feriados/' . $holidayYear, Holidays::reviewed()];
            foreach (Holidays::MONTH_SLUGS as $monthSlug) {
                $pages[] = ['/feriados/' . $holidayYear . '/' . $monthSlug, Holidays::reviewed()];
            }
            foreach (array_keys(Holidays::states()) as $stateCode) {
                $pages[] = ['/feriados/' . $holidayYear . '/' . $stateCode, Holidays::reviewed()];
            }
        }
        foreach (['/sobre', '/contato', '/termos-de-uso', '/privacidade'] as $institutionalPath) {
            $pages[] = [$institutionalPath, null];
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as [$path, $lastModified]) {
            echo '  <url><loc>' . e(url($path)) . '</loc>' . ($lastModified ? '<lastmod>' . e($lastModified) . '</lastmod>' : '') . '</url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /painel\nDisallow: /api/\nDisallow: /l/\n\nSitemap: " . url('/sitemap.xml') . "\n";
    }

    public function notFound(): void
    {
        $this->render('site/not-found', ['pageTitle' => 'Página não encontrada | iCalculei', 'robotsMeta' => 'noindex, follow', 'tools' => Content::toolsByIds(Content::showcase()['popular_tools'])], 'inicio', 404);
    }
}
