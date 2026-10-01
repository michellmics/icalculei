<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;
use App\Core\Http;
use App\Services\EconomicIndicators;
use App\Services\MonetaryIndexes;
use Throwable;

/**
 * Índices do Banco Central (em cache):
 *   GET /api/indices                    IPCA, IGP-M e Selic para o quadro da página inicial
 *   GET /api/indices/serie?indice=ipca  série mensal completa para a calculadora de correção monetária
 */
class IndicatorsController
{
    public function series(): void
    {
        Http::rejectOtherSites();
        $indexKey = (string) ($_GET['indice'] ?? '');
        if (!isset(MonetaryIndexes::INDEXES[$indexKey])) {
            Http::json(['error' => 'Escolha um índice.'], 422);
        }
        try {
            Http::json(MonetaryIndexes::monthly($indexKey));
        } catch (Throwable $exception) {
            Http::json(['error' => 'O Banco Central não respondeu agora. Tente de novo em instantes.'], 502);
        }
    }

    public function show(): void
    {
        Http::rejectOtherSites();
        try {
            Http::json(EconomicIndicators::all());
        } catch (Throwable $exception) {
            ErrorHandler::log('[índices] erro: ' . $exception->getMessage());
            Http::json(['error' => 'Índices indisponíveis agora.'], 502);
        }
    }
}
