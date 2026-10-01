<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Core\HttpClient;
use RuntimeException;
use Throwable;

/**
 * Calculadora de viagem: endereços → coordenadas, rota mais rápida de carro e praças de pedágio no caminho.
 * Tudo gratuito:
 *   OpenRouteService (endereços e rota)   chave grátis em ORS_API_KEY no .env (2.000 rotas e 1.000 buscas por dia)
 *   Overpass / OpenStreetMap (pedágios)   sem chave; onde ficam as praças e, quando a comunidade informou,
 *                                         o valor para carro (tag charge, com a data da conferência)
 * Respostas ficam em cache (storage/cache/trip) para rotas repetidas não gastarem a cota.
 */
class TripPlanner
{
    private const ORS_URL = 'https://api.openrouteservice.org';
    // Servidores públicos e gratuitos do Overpass, do mais rápido para o mais lento, com o tempo máximo de
    // espera de cada um: se um estiver ocupado (429) ou fora do ar, tenta o próximo
    private const OVERPASS_URLS = [
        'https://overpass-api.de/api/interpreter' => 15,
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter' => 20,
        'https://overpass.private.coffee/api/interpreter' => 10,
    ];
    private const CACHE_FOLDER = '/storage/cache/trip';
    private const CACHE_DAYS_ADDRESS = 30;
    private const CACHE_DAYS_ROUTE = 7;
    // Abaixo das cotas grátis do OpenRouteService, com folga
    private const DAILY_LIMITS = ['geocode' => 900, 'route' => 1800];
    // A cabine fica sobre a própria pista (0 m da rota); as de rampas de acesso ao lado ficam a 15–40 m.
    // Por isso só conta como "no caminho" a que está a até 12 m.
    private const TOLL_DISTANCE_METERS = 12;
    private const SAME_PLAZA_METERS = 400;      // cabines a menos de 400 m são a mesma praça
    private const SEGMENT_KILOMETERS = 60;      // a busca de pedágios é feita em trechos da rota
    private const MAP_POINTS = 1500;            // pontos enviados para desenhar a rota no mapa

    public static function isConfigured(): bool
    {
        return config('ors_api_key') !== '';
    }

    /**
     * Planeja a viagem: endereços e rota. Os pedágios vêm depois, em outra chamada (tolls), para o mapa
     * aparecer logo mesmo quando a busca de pedágios demora. Lança TripException com mensagem para o usuário.
     */
    public static function plan(string $originText, string $destinationText): array
    {
        $origin = self::geocode($originText);
        if ($origin === null) {
            throw new TripException('Não encontramos o endereço de partida. Tente incluir a cidade e o estado (ex.: "Av. Paulista, São Paulo, SP").', 404);
        }
        $destination = self::geocode($destinationText);
        if ($destination === null) {
            throw new TripException('Não encontramos o endereço de destino. Tente incluir a cidade e o estado.', 404);
        }

        [$routeId, $route] = self::route($origin, $destination);

        return [
            'origin' => $origin,
            'destination' => $destination,
            'distanceMeters' => $route['distance'],
            'durationSeconds' => $route['duration'],
            'path' => self::mapPath($route['coordinates']),
            'routeId' => $routeId, // usado em tolls() para buscar os pedágios desta rota
        ];
    }

    /**
     * Praças de pedágio de uma rota já calculada por plan(). null = busca indisponível agora.
     */
    public static function tolls(string $routeId): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $routeId)) {
            throw new TripException('Rota inválida.', 422);
        }
        $route = self::cacheGetById($routeId, self::CACHE_DAYS_ROUTE);
        if ($route === null) {
            throw new TripException('Calcule a rota de novo.', 404);
        }
        try {
            return self::tollsAlongRoute($route['coordinates']);
        } catch (Throwable $exception) {
            ErrorHandler::log('[viagem] pedágios indisponíveis: ' . $exception->getMessage());

            return null; // a rota continua valendo; só os pedágios ficam de fora
        }
    }

    /* ---------- Endereço → coordenadas (OpenRouteService Geocode) ---------- */
    private static function geocode(string $addressText): ?array
    {
        $cacheKey = 'geocode:' . preg_replace('/\s+/', ' ', normalize_text(trim($addressText)));
        $cached = self::cacheGet($cacheKey, self::CACHE_DAYS_ADDRESS);
        if ($cached !== null) {
            return $cached['found'] ? $cached['place'] : null;
        }

        self::countUsage('geocode');
        $url = self::ORS_URL . '/geocode/search?' . http_build_query([
            'api_key' => config('ors_api_key'),
            'text' => $addressText,
            'boundary.country' => 'BR',
            'size' => 1,
            'lang' => 'pt',
        ]);
        [$status, $body] = HttpClient::request('GET', $url, ['Accept: application/json']);
        if ($status !== 200) {
            self::failFromOrs($status, $body);
        }
        $feature = json_decode($body, true)['features'][0] ?? null;
        $place = $feature === null ? null : [
            'label' => (string) ($feature['properties']['label'] ?? $addressText),
            'lat' => (float) $feature['geometry']['coordinates'][1],
            'lon' => (float) $feature['geometry']['coordinates'][0],
        ];
        self::cacheSet($cacheKey, ['found' => $place !== null, 'place' => $place]);

        return $place;
    }

    /* ---------- Rota mais rápida de carro (OpenRouteService Directions) ---------- */
    /**
     * Devolve [id da rota, rota]. O id (sha1 da chave do cache) permite buscar os pedágios depois.
     */
    private static function route(array $origin, array $destination): array
    {
        $cacheKey = sprintf('route:%.4f,%.4f>%.4f,%.4f', $origin['lat'], $origin['lon'], $destination['lat'], $destination['lon']);
        $cached = self::cacheGet($cacheKey, self::CACHE_DAYS_ROUTE);
        if ($cached !== null) {
            return [sha1($cacheKey), $cached];
        }

        self::countUsage('route');
        [$status, $body] = HttpClient::request(
            'POST',
            self::ORS_URL . '/v2/directions/driving-car/geojson',
            ['Authorization: ' . config('ors_api_key'), 'Content-Type: application/json', 'Accept: application/geo+json'],
            json_encode([
                'coordinates' => [[$origin['lon'], $origin['lat']], [$destination['lon'], $destination['lat']]],
                'preference' => 'fastest',
                'instructions' => false,
                'units' => 'm',
            ]),
            30
        );
        if ($status !== 200) {
            self::failFromOrs($status, $body);
        }
        $feature = json_decode($body, true)['features'][0] ?? null;
        if ($feature === null) {
            throw new TripException('Não encontramos um caminho de carro entre esses endereços.', 404);
        }
        $route = [
            'distance' => (float) ($feature['properties']['summary']['distance'] ?? 0),
            'duration' => (float) ($feature['properties']['summary']['duration'] ?? 0),
            'coordinates' => $feature['geometry']['coordinates'], // [lon, lat]
        ];
        self::cacheSet($cacheKey, $route);

        return [sha1($cacheKey), $route];
    }

    /* ---------- Praças de pedágio no caminho (Overpass / OpenStreetMap) ----------
       A rota é dividida em trechos; para cada trecho, pede ao Overpass as cabines de pedágio
       (barrier=toll_booth) dentro do retângulo do trecho. Depois fica só com as que estão
       a até 12 m da rota e junta as cabines vizinhas numa única praça. */
    private static function tollsAlongRoute(array $coordinates): array
    {
        $cacheKey = 'tolls:' . md5(json_encode([$coordinates[0], end($coordinates), count($coordinates)]));
        $cached = self::cacheGet($cacheKey, self::CACHE_DAYS_ROUTE);
        if ($cached !== null) {
            return $cached;
        }

        $segments = self::splitRoute($coordinates);
        $queryParts = [];
        foreach ($segments as $segment) {
            $box = sprintf('%.5f,%.5f,%.5f,%.5f', $segment['south'], $segment['west'], $segment['north'], $segment['east']);
            $queryParts[] = "node[\"barrier\"=\"toll_booth\"]({$box});way[\"barrier\"=\"toll_booth\"]({$box});";
        }
        $query = '[out:json][timeout:25];(' . implode('', $queryParts) . ');out center tags;';
        $body = null;
        $failures = [];
        foreach (self::OVERPASS_URLS as $overpassUrl => $timeoutSeconds) {
            try {
                [$status, $responseBody] = HttpClient::request('POST', $overpassUrl, ['Content-Type: application/x-www-form-urlencoded'], 'data=' . rawurlencode($query), $timeoutSeconds);
            } catch (Throwable $exception) {
                $failures[] = parse_url($overpassUrl, PHP_URL_HOST) . ': ' . $exception->getMessage();
                continue;
            }
            if ($status === 200 && isset(json_decode($responseBody, true)['elements'])) {
                $body = $responseBody;
                break;
            }
            $failures[] = parse_url($overpassUrl, PHP_URL_HOST) . ": HTTP {$status}";
        }
        if ($body === null) {
            throw new RuntimeException('Overpass indisponível (' . implode('; ', $failures) . ')');
        }

        $booths = [];
        foreach (json_decode($body, true)['elements'] ?? [] as $element) {
            $lat = (float) ($element['lat'] ?? $element['center']['lat'] ?? 0);
            $lon = (float) ($element['lon'] ?? $element['center']['lon'] ?? 0);
            $position = self::positionOnRoute($lat, $lon, $coordinates, $segments);
            if ($position === null) {
                continue;
            }
            $tags = $element['tags'] ?? [];
            $booths[] = [
                'lat' => round($lat, 6),
                'lon' => round($lon, 6),
                'name' => trim(implode(' · ', array_filter([(string) ($tags['name'] ?? $tags['note'] ?? ''), (string) ($tags['operator'] ?? '')]))),
                'price' => self::carPrice((string) ($tags['charge'] ?? '')),
                'checkedAt' => (string) ($tags['check_date'] ?? $tags['charge:check_date'] ?? ''),
                'position' => $position,
            ];
        }

        // Ordem do caminho e uma praça por grupo de cabines vizinhas
        usort($booths, fn (array $first, array $second) => $first['position'] <=> $second['position']);
        $plazas = [];
        foreach ($booths as $booth) {
            $last = end($plazas);
            if ($last !== false && self::distanceMeters($last['lat'], $last['lon'], $booth['lat'], $booth['lon']) < self::SAME_PLAZA_METERS) {
                // Mesma praça: completa nome e valor que faltarem
                $lastIndex = array_key_last($plazas);
                foreach (['name', 'price', 'checkedAt'] as $field) {
                    if (empty($plazas[$lastIndex][$field]) && !empty($booth[$field])) {
                        $plazas[$lastIndex][$field] = $booth[$field];
                    }
                }
                continue;
            }
            unset($booth['position']);
            $plazas[] = $booth;
        }
        self::cacheSet($cacheKey, $plazas);

        return $plazas;
    }

    /**
     * Valor para carro na tag charge do OpenStreetMap (ex.: "40.60BRL/motorcar;0.00BRL/motorcycle"), ou null.
     */
    private static function carPrice(string $charge): ?float
    {
        if (preg_match('#([\d.,]+)\s*BRL\s*/\s*(motorcar|motor_vehicle|vehicle)\b#i', $charge, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return null;
    }

    /**
     * Divide a rota em trechos de ~60 km, cada um com o retângulo que o envolve (com uma pequena folga).
     */
    private static function splitRoute(array $coordinates): array
    {
        $segments = [];
        $start = 0;
        $traveled = 0.0;
        $count = count($coordinates);
        for ($index = 1; $index < $count; $index++) {
            $traveled += self::distanceMeters($coordinates[$index - 1][1], $coordinates[$index - 1][0], $coordinates[$index][1], $coordinates[$index][0]);
            if ($traveled >= self::SEGMENT_KILOMETERS * 1000 || $index === $count - 1) {
                $slice = array_slice($coordinates, $start, $index - $start + 1);
                $latitudes = array_column($slice, 1);
                $longitudes = array_column($slice, 0);
                $padding = 0.002; // ~200 m
                $segments[] = [
                    'from' => $start,
                    'to' => $index,
                    'south' => min($latitudes) - $padding,
                    'north' => max($latitudes) + $padding,
                    'west' => min($longitudes) - $padding,
                    'east' => max($longitudes) + $padding,
                ];
                $start = $index;
                $traveled = 0.0;
            }
        }

        return $segments;
    }

    /**
     * Posição da cabine ao longo da rota (índice do ponto mais próximo) se ela estiver a até 70 m; senão null.
     */
    private static function positionOnRoute(float $lat, float $lon, array $coordinates, array $segments): ?int
    {
        foreach ($segments as $segment) {
            if ($lat < $segment['south'] || $lat > $segment['north'] || $lon < $segment['west'] || $lon > $segment['east']) {
                continue;
            }
            for ($index = $segment['from']; $index < $segment['to']; $index++) {
                $distance = self::distanceToSegmentMeters($lat, $lon, $coordinates[$index], $coordinates[$index + 1]);
                if ($distance <= self::TOLL_DISTANCE_METERS) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Distância de um ponto a um trecho reto da rota (projeção plana local: boa para distâncias curtas).
     */
    private static function distanceToSegmentMeters(float $lat, float $lon, array $pointA, array $pointB): float
    {
        $metersPerDegreeLat = 111320.0;
        $metersPerDegreeLon = 111320.0 * cos(deg2rad($lat));
        $ax = ($pointA[0] - $lon) * $metersPerDegreeLon;
        $ay = ($pointA[1] - $lat) * $metersPerDegreeLat;
        $bx = ($pointB[0] - $lon) * $metersPerDegreeLon;
        $by = ($pointB[1] - $lat) * $metersPerDegreeLat;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;

        return hypot($ax + $t * $dx, $ay + $t * $dy);
    }

    private static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /**
     * Pontos para desenhar a rota no mapa ([lat, lon]), reduzidos para a resposta ficar leve.
     */
    private static function mapPath(array $coordinates): array
    {
        $step = max(1, (int) ceil(count($coordinates) / self::MAP_POINTS));
        $path = [];
        foreach ($coordinates as $index => $point) {
            if ($index % $step === 0 || $index === count($coordinates) - 1) {
                $path[] = [round($point[1], 5), round($point[0], 5)];
            }
        }

        return $path;
    }

    /* ---------- Cota diária e cache ---------- */
    private static function countUsage(string $kind): void
    {
        $usageFile = self::cacheFolder() . '/usage-' . date('Y-m-d') . '.json';
        $handle = fopen($usageFile, 'c+');
        flock($handle, LOCK_EX);
        $usage = json_decode((string) stream_get_contents($handle), true) ?: [];
        $usage[$kind] = ($usage[$kind] ?? 0) + 1;
        if ($usage[$kind] > self::DAILY_LIMITS[$kind]) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new TripException('A calculadora de viagem atingiu o limite de consultas de hoje. Tente de novo amanhã.', 429);
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($usage));
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function cacheFolder(): string
    {
        $folder = BASE_PATH . self::CACHE_FOLDER;
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder;
    }

    private static function cacheGet(string $key, int $days): mixed
    {
        return self::cacheGetById(sha1($key), $days);
    }

    private static function cacheGetById(string $cacheId, int $days): mixed
    {
        $file = self::cacheFolder() . '/' . $cacheId . '.json';
        if (!is_file($file) || filemtime($file) < time() - $days * 86400) {
            return null;
        }

        return json_decode((string) file_get_contents($file), true);
    }

    private static function cacheSet(string $key, mixed $value): void
    {
        file_put_contents(self::cacheFolder() . '/' . sha1($key) . '.json', json_encode($value), LOCK_EX);
    }

    private static function failFromOrs(int $status, string $body): never
    {
        ErrorHandler::log("[viagem] OpenRouteService respondeu {$status}: " . mb_substr($body, 0, 300));
        $orsCode = (int) (json_decode($body, true)['error']['code'] ?? 0);
        if ($status === 404 || in_array($orsCode, [2009, 2010], true)) {
            throw new TripException('Não encontramos um caminho de carro entre esses endereços.', 404);
        }
        if ($status === 403 || $status === 401) {
            throw new TripException('O serviço de rotas não está configurado corretamente (chave do OpenRouteService).', 503);
        }
        if ($status === 429) {
            throw new TripException('Muitas consultas ao serviço de rotas agora. Tente de novo em alguns minutos.', 429);
        }
        throw new TripException('O serviço de rotas não respondeu. Tente de novo em instantes.', 502);
    }
}
