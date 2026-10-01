<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Erro da calculadora de viagem com mensagem pronta para mostrar ao usuário
 * e o código HTTP da resposta (ex.: 404 endereço não encontrado, 429 limite do dia).
 */
class TripException extends RuntimeException
{
}
