<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Erro na consulta à Tabela FIPE, com mensagem pronta para o usuário e o código HTTP da resposta.
 */
class FipeException extends RuntimeException
{
}
