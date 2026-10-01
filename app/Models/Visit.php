<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Grava as visitas (tabelas visits, visits_online e tool_uses).
 */
class Visit
{
    /**
     * $country: código do país ("BR"); $region: código do estado ("SP"). Null quando o Cloudflare não informa.
     */
    public static function recordPageView(string $visitor, bool $isNew, string $page, string $source, string $device, ?string $country, ?string $region): void
    {
        Database::insert(
            'INSERT INTO visits (visitor, is_new, page, source, device, country, region) VALUES (:visitor, :is_new, :page, :source, :device, :country, :region)',
            ['visitor' => $visitor, 'is_new' => $isNew ? 1 : 0, 'page' => $page, 'source' => $source, 'device' => $device, 'country' => $country, 'region' => $region]
        );
    }

    /**
     * "Ainda estou aqui": mantém a pessoa na lista de online.
     * Se ela ficou mais de 30 min sem aparecer, conta como uma nova entrada.
     */
    public static function markOnline(string $visitor, string $page, string $device): void
    {
        Database::execute(
            'INSERT INTO visits_online (visitor, page, device) VALUES (:visitor, :page, :device)
             ON DUPLICATE KEY UPDATE
                entered_at = IF(seen_at < NOW() - INTERVAL 30 MINUTE, NOW(), entered_at),
                seen_at = NOW(), page = VALUES(page), device = VALUES(device)',
            ['visitor' => $visitor, 'page' => $page, 'device' => $device]
        );
    }

    public static function markOffline(string $visitor): void
    {
        Database::execute('DELETE FROM visits_online WHERE visitor = :visitor', ['visitor' => $visitor]);
    }

    /**
     * Apaga da tabela de online quem sumiu há mais de um dia (limpeza ocasional).
     */
    public static function cleanOldOnline(): void
    {
        Database::execute('DELETE FROM visits_online WHERE seen_at < NOW() - INTERVAL 1 DAY');
    }

    /**
     * Soma 1 ao total de visitantes desde o início e devolve o novo total.
     * LAST_INSERT_ID(valor) faz a soma e a leitura juntas: dois visitantes ao mesmo tempo nunca recebem o mesmo número.
     */
    public static function countNewVisitor(): int
    {
        Database::execute("UPDATE site_counters SET value = LAST_INSERT_ID(value + 1) WHERE name = 'visitors'");

        return (int) Database::fetchValue('SELECT LAST_INSERT_ID()');
    }

    public static function recordToolUse(string $toolId, string $visitor): void
    {
        Database::insert('INSERT INTO tool_uses (tool_id, visitor) VALUES (:tool_id, :visitor)', ['tool_id' => $toolId, 'visitor' => $visitor]);
    }
}
