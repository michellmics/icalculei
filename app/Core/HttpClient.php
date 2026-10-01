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
    private const USER_AGENT = 'iCalculei/1.0 (+https://icalculei.com.br/contato)';

    /**
     * Faz o pedido e devolve [código HTTP, corpo da resposta].
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('O PHP precisa da extensão curl.');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, self::options($method, $headers, $body, $timeoutSeconds));
        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($responseBody === false) {
            throw new RuntimeException('Falha na conexão: ' . $error);
        }

        return [$status, (string) $responseBody];
    }

    /**
     * Vários pedidos ao mesmo tempo: o tempo total é o do mais lento, não a soma.
     * $requests = [chave => ['method' => 'POST', 'url' => ..., 'headers' => [...], 'body' => ...]].
     * Devolve [chave => [código HTTP, corpo]]; quem falhou na conexão volta com código 0 e o erro no corpo.
     */
    public static function requestMany(array $requests, int $timeoutSeconds = 20): array
    {
        if (!function_exists('curl_multi_init')) {
            throw new RuntimeException('O PHP precisa da extensão curl.');
        }
        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as $key => $request) {
            $curl = curl_init($request['url']);
            curl_setopt_array($curl, self::options($request['method'] ?? 'GET', $request['headers'] ?? [], $request['body'] ?? null, $timeoutSeconds));
            curl_multi_add_handle($multi, $curl);
            $handles[$key] = $curl;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $key => $curl) {
            $body = curl_multi_getcontent($curl);
            $error = curl_error($curl);
            $responses[$key] = $error !== '' || $body === null
                ? [0, 'Falha na conexão: ' . $error]
                : [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $body];
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }
        curl_multi_close($multi);

        return $responses;
    }

    private static function options(string $method, array $headers, ?string $body, int $timeoutSeconds): array
    {
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

        return $options;
    }
}
