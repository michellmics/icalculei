<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Core\FileCache;
use App\Core\HttpClient;
use Throwable;

/**
 * Tabela FIPE (Fundação Instituto de Pesquisas Econômicas): marcas, modelos, anos e o histórico de preços.
 * Usa a mesma API que o site veiculos.fipe.org.br usa (gratuita e sem chave, mas não é uma API pública
 * oficial: pode mudar sem aviso). Por isso tudo fica em cache:
 *   preço de um mês que já passou nunca muda → guardado para sempre (cada consulta é feita uma vez só)
 *   listas de marcas, modelos e anos → 30 dias; lista de meses de referência → 1 dia
 */
class FipeClient
{
    private const BASE_URL = 'https://veiculos.fipe.org.br/api/veiculos';
    // Reserva: API pública da Parallelum (mesmos códigos da FIPE; grátis só o mês atual, sem o histórico).
    // Usada quando o site da FIPE não responde (ele costuma bloquear servidores de hospedagem).
    private const BACKUP_URL = 'https://fipe.parallelum.com.br/api/v2';
    private const BACKUP_TYPE_PATHS = [1 => 'cars', 2 => 'motorcycles', 3 => 'trucks'];
    // Tipo do veículo na FIPE: código e o nome usado na consulta de preço
    public const VEHICLE_TYPES = [1 => 'carro', 2 => 'moto', 3 => 'caminhao'];
    private const LIST_CACHE_SECONDS = 30 * 86400;
    private const REFERENCE_CACHE_SECONDS = 86400;
    public const HISTORY_YEARS = 5;
    private const REQUEST_HEADERS = ['Content-Type: application/x-www-form-urlencoded', 'Referer: https://veiculos.fipe.org.br/', 'Accept: application/json'];
    private const REQUEST_TIMEOUT = 25; // segundos; a FIPE às vezes demora para responder a servidores
    public const ZERO_KM_YEAR = 32000; // a FIPE usa o "ano" 32000 para veículo zero km

    /**
     * Endereço da API: direto na FIPE ou pelo Cloudflare Worker (cloudflare/fipe-worker.js) quando
     * FIPE_PROXY_URL está no .env (a FIPE bloqueia servidores de hospedagem com HTTP 403).
     */
    private static function apiUrl(string $endpoint): string
    {
        $proxyUrl = (string) config('fipe_proxy_url');

        return ($proxyUrl !== '' ? $proxyUrl . '/api/veiculos' : self::BASE_URL) . '/' . $endpoint;
    }

    private static function requestHeaders(): array
    {
        $proxyKey = (string) config('fipe_proxy_key');

        return $proxyKey !== '' ? [...self::REQUEST_HEADERS, 'X-Proxy-Key: ' . $proxyKey] : self::REQUEST_HEADERS;
    }

    private static function cache(): FileCache
    {
        return new FileCache('fipe');
    }

    /**
     * POST na API da FIPE. Lança FipeException com mensagem para o usuário se ela não responder.
     */
    private static function request(string $endpoint, array $fields): array
    {
        try {
            [$status, $body] = HttpClient::request(
                'POST',
                self::apiUrl($endpoint),
                self::requestHeaders(),
                http_build_query($fields),
                self::REQUEST_TIMEOUT
            );
        } catch (Throwable $exception) {
            ErrorHandler::log('[fipe] ' . $endpoint . ': ' . $exception->getMessage());
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
        }
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            ErrorHandler::log("[fipe] {$endpoint}: HTTP {$status} " . mb_substr($body, 0, 200));
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
        }

        return $data;
    }

    /**
     * Meses de referência, do mais novo para o mais antigo: [['code' => 337, 'month' => 'setembro/2026'], ...].
     */
    public static function references(): array
    {
        $cached = self::cache()->get('references', self::REFERENCE_CACHE_SECONDS);
        if ($cached !== null) {
            return $cached;
        }
        $references = array_map(fn (array $item) => ['code' => (int) $item['Codigo'], 'month' => trim((string) $item['Mes'])], self::request('ConsultarTabelaDeReferencia', []));
        if ($references === []) {
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
        }
        self::cache()->set('references', $references);

        return $references;
    }

    private static function latestReference(): int
    {
        return self::references()[0]['code'];
    }

    /**
     * Lista de opções [['value' => ..., 'label' => ...]] (marcas, modelos ou anos), guardada por 30 dias.
     */
    private static function cachedList(string $cacheKey, string $endpoint, array $fields, callable $extractItems): array
    {
        $cached = self::cache()->get($cacheKey, self::LIST_CACHE_SECONDS);
        if ($cached !== null) {
            return $cached;
        }
        $items = array_map(fn (array $item) => ['value' => (string) $item['Value'], 'label' => trim((string) $item['Label'])], $extractItems(self::request($endpoint, $fields)));
        self::cache()->set($cacheKey, $items);

        return $items;
    }

    public static function brands(int $vehicleType): array
    {
        return self::withBackup("brands:{$vehicleType}", "/brands", $vehicleType, fn () => self::cachedList("brands:{$vehicleType}", 'ConsultarMarcas', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
        ], fn (array $data) => $data));
    }

    public static function models(int $vehicleType, int $brandCode): array
    {
        return self::withBackup("models:{$vehicleType}:{$brandCode}", "/brands/{$brandCode}/models", $vehicleType, fn () => self::cachedList("models:{$vehicleType}:{$brandCode}", 'ConsultarModelos', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
            'codigoMarca' => $brandCode,
        ], fn (array $data) => $data['Modelos'] ?? []));
    }

    public static function years(int $vehicleType, int $brandCode, int $modelCode): array
    {
        return self::withBackup("years:{$vehicleType}:{$brandCode}:{$modelCode}", "/brands/{$brandCode}/models/{$modelCode}/years", $vehicleType, fn () => self::cachedList("years:{$vehicleType}:{$brandCode}:{$modelCode}", 'ConsultarAnoModelo', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
            'codigoMarca' => $brandCode,
            'codigoModelo' => $modelCode,
        ], fn (array $data) => isset($data['erro']) ? [] : $data));
    }

    /**
     * GET na API reserva (Parallelum). Lança FipeException se ela também não responder.
     */
    private static function backupRequest(int $vehicleType, string $path): array
    {
        try {
            [$status, $body] = HttpClient::request('GET', self::BACKUP_URL . '/' . self::BACKUP_TYPE_PATHS[$vehicleType] . $path, ['Accept: application/json'], null, 15);
        } catch (Throwable $exception) {
            ErrorHandler::log('[fipe reserva] ' . $path . ': ' . $exception->getMessage());
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
        }
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            ErrorHandler::log("[fipe reserva] {$path}: HTTP {$status} " . mb_substr($body, 0, 200));
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
        }

        return $data;
    }

    /**
     * Lista da FIPE; se o site da FIPE falhar, a mesma lista da API reserva (guardada por 1 dia).
     */
    private static function withBackup(string $cacheKey, string $backupPath, int $vehicleType, callable $fromFipe): array
    {
        try {
            return $fromFipe();
        } catch (FipeException $exception) {
            $backupKey = 'backup:' . $cacheKey;
            $cached = self::cache()->get($backupKey, 86400);
            if ($cached !== null) {
                return $cached;
            }
            $items = array_map(fn (array $item) => ['value' => (string) $item['code'], 'label' => trim((string) $item['name'])], self::backupRequest($vehicleType, $backupPath));
            self::cache()->set($backupKey, $items);

            return $items;
        }
    }

    /**
     * Preço do veículo no mesmo mês de cada um dos últimos 5 anos (e no mês atual).
     * $yearValue vem da lista de anos, no formato "2020-5" (ano-modelo e código do combustível).
     */
    public static function history(int $vehicleType, int $brandCode, int $modelCode, string $yearValue): array
    {
        if (!preg_match('/^(\d{4,5})-(\d{1,2})$/', $yearValue, $yearParts)) {
            throw new FipeException('Escolha o ano do veículo.', 422);
        }
        try {
            return self::fullHistory($vehicleType, $brandCode, $modelCode, $yearParts);
        } catch (FipeException $exception) {
            if ($exception->getCode() !== 503) {
                throw $exception;
            }
            return self::currentPriceFromBackup($vehicleType, $brandCode, $modelCode, $yearValue);
        }
    }

    /**
     * Só o valor do mês atual, pela API reserva (o histórico de anos anteriores lá é pago).
     */
    private static function currentPriceFromBackup(int $vehicleType, int $brandCode, int $modelCode, string $yearValue): array
    {
        $data = self::backupRequest($vehicleType, "/brands/{$brandCode}/models/{$modelCode}/years/{$yearValue}");
        if (!isset($data['price'])) {
            throw new FipeException('Não encontramos preços deste veículo na Tabela FIPE.', 404);
        }

        return [
            'vehicle' => [
                'brand' => (string) ($data['brand'] ?? ''),
                'model' => (string) ($data['model'] ?? ''),
                'year' => (int) ($data['modelYear'] ?? 0) === self::ZERO_KM_YEAR ? 'Zero km' : (string) ($data['modelYear'] ?? ''),
                'fuel' => (string) ($data['fuel'] ?? ''),
                'fipeCode' => (string) ($data['codeFipe'] ?? ''),
            ],
            'points' => [[
                'month' => str_replace(' de ', '/', trim((string) ($data['referenceMonth'] ?? ''))),
                'price' => (float) str_replace(['R$', '.', ',', ' '], ['', '', '.', ''], (string) $data['price']),
            ]],
            'historyUnavailable' => true,
        ];
    }

    private static function fullHistory(int $vehicleType, int $brandCode, int $modelCode, array $yearParts): array
    {
        $references = self::references();
        $modelYear = (int) $yearParts[1];
        $fuelCode = (int) $yearParts[2];

        // Um mês por ano (o atual e o mesmo mês de 1 a 5 anos atrás), do mais antigo para o mais novo
        $months = [];
        for ($yearsAgo = self::HISTORY_YEARS; $yearsAgo >= 0; $yearsAgo--) {
            if (isset($references[$yearsAgo * 12])) {
                $months[] = $references[$yearsAgo * 12];
            }
        }

        // Mês que já passou fica no cache para sempre; só os que faltam vão para a FIPE, todos ao mesmo tempo
        $prices = [];
        $missing = [];
        foreach ($months as $reference) {
            $cached = self::cache()->get(self::priceCacheKey($reference['code'], $vehicleType, $brandCode, $modelCode, $modelYear, $fuelCode));
            if ($cached !== null) {
                $prices[$reference['code']] = $cached['found'] ? $cached['price'] : null;
                continue;
            }
            $missing[$reference['code']] = [
                'method' => 'POST',
                'url' => self::apiUrl('ConsultarValorComTodosParametros'),
                'headers' => self::requestHeaders(),
                'body' => http_build_query([
                    'codigoTabelaReferencia' => $reference['code'],
                    'codigoMarca' => $brandCode,
                    'codigoModelo' => $modelCode,
                    'codigoTipoVeiculo' => $vehicleType,
                    'anoModelo' => $modelYear,
                    'codigoTipoCombustivel' => $fuelCode,
                    'tipoVeiculo' => self::VEHICLE_TYPES[$vehicleType],
                    'modeloCodigoExterno' => '',
                    'tipoConsulta' => 'tradicional',
                ]),
            ];
        }
        $answered = 0;
        if ($missing !== []) {
            $startedAt = microtime(true);
            $responses = HttpClient::requestMany($missing, self::REQUEST_TIMEOUT);
            $seconds = microtime(true) - $startedAt;
            if ($seconds > 5) {
                ErrorHandler::log(sprintf('[fipe] histórico demorou %.1f s (%d meses)', $seconds, count($missing)));
            }
            foreach ($responses as $referenceCode => [$status, $body]) {
                $data = json_decode($body, true);
                if ($status !== 200 || !is_array($data)) {
                    ErrorHandler::log("[fipe] histórico {$referenceCode}: HTTP {$status} " . mb_substr($body, 0, 200));
                    continue; // fica sem esse mês (e sem cache, para tentar de novo depois)
                }
                $answered++;
                $price = self::parsePrice($data, $modelYear);
                self::cache()->set(self::priceCacheKey($referenceCode, $vehicleType, $brandCode, $modelCode, $modelYear, $fuelCode), ['found' => $price !== null, 'price' => $price]);
                $prices[$referenceCode] = $price;
            }
        }

        $points = [];
        $vehicle = null;
        foreach ($months as $reference) {
            $price = $prices[$reference['code']] ?? null;
            if ($price === null) {
                continue; // o veículo ainda não estava na tabela nesse mês (ou a FIPE não respondeu esse mês)
            }
            $vehicle ??= $price['vehicle'];
            $points[] = ['month' => $reference['month'], 'price' => $price['value']];
        }
        if ($points === []) {
            // A FIPE não respondeu nenhum mês: history() cai para a API reserva
            if ($missing !== [] && $answered === 0) {
                throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 503);
            }
            throw new FipeException('Não encontramos preços deste veículo na Tabela FIPE.', 404);
        }

        return ['vehicle' => $vehicle, 'points' => $points];
    }

    private static function priceCacheKey(int $referenceCode, int $vehicleType, int $brandCode, int $modelCode, int $modelYear, int $fuelCode): string
    {
        return "price:{$referenceCode}:{$vehicleType}:{$brandCode}:{$modelCode}:{$modelYear}:{$fuelCode}";
    }

    /**
     * Resposta de ConsultarValorComTodosParametros → ['value' => 45000.0, 'vehicle' => [...]] ou null (sem preço nesse mês).
     */
    private static function parsePrice(array $data, int $modelYear): ?array
    {
        if (!isset($data['Valor'])) {
            return null;
        }

        return [
            'value' => (float) str_replace(['R$', '.', ',', ' '], ['', '', '.', ''], (string) $data['Valor']),
            'vehicle' => [
                'brand' => (string) ($data['Marca'] ?? ''),
                'model' => (string) ($data['Modelo'] ?? ''),
                'year' => (int) ($data['AnoModelo'] ?? $modelYear) === self::ZERO_KM_YEAR ? 'Zero km' : (string) ($data['AnoModelo'] ?? $modelYear),
                'fuel' => (string) ($data['Combustivel'] ?? ''),
                'fipeCode' => (string) ($data['CodigoFipe'] ?? ''),
            ],
        ];
    }
}
