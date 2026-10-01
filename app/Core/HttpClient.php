<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Chamadas HTTP do servidor para APIs externas (ex.: rotas da calculadora de viagem).
 * HTTP_CA_BUNDLE no .env (opcional): arquivo de certificados para o PHP que não consegue
 * validar HTTPS sozinho (comum no Windows em desenvolvimento). Em produção, deixe vazio.
 */
class HttpClient
{
    private const USER_AGENT = 'Vibe2000/1.0 (+https://vibe2000.com.br/contato)';

    /**
     * Faz o pedido e devolve [código HTTP, corpo da resposta].
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('O PHP precisa da extensão curl.');
        }
        $curl = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_ENCODING => '', // aceita resposta comprimida
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        // HTTP_CA_BUNDLE=windows usa os certificados do Windows (inclui o de antivírus que inspecionam HTTPS);
        // um caminho de arquivo usa esse arquivo; vazio usa o padrão do PHP (produção)
        $caBundle = (string) config('http_ca_bundle');
        if (strtolower($caBundle) === 'windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        } elseif ($caBundle !== '' && is_file($caBundle)) {
            $options[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($curl, $options);
        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($responseBody === false) {
            throw new RuntimeException('Falha na conexão: ' . $error);
        }

        return [$status, (string) $responseBody];
    }
}
