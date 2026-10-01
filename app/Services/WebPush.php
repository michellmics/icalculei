<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpClient;
use RuntimeException;

/**
 * Envio de notificação push (Web Push) sem bibliotecas: só o openssl do PHP.
 *   - VAPID (RFC 8292): um token assinado (ES256) prova ao serviço de push (Google, Apple, Mozilla) que somos nós.
 *   - Criptografia da mensagem (RFC 8291, "aes128gcm"): só o aparelho inscrito consegue ler o texto.
 * As chaves VAPID são criadas sozinhas na primeira vez e ficam em storage/keys/vapid.json (fora da pasta pública;
 * a atualização pelo painel não mexe em storage/). Se o arquivo sumir, basta ativar as notificações de novo no painel.
 */
class WebPush
{
    private const KEYS_FILE = BASE_PATH . '/storage/keys/vapid.json';
    // Começo fixo do DER de uma chave pública P-256 (SubjectPublicKeyInfo); depois vem 04 || x || y
    private const PUBLIC_KEY_DER_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Chave pública VAPID (base64url), usada pelo navegador ao se inscrever.
     */
    public static function publicKey(): string
    {
        return self::base64UrlEncode(self::serverKeys()['public']);
    }

    /**
     * Manda a mensagem para uma inscrição. Devolve o status HTTP do serviço de push
     * (201 = entregue; 404 ou 410 = inscrição vencida, pode apagar).
     */
    public static function send(array $subscription, array $message): int
    {
        $payload = self::encrypt(
            json_encode($message, JSON_UNESCAPED_UNICODE),
            self::base64UrlDecode($subscription['public_key']),
            self::base64UrlDecode($subscription['auth_secret'])
        );
        $keys = self::serverKeys();
        [$status] = HttpClient::request('POST', $subscription['endpoint'], [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400', // o serviço guarda por até 1 dia se o celular estiver desligado
            'Urgency: normal',
            'Authorization: vapid t=' . self::vapidToken($subscription['endpoint'], $keys) . ', k=' . self::base64UrlEncode($keys['public']),
        ], $payload, 10);

        return $status;
    }

    /**
     * Criptografa a mensagem para o aparelho (RFC 8291). $serverKeyPair e $salt só são passados no teste com os valores do RFC.
     */
    public static function encrypt(string $plaintext, string $userAgentPublic, string $authSecret, ?array $serverKeyPair = null, ?string $salt = null): string
    {
        // Par de chaves descartável, novo a cada mensagem
        $serverKeyPair ??= self::newKeyPair();
        $salt ??= random_bytes(16);

        $sharedSecret = openssl_pkey_derive(self::publicKeyFromRaw($userAgentPublic), self::privateKeyFromRaw($serverKeyPair['private'], $serverKeyPair['public']), 32);
        if ($sharedSecret === false) {
            throw new RuntimeException('chave do aparelho inválida');
        }
        $keyInfo = "WebPush: info\0" . $userAgentPublic . $serverKeyPair['public'];
        $inputKey = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);
        $contentKey = hash_hkdf('sha256', $inputKey, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $inputKey, 12, "Content-Encoding: nonce\0", $salt);

        // \x02 = fim da mensagem (último bloco, sem enchimento)
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // Cabeçalho: salt (16) + tamanho do bloco 4096 (4) + tamanho da chave (1) + chave pública do servidor (65)
        return $salt . pack('N', 4096) . chr(strlen($serverKeyPair['public'])) . $serverKeyPair['public'] . $ciphertext . $tag;
    }

    /**
     * Token VAPID (JWT ES256), válido por 12 horas, para a origem do serviço de push.
     */
    private static function vapidToken(string $endpoint, array $keys): string
    {
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $contactEmail = (string) config('contact_email');
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $contactEmail !== '' ? 'mailto:' . $contactEmail : config('url'),
        ], JSON_UNESCAPED_SLASHES));
        $signedPart = $header . '.' . $claims;
        openssl_sign($signedPart, $derSignature, self::privateKeyFromRaw($keys['private'], $keys['public']), OPENSSL_ALGO_SHA256);

        return $signedPart . '.' . self::base64UrlEncode(self::derSignatureToRaw($derSignature));
    }

    /**
     * Chaves VAPID do site: lê de storage/keys/vapid.json ou cria na primeira vez.
     */
    private static function serverKeys(): array
    {
        if (is_file(self::KEYS_FILE)) {
            $saved = json_decode((string) file_get_contents(self::KEYS_FILE), true);
            if (isset($saved['public'], $saved['private'])) {
                return ['public' => self::base64UrlDecode($saved['public']), 'private' => self::base64UrlDecode($saved['private'])];
            }
        }
        $keyPair = self::newKeyPair();
        if (!is_dir(dirname(self::KEYS_FILE))) {
            mkdir(dirname(self::KEYS_FILE), 0700, true);
        }
        file_put_contents(self::KEYS_FILE, json_encode(['public' => self::base64UrlEncode($keyPair['public']), 'private' => self::base64UrlEncode($keyPair['private'])]));
        @chmod(self::KEYS_FILE, 0600);

        return $keyPair;
    }

    /**
     * Novo par P-256 em bytes: public = 04 || x || y (65 bytes), private = d (32 bytes).
     */
    private static function newKeyPair(): array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $key = openssl_pkey_new($options);
        if ($key === false) {
            // PHP no Windows às vezes não acha o openssl.cnf; ele vem junto do PHP em extras/ssl
            $key = openssl_pkey_new($options + ['config' => dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf']);
        }
        if ($key === false) {
            throw new RuntimeException('o openssl do PHP não conseguiu criar a chave');
        }
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'public' => "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT),
            'private' => str_pad($ec['d'], 32, "\0", STR_PAD_LEFT),
        ];
    }

    private static function publicKeyFromRaw(string $publicRaw): mixed
    {
        $der = hex2bin(self::PUBLIC_KEY_DER_PREFIX) . $publicRaw;
        $key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
        if ($key === false) {
            throw new RuntimeException('chave pública inválida');
        }

        return $key;
    }

    /**
     * Monta a chave privada (formato EC PRIVATE KEY, SEC1) a partir dos bytes.
     */
    private static function privateKeyFromRaw(string $privateRaw, string $publicRaw): mixed
    {
        $der = hex2bin('30770201010420') . $privateRaw . hex2bin('a00a06082a8648ce3d030107a144034200') . $publicRaw;
        $key = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n");
        if ($key === false) {
            throw new RuntimeException('chave privada inválida');
        }

        return $key;
    }

    /**
     * O openssl assina em DER (SEQUENCE com r e s); o JWT quer r || s com 32 bytes cada.
     */
    private static function derSignatureToRaw(string $der): string
    {
        $position = 2; // pula SEQUENCE e tamanho
        $parts = [];
        for ($index = 0; $index < 2; $index++) {
            $length = ord($der[$position + 1]);
            $integer = substr($der, $position + 2, $length);
            $parts[] = str_pad(ltrim($integer, "\0"), 32, "\0", STR_PAD_LEFT);
            $position += 2 + $length;
        }

        return $parts[0] . $parts[1];
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'));
    }
}
