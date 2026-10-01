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
    // Tipo do veículo na FIPE: código e o nome usado na consulta de preço
    public const VEHICLE_TYPES = [1 => 'carro', 2 => 'moto', 3 => 'caminhao'];
    private const LIST_CACHE_SECONDS = 30 * 86400;
    private const REFERENCE_CACHE_SECONDS = 86400;
    public const HISTORY_YEARS = 5;
    public const ZERO_KM_YEAR = 32000; // a FIPE usa o "ano" 32000 para veículo zero km

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
                self::BASE_URL . '/' . $endpoint,
                ['Content-Type: application/x-www-form-urlencoded', 'Referer: https://veiculos.fipe.org.br/', 'Accept: application/json'],
                http_build_query($fields),
                15
            );
        } catch (Throwable $exception) {
            ErrorHandler::log('[fipe] ' . $endpoint . ': ' . $exception->getMessage());
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 502);
        }
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            ErrorHandler::log("[fipe] {$endpoint}: HTTP {$status} " . mb_substr($body, 0, 200));
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 502);
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
            throw new FipeException('A Tabela FIPE não respondeu agora. Tente de novo em instantes.', 502);
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
        return self::cachedList("brands:{$vehicleType}", 'ConsultarMarcas', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
        ], fn (array $data) => $data);
    }

    public static function models(int $vehicleType, int $brandCode): array
    {
        return self::cachedList("models:{$vehicleType}:{$brandCode}", 'ConsultarModelos', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
            'codigoMarca' => $brandCode,
        ], fn (array $data) => $data['Modelos'] ?? []);
    }

    public static function years(int $vehicleType, int $brandCode, int $modelCode): array
    {
        return self::cachedList("years:{$vehicleType}:{$brandCode}:{$modelCode}", 'ConsultarAnoModelo', [
            'codigoTipoVeiculo' => $vehicleType,
            'codigoTabelaReferencia' => self::latestReference(),
            'codigoMarca' => $brandCode,
            'codigoModelo' => $modelCode,
        ], fn (array $data) => isset($data['erro']) ? [] : $data);
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
        $references = self::references();
        $points = [];
        $vehicle = null;
        for ($yearsAgo = self::HISTORY_YEARS; $yearsAgo >= 0; $yearsAgo--) {
            $reference = $references[$yearsAgo * 12] ?? null;
            if ($reference === null) {
                continue;
            }
            $price = self::priceAt($reference['code'], $vehicleType, $brandCode, $modelCode, (int) $yearParts[1], (int) $yearParts[2]);
            if ($price === null) {
                continue; // o veículo ainda não estava na tabela nesse mês
            }
            $vehicle ??= $price['vehicle'];
            $points[] = ['month' => $reference['month'], 'price' => $price['value']];
        }
        if ($points === []) {
            throw new FipeException('Não encontramos preços deste veículo na Tabela FIPE.', 404);
        }

        return ['vehicle' => $vehicle, 'points' => $points];
    }

    /**
     * Preço num mês de referência. Mês que já passou nunca muda: fica no cache para sempre
     * (inclusive o "não encontrado", para não perguntar de novo).
     */
    private static function priceAt(int $referenceCode, int $vehicleType, int $brandCode, int $modelCode, int $modelYear, int $fuelCode): ?array
    {
        $cacheKey = "price:{$referenceCode}:{$vehicleType}:{$brandCode}:{$modelCode}:{$modelYear}:{$fuelCode}";
        $cached = self::cache()->get($cacheKey);
        if ($cached !== null) {
            return $cached['found'] ? $cached['price'] : null;
        }

        $data = self::request('ConsultarValorComTodosParametros', [
            'codigoTabelaReferencia' => $referenceCode,
            'codigoMarca' => $brandCode,
            'codigoModelo' => $modelCode,
            'codigoTipoVeiculo' => $vehicleType,
            'anoModelo' => $modelYear,
            'codigoTipoCombustivel' => $fuelCode,
            'tipoVeiculo' => self::VEHICLE_TYPES[$vehicleType],
            'modeloCodigoExterno' => '',
            'tipoConsulta' => 'tradicional',
        ]);
        $price = null;
        if (isset($data['Valor'])) {
            $price = [
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
        self::cache()->set($cacheKey, ['found' => $price !== null, 'price' => $price]);

        return $price;
    }
}
