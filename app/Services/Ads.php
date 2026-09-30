<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Monta o HTML de cada espaço de anúncio configurado em content/ads.php.
 */
class Ads
{
    public static function isEnabled(): bool
    {
        return config('adsense_client') !== '';
    }

    public static function slot(string $slotName): string
    {
        $slot = Content::adSlots()[$slotName] ?? null;
        if ($slot === null) {
            return '';
        }

        $cssClass = 'ad-slot ad-' . $slot['size'];

        // Anúncio de verdade: o site.js pede para o Google preencher cada <ins class="adsbygoogle">
        if (self::isEnabled() && $slot['slot_id'] !== '') {
            return '<div class="' . e($cssClass) . ' ad-live"><span class="ad-label">Publicidade</span>'
                . '<ins class="adsbygoogle" style="display:block" data-ad-client="' . e(config('adsense_client')) . '"'
                . ' data-ad-slot="' . e($slot['slot_id']) . '" data-ad-format="auto" data-full-width-responsive="true"></ins></div>';
        }

        // Em desenvolvimento, uma caixa cinza mostra onde o anúncio vai ficar
        if (config('ads_placeholders')) {
            return '<div class="' . e($cssClass) . '"><div><b>Publicidade</b>' . e($slot['description']) . '<br><code>' . e($slotName) . '</code></div></div>';
        }

        return '';
    }
}
