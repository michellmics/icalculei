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
        $data += [
            'categories' => Content::categories(),
            'pageKey' => $pageKey,
            'metaDescription' => 'Calculadoras gratuitas de porcentagem, rescisão, férias, 13º, salário líquido, juros, IMC, datas e muito mais, com explicação passo a passo.',
            'canonicalPath' => '/',
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
            'pageTitle' => 'Vibe2000 · Calculadoras, conversores e notícias',
            'searchTerm' => $searchTerm,
            'activeCategory' => $activeCategory,
            'isSearching' => $isSearching,
            'searchResults' => $isSearching ? Content::searchTools($searchTerm, $activeCategory) : [],
            'news' => Content::news(),
            'tools' => Content::tools(),
            'popularTools' => Content::toolsByIds($showcase['popular_tools']),
            'trendingTools' => Content::toolsByIds($showcase['trending_tools']),
            'trendingStrip' => $showcase['trending_strip'],
            'mostReadNews' => Content::newsByIds($showcase['most_read_news']),
            'canonicalPath' => '/',
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
            'pageTitle' => ($tool['name'] === 'IMC' ? 'Calculadora de IMC' : $tool['name']) . ' · Vibe2000',
            'metaDescription' => $tool['lead'],
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
            'pageTitle' => 'Notícias e artigos · Vibe2000',
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
            'pageTitle' => $article['title'] . ' · Vibe2000',
            'metaDescription' => $article['summary'],
            'article' => $article,
            'relatedTools' => Content::toolsByIds($article['related_tools']),
            'moreNews' => array_slice(array_values(array_filter(Content::news(), fn (array $otherArticle) => $otherArticle['id'] !== $articleId)), 0, 4),
            'canonicalPath' => '/noticias/' . $articleId,
        ], 'noticia:' . $articleId);
    }

    public function about(): void
    {
        $this->render('pages/about', ['pageTitle' => 'Sobre o Vibe2000', 'canonicalPath' => '/sobre'], 'sobre');
    }

    public function terms(): void
    {
        $this->render('pages/terms', ['pageTitle' => 'Termos de uso · Vibe2000', 'canonicalPath' => '/termos-de-uso'], 'termos');
    }

    public function privacy(): void
    {
        $this->render('pages/privacy', ['pageTitle' => 'Política de privacidade · Vibe2000', 'canonicalPath' => '/privacidade'], 'privacidade');
    }

    public function contact(): void
    {
        Session::start();
        $this->render('pages/contact', [
            'pageTitle' => 'Contato · Vibe2000',
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
        $subjectLine = 'Vibe2000 · ' . ContactMessage::SUBJECTS[$input['subject']] . ($toolId ? ' · ' . $toolId : '');
        $body = "Nova mensagem no Vibe2000\n\nNome: {$input['name']}\nE-mail: {$input['email']}\n\n{$input['message']}\n\nVeja no painel: " . url('/painel/mensagens');
        // O e-mail da pessoa vai no Reply-To (sem quebras de linha, contra injeção de cabeçalho)
        $replyTo = str_replace(["\r", "\n"], '', $input['email']);
        if (!@mail($contactEmail, '=?UTF-8?B?' . base64_encode($subjectLine) . '?=', $body, "Content-Type: text/plain; charset=UTF-8\r\nReply-To: {$replyTo}")) {
            ErrorHandler::log('Falha ao enviar e-mail de aviso de contato');
        }
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $paths = ['/', '/noticias', '/sobre', '/contato', '/termos-de-uso', '/privacidade'];
        foreach (Content::tools() as $tool) {
            $paths[] = '/calculadoras/' . $tool['id'];
        }
        foreach (Content::news() as $article) {
            $paths[] = '/noticias/' . $article['id'];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paths as $path) {
            echo '  <url><loc>' . e(url($path)) . '</loc></url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /painel\nDisallow: /api/\n\nSitemap: " . url('/sitemap.xml') . "\n";
    }

    public function notFound(): void
    {
        $this->render('site/not-found', ['pageTitle' => 'Página não encontrada · Vibe2000', 'tools' => Content::toolsByIds(Content::showcase()['popular_tools'])], 'inicio', 404);
    }
}
