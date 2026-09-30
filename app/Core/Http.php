<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respostas HTTP prontas e cabeçalhos de segurança.
 */
class Http
{
    public static function redirect(string $path, int $statusCode = 302): never
    {
        // Só redireciona para caminhos internos (evita "open redirect")
        $isInternalPath = str_starts_with($path, '/') && !str_starts_with($path, '//');
        header('Location: ' . ($isInternalPath ? $path : '/'), true, $statusCode);
        exit;
    }

    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noContent(int $statusCode = 204): never
    {
        http_response_code($statusCode);
        exit;
    }

    /**
     * Cabeçalhos de segurança. Quando o AdSense está ligado, a política libera
     * os endereços do Google necessários para os anúncios funcionarem.
     */
    public static function sendSecurityHeaders(): void
    {
        $adsEnabled = config('adsense_client') !== '';
        $googleAdHosts = 'https://*.googlesyndication.com https://*.doubleclick.net https://*.google.com https://*.gstatic.com https://*.googletagservices.com https://*.adtrafficquality.google https://*.googleadservices.com';

        $policy = [
            "default-src 'self'",
            "script-src 'self' https://cdnjs.cloudflare.com" . ($adsEnabled ? ' ' . $googleAdHosts : ''),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:" . ($adsEnabled ? ' https:' : ''),
            // APIs gratuitas de cotação de moedas (chamadas pelo navegador)
            "connect-src 'self' https://economia.awesomeapi.com.br https://api.frankfurter.dev" . ($adsEnabled ? ' ' . $googleAdHosts : ''),
            'frame-src ' . ($adsEnabled ? $googleAdHosts : "'none'"),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        header('Content-Security-Policy: ' . implode('; ', $policy));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header_remove('X-Powered-By');
    }
}
