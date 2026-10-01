<?php

declare(strict_types=1);

/**
 * Funções de apoio usadas em toda a aplicação, principalmente nas views.
 */

use App\Core\Csrf;
use App\Core\Env;

/**
 * Configurações do site (valores do .env com padrões seguros).
 */
function config(string $key): mixed
{
    static $settings = null;

    $settings ??= [
        'site_name' => Env::get('APP_NAME', 'Vibe2000'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::getBool('APP_DEBUG', false),
        'url' => rtrim(Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
        'app_key' => Env::get('APP_KEY', ''),
        'db_host' => Env::get('DB_HOST', 'localhost'),
        'db_port' => Env::getInt('DB_PORT', 3306),
        'db_name' => Env::get('DB_DATABASE', ''),
        'db_user' => Env::get('DB_USERNAME', ''),
        'db_password' => Env::get('DB_PASSWORD', ''),
        'session_name' => Env::get('SESSION_NAME', 'vibe2000_session'),
        'admin_user' => Env::get('ADMIN_USER', ''),
        'admin_password' => Env::get('ADMIN_PASSWORD', ''),
        'admin_remember_key' => Env::get('ADMIN_REMEMBER_KEY', ''),
        'ors_api_key' => Env::get('ORS_API_KEY', ''),
        'fipe_proxy_url' => rtrim(Env::get('FIPE_PROXY_URL', ''), '/'),
        'http_ca_bundle' => Env::get('HTTP_CA_BUNDLE', ''),
        'adsense_client' => Env::get('ADSENSE_CLIENT', ''),
        'ads_placeholders' => Env::getBool('ADS_PLACEHOLDERS', false),
        'contact_email' => Env::get('CONTACT_EMAIL', ''),
        'owner_name' => Env::get('SITE_OWNER_NAME', ''),
        'owner_document' => Env::get('SITE_OWNER_DOCUMENT', ''),
        'owner_city' => Env::get('SITE_OWNER_CITY', ''),
    ];

    return $settings[$key] ?? null;
}

/**
 * Escapa um texto para exibir com segurança no HTML (proteção contra XSS).
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Endereço completo de uma página do site, ex.: url('/contato').
 */
function url(string $path = '/'): string
{
    return config('url') . '/' . ltrim($path, '/');
}

/**
 * Título de uma calculadora para o Google, sempre com o ano atual e "Grátis"
 * (o ano muda sozinho em janeiro; não escreva o ano à mão no seo_title).
 *   "Calculadora de Salário Líquido {ano} (INSS e IR)" → "Calculadora de Salário Líquido 2026 Grátis (INSS e IR)"
 *   "Calculadora de IMC: Índice de Massa Corporal"     → "Calculadora de IMC 2026 Grátis: Índice de Massa Corporal"
 *   "Calculadora de Média Ponderada de Notas"          → "Calculadora de Média Ponderada de Notas 2026 Grátis"
 */
function tool_title(array $tool): string
{
    $year = date('Y');
    $hasFree = preg_match('/gr[áa]tis/iu', $tool['seo_title'] ?? '') === 1;
    $free = $hasFree ? '' : ' Grátis';
    // {ano} ou um ano escrito à mão (ex.: 2026) viram o ano atual, seguido de "Grátis"
    $title = preg_replace('/\{ano\}|\b20\d{2}\b/u', $year . $free, $tool['seo_title'] ?? $tool['name'], 1, $replacements);
    if ($replacements === 0) {
        // Sem ano no texto: entra antes dos dois-pontos ou no fim
        $colon = mb_strpos($title, ':');
        $title = $colon === false
            ? $title . ' ' . $year . $free
            : mb_substr($title, 0, $colon) . ' ' . $year . $free . mb_substr($title, $colon);
    }

    return $title;
}

/**
 * Arquivo de public/assets com a data de alteração, para o navegador não usar versão antiga.
 */
function asset(string $path): string
{
    $filePath = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($filePath) ? filemtime($filePath) : 0;

    return '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Endereço da foto de uma notícia (public/assets/img/news/).
 * No site usa a versão WebP, mais leve (criada por bin/news-webp.php), quando ela existe.
 * Para redes sociais e dados do Google ($preferWebp = false), usa o JPG original.
 */
function news_image(array $article, bool $preferWebp = true): string
{
    $webpName = preg_replace('/\.jpe?g$/i', '.webp', $article['image']);
    if ($preferWebp && is_file(BASE_PATH . '/public/assets/img/news/' . $webpName)) {
        return asset('img/news/' . $webpName);
    }

    return asset('img/news/' . $article['image']);
}

/**
 * srcset da foto de uma notícia: 480 px (celular e cartões) e 960 px (telas grandes).
 * Vazio se a versão pequena ainda não foi gerada (php bin/news-webp.php).
 */
function news_image_srcset(array $article): string
{
    $smallName = preg_replace('/\.jpe?g$/i', '-480.webp', $article['image']);
    if (!is_file(BASE_PATH . '/public/assets/img/news/' . $smallName)) {
        return '';
    }

    return asset('img/news/' . $smallName) . ' 480w, ' . news_image($article) . ' 960w';
}

/**
 * Foto pequena (480 px) para miniaturas; se não existir, a normal.
 */
function news_image_small(array $article): string
{
    $smallName = preg_replace('/\.jpe?g$/i', '-480.webp', $article['image']);

    return is_file(BASE_PATH . '/public/assets/img/news/' . $smallName) ? asset('img/news/' . $smallName) : news_image($article);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(Csrf::token()) . '">';
}

/**
 * Data no formato brasileiro: "2026-09-30" vira "30/09/2026".
 */
function format_date(string $isoDate): string
{
    $timestamp = strtotime($isoDate);

    return $timestamp === false ? $isoDate : date('d/m/Y', $timestamp);
}

/**
 * Banner de compartilhamento (og:image 1200×630, gerado por bin/og-images.php). Sem o arquivo, usa o banner geral.
 * O ?v= muda quando o banner é gerado de novo (WhatsApp e Facebook guardam a imagem pelo endereço).
 */
function share_banner(string $name = 'vibe2000'): string
{
    $path = '/assets/img/og/' . $name . '.png';
    if (!is_file(BASE_PATH . '/public' . $path)) {
        $path = '/assets/img/og/vibe2000.png';
    }

    return url($path) . '?v=' . (int) @filemtime(BASE_PATH . '/public' . $path);
}

function format_number(int|float $value, int $decimals = 0): string
{
    return number_format($value, $decimals, ',', '.');
}

/**
 * Texto sem acentos e em minúsculas, para comparar na busca.
 */
function normalize_text(string $text): string
{
    $accentReplacements = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    return strtr(mb_strtolower($text), $accentReplacements);
}

/**
 * Dado do responsável pelo site (nome, e-mail...). Se ainda não foi preenchido no .env,
 * aparece destacado em amarelo para o dono lembrar de completar.
 */
function owner_info(string $key, string $label): string
{
    $value = (string) config($key);

    return $value !== '' ? e($value) : '<span class="fill-in">[preencher: ' . e($label) . ']</span>';
}
