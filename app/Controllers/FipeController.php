<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\Http;
use App\Models\RateLimit;
use App\Services\FipeClient;
use App\Services\FipeException;
use Throwable;

/**
 * API da consulta FIPE (calculadora de depreciação):
 *   GET /api/fipe/marcas?tipo=1
 *   GET /api/fipe/modelos?tipo=1&marca=59
 *   GET /api/fipe/anos?tipo=1&marca=59&modelo=8323
 *   GET /api/fipe/historico?tipo=1&marca=59&modelo=8323&ano=2020-5
 * tipo: 1 carro, 2 moto, 3 caminhão.
 */
class FipeController
{
    private const MAX_LIST_REQUESTS_PER_HOUR = 300;
    private const MAX_HISTORY_REQUESTS_PER_HOUR = 60;

    public function brands(): void
    {
        $vehicleType = $this->vehicleType();
        $this->respond('fipe-list', self::MAX_LIST_REQUESTS_PER_HOUR, fn () => ['items' => FipeClient::brands($vehicleType)]);
    }

    public function models(): void
    {
        $vehicleType = $this->vehicleType();
        $brandCode = $this->positiveInt('marca');
        $this->respond('fipe-list', self::MAX_LIST_REQUESTS_PER_HOUR, fn () => ['items' => FipeClient::models($vehicleType, $brandCode)]);
    }

    public function years(): void
    {
        $vehicleType = $this->vehicleType();
        $brandCode = $this->positiveInt('marca');
        $modelCode = $this->positiveInt('modelo');
        $this->respond('fipe-list', self::MAX_LIST_REQUESTS_PER_HOUR, fn () => ['items' => FipeClient::years($vehicleType, $brandCode, $modelCode)]);
    }

    public function history(): void
    {
        $vehicleType = $this->vehicleType();
        $brandCode = $this->positiveInt('marca');
        $modelCode = $this->positiveInt('modelo');
        $yearValue = (string) ($_GET['ano'] ?? '');
        $this->respond('fipe-history', self::MAX_HISTORY_REQUESTS_PER_HOUR, fn () => FipeClient::history($vehicleType, $brandCode, $modelCode, $yearValue));
    }

    private function vehicleType(): int
    {
        $vehicleType = (int) ($_GET['tipo'] ?? 1);

        return isset(FipeClient::VEHICLE_TYPES[$vehicleType]) ? $vehicleType : 1;
    }

    private function positiveInt(string $parameter): int
    {
        $value = (int) ($_GET[$parameter] ?? 0);
        if ($value <= 0) {
            Http::json(['error' => 'Escolha marca, modelo e ano.'], 422);
        }

        return $value;
    }

    private function respond(string $limitAction, int $maxPerHour, callable $buildResponse): void
    {
        Http::rejectOtherSites();
        $limitKey = RateLimit::key($limitAction, $_SERVER['REMOTE_ADDR'] ?? '');
        if (RateLimit::tooManyAttempts($limitKey, $maxPerHour)) {
            Http::json(['error' => 'Você fez muitas consultas seguidas. Aguarde alguns minutos e tente de novo.'], 429);
        }
        RateLimit::hit($limitKey, 60);

        try {
            Http::json($buildResponse());
        } catch (FipeException $exception) {
            Http::json(['error' => $exception->getMessage()], $exception->getCode() ?: 400);
        } catch (Throwable $exception) {
            ErrorHandler::log('[fipe] erro: ' . $exception->getMessage());
            Http::json(['error' => 'A Tabela FIPE não respondeu agora. Tente de novo em instantes.'], 502);
        }
    }
}
