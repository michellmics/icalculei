<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\Http;
use App\Models\RateLimit;
use App\Services\TripException;
use App\Services\TripPlanner;
use Throwable;

/**
 * API da calculadora de viagem (o combustível é calculado no navegador):
 *   GET /api/viagem/rota?origem=...&destino=...   endereços, distância, tempo e o desenho da rota
 *   GET /api/viagem/pedagios?rota=ID              praças de pedágio da rota (chamada logo depois)
 */
class TripController
{
    private const MAX_ROUTES_PER_HOUR = 30;   // por visitante (IP), para ninguém gastar a cota do dia
    private const MAX_TOLL_LOOKUPS_PER_HOUR = 60;
    private const MIN_ADDRESS_LENGTH = 3;
    private const MAX_ADDRESS_LENGTH = 150;

    public function route(): void
    {
        Http::rejectOtherSites();
        if (!TripPlanner::isConfigured()) {
            Http::json(['error' => 'A calculadora de viagem ainda não foi configurada (falta a chave ORS_API_KEY no .env).'], 503);
        }

        $originText = trim((string) ($_GET['origem'] ?? ''));
        $destinationText = trim((string) ($_GET['destino'] ?? ''));
        foreach ([$originText, $destinationText] as $addressText) {
            $length = mb_strlen($addressText);
            if ($length < self::MIN_ADDRESS_LENGTH || $length > self::MAX_ADDRESS_LENGTH) {
                Http::json(['error' => 'Preencha os endereços de partida e de destino.'], 422);
            }
        }
        $this->applyRateLimit('trip-route', self::MAX_ROUTES_PER_HOUR);

        $this->respond(fn () => TripPlanner::plan($originText, $destinationText));
    }

    public function tolls(): void
    {
        Http::rejectOtherSites();
        $this->applyRateLimit('trip-tolls', self::MAX_TOLL_LOOKUPS_PER_HOUR);
        $routeId = (string) ($_GET['rota'] ?? '');

        $this->respond(fn () => ['tolls' => TripPlanner::tolls($routeId)]);
    }

    private function applyRateLimit(string $action, int $maxPerHour): void
    {
        $limitKey = RateLimit::key($action, $_SERVER['REMOTE_ADDR'] ?? '');
        if (RateLimit::tooManyAttempts($limitKey, $maxPerHour)) {
            Http::json(['error' => 'Você fez muitas consultas seguidas. Aguarde alguns minutos e tente de novo.'], 429);
        }
        RateLimit::hit($limitKey, 60);
    }

    private function respond(callable $buildResponse): void
    {
        try {
            Http::json($buildResponse());
        } catch (TripException $exception) {
            Http::json(['error' => $exception->getMessage()], $exception->getCode() ?: 400);
        } catch (Throwable $exception) {
            ErrorHandler::log('[viagem] erro: ' . $exception->getMessage());
            Http::json(['error' => 'Não foi possível calcular a rota agora. Tente de novo em instantes.'], 503);
        }
    }
}
