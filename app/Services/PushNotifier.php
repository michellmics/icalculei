<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Models\PushSubscription;
use Throwable;

/**
 * Notificações do app do painel: manda a mensagem para todos os aparelhos que ativaram os avisos.
 */
class PushNotifier
{
    public const VISITOR_MILESTONE = 1000; // avisa a cada 1.000 visitantes

    /**
     * Envia para todos os aparelhos. Apaga inscrições vencidas (celular trocado, app removido).
     * Devolve quantos aparelhos receberam.
     */
    public static function notifyAll(string $title, string $body, string $url = '/painel/visitas'): int
    {
        $delivered = 0;
        foreach (PushSubscription::all() as $subscription) {
            try {
                $status = WebPush::send($subscription, ['title' => $title, 'body' => $body, 'url' => $url]);
                if ($status >= 200 && $status < 300) {
                    $delivered++;
                } elseif (in_array($status, [404, 410], true)) {
                    PushSubscription::delete((int) $subscription['id']);
                } else {
                    ErrorHandler::log("[push] {$subscription['device']}: HTTP {$status}");
                }
            } catch (Throwable $exception) {
                ErrorHandler::log("[push] {$subscription['device']}: " . $exception->getMessage());
            }
        }

        return $delivered;
    }

    /**
     * Chamado a cada visitante novo com o total atualizado; avisa quando bate um múltiplo de 1.000.
     */
    public static function checkVisitorMilestone(int $totalVisitors): void
    {
        if ($totalVisitors <= 0 || $totalVisitors % self::VISITOR_MILESTONE !== 0) {
            return;
        }
        self::notifyAll(
            '🎉 ' . format_number($totalVisitors) . ' visitantes!',
            'O iCalculei acaba de chegar a ' . format_number($totalVisitors) . ' visitantes desde o início. Toque para ver as visitas.'
        );
    }
}
