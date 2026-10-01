<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\Http;
use App\Models\RateLimit;
use App\Models\ShortLink;
use Throwable;

/**
 * Encurtador de URL:
 *   POST /api/encurtar { url }   cria (ou reaproveita) o link curto e devolve o endereço
 *   GET  /l/{code}               leva para o endereço longo e conta o clique
 */
class ShortLinkController
{
    private const MAX_LINKS_PER_HOUR = 20; // por visitante (IP), contra spam
    private const MAX_URL_LENGTH = 2000;

    public function create(): void
    {
        Http::rejectOtherSites();
        $input = json_decode((string) file_get_contents('php://input'), true);
        $url = trim((string) (is_array($input) ? ($input['url'] ?? '') : ''));
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . $url;
        }

        $validationError = $this->validationError($url);
        if ($validationError !== null) {
            Http::json(['error' => $validationError], 422);
        }

        try {
            $limitKey = RateLimit::key('short-link', $_SERVER['REMOTE_ADDR'] ?? '');
            if (RateLimit::tooManyAttempts($limitKey, self::MAX_LINKS_PER_HOUR)) {
                Http::json(['error' => 'Você criou muitos links seguidos. Aguarde um pouco e tente de novo.'], 429);
            }
            RateLimit::hit($limitKey, 60);

            $code = ShortLink::codeFor($url);
        } catch (Throwable $exception) {
            ErrorHandler::log('[encurtador] erro: ' . $exception->getMessage());
            Http::json(['error' => 'Não foi possível encurtar o link agora. Tente de novo em instantes.'], 503);
        }

        Http::json(['short_url' => url('/l/' . $code), 'url' => $url]);
    }

    public function open(string $code): void
    {
        $link = null;
        try {
            $link = ShortLink::find($code);
            if ($link !== null) {
                ShortLink::countClick((int) $link['id']);
            }
        } catch (Throwable $exception) {
            ErrorHandler::log('[encurtador] erro ao abrir: ' . $exception->getMessage());
        }

        if ($link === null) {
            (new SiteController())->notFound();
            return;
        }

        // Endereço externo: não usa Http::redirect, que só aceita caminhos do próprio site
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header('Location: ' . $link['url'], true, 302);
        exit;
    }

    /**
     * Só links http/https completos, sem usuário/senha e que não apontem para o próprio encurtador.
     */
    private function validationError(string $url): ?string
    {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            return 'O link é longo demais (máximo de 2.000 caracteres).';
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false || !str_contains($host, '.')) {
            return 'Esse link não parece válido. Confira o endereço (ex.: https://site.com.br/pagina).';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Links com usuário e senha no endereço não são aceitos.';
        }
        $siteHost = strtolower((string) parse_url((string) config('url'), PHP_URL_HOST));
        if ($host === $siteHost && str_starts_with((string) ($parts['path'] ?? ''), '/l/')) {
            return 'Esse link já é curto.';
        }

        return null;
    }
}
