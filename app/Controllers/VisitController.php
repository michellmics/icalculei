<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\Http;
use App\Core\Session;
use App\Models\Visit;
use App\Services\Content;
use App\Services\PushNotifier;
use PDOException;
use Throwable;

/**
 * Contador de visitas. Chamado pelo site.js em todas as páginas públicas:
 *   POST { t: 'ver',  p: 'calculadora:ferias', r: document.referrer, u: utm_source } → 1 página vista
 *   POST { t: 'ping', p }                   → "ainda estou aqui" (a cada 30 s com a aba visível)
 *   POST { t: 'sai' }                       → saiu da página (sendBeacon)
 *   POST { t: 'uso',  tool: 'ferias' }      → primeiro cálculo feito numa calculadora
 * O visitante é um cookie anônimo (v2k_vid); no banco vai só o hash dele.
 * Robôs e o navegador com o painel aberto não contam.
 */
class VisitController
{
    private const VISITOR_COOKIE = 'v2k_vid';
    public const ADMIN_COOKIE = 'v2k_admin';
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|headless|lighthouse|curl|wget|python|monitor/i';

    public function track(): void
    {
        // Só aceita chamadas do próprio site
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
            Http::noContent(403);
        }

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) || isset($_COOKIE[self::ADMIN_COOKIE])) {
            Http::noContent();
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $type = is_array($input) ? (string) ($input['t'] ?? '') : '';
        if (!in_array($type, ['ver', 'ping', 'sai', 'uso'], true)) {
            Http::noContent(422);
        }

        // Visitante anônimo: cookie aleatório de 2 anos; sem cookie = primeira visita deste navegador
        $visitorId = (string) ($_COOKIE[self::VISITOR_COOKIE] ?? '');
        $isNewVisitor = preg_match('/^[a-f0-9]{32}$/', $visitorId) !== 1;
        if ($isNewVisitor) {
            if ($type !== 'ver') {
                Http::noContent();
            }
            $visitorId = bin2hex(random_bytes(16));
            setcookie(self::VISITOR_COOKIE, $visitorId, [
                'expires' => time() + 730 * 86400,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => Session::isHttps(),
            ]);
        }
        $visitor = md5('v2k-visita|' . $visitorId);
        $device = $this->detectDevice($userAgent);

        try {
            if ($type === 'sai') {
                Visit::markOffline($visitor);
                Http::noContent();
            }

            if ($type === 'uso') {
                $toolId = (string) ($input['tool'] ?? '');
                if (Content::tool($toolId) !== null) {
                    Visit::recordToolUse($toolId, $visitor);
                }
                Http::noContent();
            }

            $page = $this->pageLabel((string) ($input['p'] ?? ''));
            if ($type === 'ver') {
                Visit::recordPageView($visitor, $isNewVisitor, $page, $this->detectSource($input, $host), $device);
                if ($isNewVisitor) {
                    $this->countNewVisitor();
                }
            }
            Visit::markOnline($visitor, $page, $device);

            if (random_int(1, 200) === 1) {
                Visit::cleanOldOnline();
            }
        } catch (PDOException) {
            Http::noContent(503); // banco fora do ar: o site continua funcionando normalmente
        }

        Http::noContent();
    }

    /**
     * Total de visitantes + aviso no app do painel a cada 1.000. Falha aqui nunca atrapalha a contagem da visita.
     */
    private function countNewVisitor(): void
    {
        try {
            PushNotifier::checkVisitorMilestone(Visit::countNewVisitor());
        } catch (Throwable $exception) {
            ErrorHandler::log('[push] contador de visitantes: ' . $exception->getMessage());
        }
    }

    /**
     * Converte a chave da página em um nome legível. Só aceita páginas que existem (nada de texto livre no banco).
     */
    private function pageLabel(string $pageKey): string
    {
        $fixedPages = ['inicio' => 'Início', 'noticias' => 'Notícias', 'sobre' => 'Sobre', 'contato' => 'Contato', 'termos' => 'Termos de uso', 'privacidade' => 'Privacidade'];
        if (isset($fixedPages[$pageKey])) {
            return $fixedPages[$pageKey];
        }
        if (str_starts_with($pageKey, 'calculadora:') && ($tool = Content::tool(substr($pageKey, 12))) !== null) {
            return 'Calculadora · ' . $tool['name'];
        }
        if (str_starts_with($pageKey, 'noticia:') && ($article = Content::article(substr($pageKey, 8))) !== null) {
            return mb_substr('Notícia · ' . $article['title'], 0, 120);
        }

        return 'Início';
    }

    /**
     * De onde veio: utm_source do link, o site que mandou (referrer) ou "direto".
     * Navegação dentro do próprio site = "interno".
     */
    private function detectSource(array $input, ?string $host): string
    {
        $utmSource = strtolower((string) preg_replace('/[^\w.-]/', '', (string) ($input['u'] ?? '')));
        $referrerHost = strtolower((string) parse_url((string) ($input['r'] ?? ''), PHP_URL_HOST));
        $referrerHost = (string) preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $referrerHost);
        $ownHost = (string) preg_replace('/^www\./', '', (string) $host);

        if ($utmSource !== '') {
            $source = $utmSource;
        } elseif ($referrerHost === '') {
            $source = 'direto';
        } elseif ($referrerHost === $ownHost) {
            $source = 'interno';
        } else {
            // "google.com.br" e "google.com" contam como "google"
            $source = preg_match('/(^|\.)(google|bing|yahoo|duckduckgo|facebook|instagram|youtube|twitter|x|linkedin|tiktok|pinterest)\./', $referrerHost, $match) ? $match[2] : $referrerHost;
        }

        return mb_substr($source, 0, 80);
    }

    private function detectDevice(string $userAgent): string
    {
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $userAgent)) {
            return 'tablet';
        }

        return preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $userAgent) ? 'celular' : 'computador';
    }
}
