<?php

declare(strict_types=1);

/**
 * Gera os ícones dos dois apps (PWA) em public/icons/ com o logo do iCalculei.
 *   php bin/app-icons.php
 * site-* = verde com desenho branco; painel-* = escuro com desenho verde-claro.
 * O favicon (public/favicon.svg) e o logo do topo (app/Views/partials/logo-mark.php) são SVG com o mesmo desenho.
 */

require __DIR__ . '/logo.php';

const ICONS_DIR = __DIR__ . '/../public/icons';

$themes = [
    'site' => ['#0e6b4f', '#ffffff'],
    'painel' => ['#1c2b24', '#c8f7a3'],
];
// nome => [tamanho, escala do desenho, cantos redondos]
$variants = [
    '192' => [192, 1.0, true],
    '512' => [512, 1.0, true],
    'maskable-512' => [512, 0.8, false], // o Android corta as bordas: desenho menor, fundo cheio
    'apple-touch' => [180, 0.9, false],  // o iPhone arredonda sozinho
];

foreach ($themes as $theme => [$background, $foreground]) {
    foreach ($variants as $variant => [$size, $scale, $rounded]) {
        $image = renderLogo($size, $background, $foreground, $scale, $rounded);
        imagepng($image, ICONS_DIR . "/{$theme}-{$variant}.png", 9);
        imagedestroy($image);
    }
}
echo count($themes) * count($variants) . " ícones criados em public/icons/\n";
