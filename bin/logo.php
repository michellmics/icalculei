<?php

declare(strict_types=1);

/**
 * Desenho do logo do iCalculei com GD: um "i" e um ✓ ("eu calculei") num quadrado de cantos redondos.
 * Usado por bin/app-icons.php (ícones do app) e bin/og-images.php (banners).
 * Mesmo desenho de app/Views/partials/logo-mark.php e public/favicon.svg (grade de 24 × 24).
 */

const LOGO_STROKE = 2.6;
const LOGO_DOT = [6.2, 6.2, 1.7];                        // x, y, raio do pingo do "i"
const LOGO_LINES = [
    [[6.2, 10.2], [6.2, 18.2]],                          // perna do "i"
    [[9.7, 14.2], [12.7, 17.5], [18.2, 9.2]],            // ✓
];

/**
 * Devolve uma imagem quadrada (com transparência) do logo.
 * $glyphScale: tamanho do desenho em relação ao quadrado (1 = grade de 24 ocupando tudo).
 * $rounded: cantos redondos (false = fundo cheio, para ícones "maskable" e do iPhone).
 */
function renderLogo(int $size, string $background, string $foreground, float $glyphScale = 1.0, bool $rounded = true): GdImage
{
    $factor = 4; // desenha 4× maior e reduz no fim, para as bordas ficarem suaves
    $big = $size * $factor;
    $canvas = imagecreatetruecolor($big, $big);
    imagealphablending($canvas, false);
    imagefilledrectangle($canvas, 0, 0, $big, $big, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagealphablending($canvas, true);

    $backgroundColor = logoColor($canvas, $background);
    if ($rounded) {
        $radius = (int) round($big * 0.22);
        imagefilledrectangle($canvas, $radius, 0, $big - $radius, $big, $backgroundColor);
        imagefilledrectangle($canvas, 0, $radius, $big, $big - $radius, $backgroundColor);
        foreach ([[$radius, $radius], [$big - $radius, $radius], [$radius, $big - $radius], [$big - $radius, $big - $radius]] as [$cx, $cy]) {
            imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $backgroundColor);
        }
    } else {
        imagefilledrectangle($canvas, 0, 0, $big, $big, $backgroundColor);
    }

    // Grade de 24 centralizada no quadrado
    $unit = $big / 24 * $glyphScale;
    $offset = ($big - 24 * $unit) / 2;
    $point = fn (array $p) => [$offset + $p[0] * $unit, $offset + $p[1] * $unit];
    $foregroundColor = logoColor($canvas, $foreground);
    $half = LOGO_STROKE / 2 * $unit;

    [$dotX, $dotY] = $point(LOGO_DOT);
    imagefilledellipse($canvas, (int) round($dotX), (int) round($dotY), (int) round(LOGO_DOT[2] * 2 * $unit), (int) round(LOGO_DOT[2] * 2 * $unit), $foregroundColor);
    foreach (LOGO_LINES as $line) {
        $points = array_map($point, $line);
        foreach ($points as $index => [$x, $y]) {
            // ponta e junção redondas
            imagefilledellipse($canvas, (int) round($x), (int) round($y), (int) round($half * 2), (int) round($half * 2), $foregroundColor);
            if ($index === 0) {
                continue;
            }
            [$fromX, $fromY] = $points[$index - 1];
            $length = hypot($x - $fromX, $y - $fromY);
            $normalX = -($y - $fromY) / $length * $half;
            $normalY = ($x - $fromX) / $length * $half;
            imagefilledpolygon($canvas, [
                (int) round($fromX + $normalX), (int) round($fromY + $normalY),
                (int) round($x + $normalX), (int) round($y + $normalY),
                (int) round($x - $normalX), (int) round($y - $normalY),
                (int) round($fromX - $normalX), (int) round($fromY - $normalY),
            ], $foregroundColor);
        }
    }

    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagecopyresampled($image, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($canvas);
    return $image;
}

function logoColor(GdImage $image, string $hex): int
{
    [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
    return imagecolorallocate($image, $red, $green, $blue);
}
