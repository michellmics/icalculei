<?php

declare(strict_types=1);

/**
 * Gera os banners de compartilhamento (og:image, 1200×630) que aparecem no WhatsApp, Facebook, LinkedIn etc.
 *   php bin/og-images.php
 * Cria public/assets/img/og/{id-da-calculadora}.png, vibe2000.png (página inicial e demais páginas) e feriados.png.
 * Rode de novo depois de criar ou renomear uma calculadora (precisa da extensão GD com FreeType).
 * Fonte: IBM Plex Sans (licença OFL, em bin/fonts).
 */

require __DIR__ . '/../app/bootstrap.php';

const WIDTH = 1200;
const HEIGHT = 630;
const MARGIN = 80;
const OUTPUT_DIR = BASE_PATH . '/public/assets/img/og';
const FONT_BOLD = __DIR__ . '/fonts/IBMPlexSans-Bold.ttf';
const FONT_REGULAR = __DIR__ . '/fonts/IBMPlexSans-Regular.ttf';

if (!function_exists('imagettftext')) {
    exit("O PHP precisa da extensão GD com FreeType.\n");
}
if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0755, true);
}

$content = require BASE_PATH . '/content/tools.php';
$created = 0;

drawBanner(OUTPUT_DIR . '/vibe2000.png', 'CALCULADORAS GRÁTIS', 'Calculadoras e conversores para o dia a dia', 'Salário, rescisão, FGTS, investimentos, financiamento, IPVA, feriados e muito mais.');
drawBanner(OUTPUT_DIR . '/feriados.png', 'CALENDÁRIO', 'Feriados nacionais, estaduais e da sua capital', 'Dia da semana, feriadões e dias úteis de cada mês, para os 27 estados.');
$created += 2;
foreach ($content['tools'] as $toolId => $tool) {
    if (empty($tool['ready'])) {
        continue;
    }
    $category = mb_strtoupper($content['categories'][$tool['category']] ?? 'Calculadora');
    drawBanner(OUTPUT_DIR . "/{$toolId}.png", $category . ' · GRÁTIS', $tool['name'], $tool['description']);
    $created++;
}
echo "{$created} banners criados em public/assets/img/og/\n";

/**
 * Desenha um banner: marca no topo, categoria, título grande, descrição e o endereço do site embaixo.
 */
function drawBanner(string $path, string $kicker, string $title, string $description): void
{
    $image = imagecreatetruecolor(WIDTH, HEIGHT);
    imageantialias($image, true);
    $background = color($image, '#1c2b24');
    $green = color($image, '#0e6b4f');
    $lime = color($image, '#c8f7a3');
    $muted = color($image, '#8fb39e');
    $white = color($image, '#ffffff');
    $soft = color($image, '#d5e4da');
    $yellow = color($image, '#f0b429');
    $dark = color($image, '#17221d');

    imagefilledrectangle($image, 0, 0, WIDTH, HEIGHT, $background);
    // Faixa verde à esquerda e círculos decorativos à direita
    imagefilledrectangle($image, 0, 0, 14, HEIGHT, $green);
    imagefilledellipse($image, WIDTH - 40, 70, 360, 360, color($image, '#22362c'));
    imagefilledellipse($image, WIDTH - 40, 70, 220, 220, color($image, '#284034'));

    // Marca: quadrado verde com a calculadora + "Vibe2000"
    drawLogo($image, MARGIN, 56, 76, $green, $white);
    imagettftext($image, 38, 0, MARGIN + 98, 110, $white, FONT_BOLD, 'Vibe2000');

    // Categoria
    imagettftext($image, 22, 0, MARGIN, 200, $muted, FONT_BOLD, spaced($kicker));

    // Título: o maior tamanho que couber em até 2 linhas (3 se for muito longo)
    $maxWidth = WIDTH - MARGIN * 2;
    foreach ([72, 66, 60, 54, 48] as $size) {
        $titleLines = wrapText($title, FONT_BOLD, $size, $maxWidth);
        if (count($titleLines) <= 2) {
            break;
        }
    }
    // Linhas de base: título logo abaixo da categoria; a descrição termina antes do rodapé (y 522)
    $lineHeight = (int) round($size * 1.15);
    $y = 200 + 28 + $size;
    foreach (array_slice($titleLines, 0, 3) as $line) {
        imagettftext($image, $size, 0, MARGIN, $y, $white, FONT_BOLD, $line);
        $lastTitleBaseline = $y;
        $y += $lineHeight;
    }

    // Descrição (até 2 linhas)
    $descriptionLines = wrapText($description, FONT_REGULAR, 28, $maxWidth);
    $y = $lastTitleBaseline + 62;
    foreach (array_slice($descriptionLines, 0, 2) as $index => $line) {
        if ($index === 1 && count($descriptionLines) > 2) {
            $line = rtrim($line, ' .,;') . '…';
        }
        imagettftext($image, 28, 0, MARGIN, $y, $soft, FONT_REGULAR, $line);
        $y += 40;
    }
    if ($y - 40 > 490) {
        echo "aviso: texto longo demais em " . basename($path) . "
";
    }

    // Rodapé: endereço do site e o "botão"
    imagettftext($image, 30, 0, MARGIN, HEIGHT - 58, $lime, FONT_BOLD, 'vibe2000.com.br');
    $buttonText = 'Calcule grátis  →';
    $buttonBox = imagettfbbox(26, 0, FONT_BOLD, $buttonText);
    $buttonWidth = $buttonBox[2] - $buttonBox[0] + 56;
    $buttonX = WIDTH - MARGIN - $buttonWidth;
    roundedRectangle($image, $buttonX, HEIGHT - 108, $buttonX + $buttonWidth, HEIGHT - 40, 34, $yellow);
    imagettftext($image, 26, 0, $buttonX + 28, HEIGHT - 61, $dark, FONT_BOLD, $buttonText);

    imagepng($image, $path, 9);
    imagedestroy($image);
}

/**
 * Calculadora simplificada dentro de um quadrado verde de cantos redondos (a mesma ideia do logo do site).
 */
function drawLogo(GdImage $image, int $x, int $y, int $size, int $boxColor, int $lineColor): void
{
    roundedRectangle($image, $x, $y, $x + $size, $y + $size, (int) ($size * 0.22), $boxColor);
    $scale = $size / 24;
    imagesetthickness($image, max(2, (int) round(1.8 * $scale)));
    $point = fn (float $value, int $origin) => (int) round($origin + $value * $scale);
    imagerectangle($image, $point(5.5, $x), $point(3, $y), $point(18.5, $x), $point(21, $y), $lineColor);
    imagerectangle($image, $point(8, $x), $point(5.5, $y), $point(16, $x), $point(9, $y), $lineColor);
    foreach ([[9, 12.5], [12, 12.5], [15, 12.5], [9, 15.5], [12, 15.5], [9, 18.5], [12, 18.5]] as [$dotX, $dotY]) {
        imagefilledellipse($image, $point($dotX, $x), $point($dotY, $y), (int) (2 * $scale), (int) (2 * $scale), $lineColor);
    }
    imageline($image, $point(15, $x), $point(15.5, $y), $point(15, $x), $point(18.5, $y), $lineColor);
    imagesetthickness($image, 1);
}

function roundedRectangle(GdImage $image, int $left, int $top, int $right, int $bottom, int $radius, int $color): void
{
    imagefilledrectangle($image, $left + $radius, $top, $right - $radius, $bottom, $color);
    imagefilledrectangle($image, $left, $top + $radius, $right, $bottom - $radius, $color);
    foreach ([[$left + $radius, $top + $radius], [$right - $radius, $top + $radius], [$left + $radius, $bottom - $radius], [$right - $radius, $bottom - $radius]] as [$centerX, $centerY]) {
        imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $color);
    }
}

/**
 * Quebra o texto em linhas que cabem na largura.
 */
function wrapText(string $text, string $font, int $size, int $maxWidth): array
{
    $lines = [];
    $current = '';
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        if ($current !== '' && $box[2] - $box[0] > $maxWidth) {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

/**
 * "FINANÇAS" → "F I N A N Ç A S" com espaço fino (letras espaçadas, como no site).
 */
function spaced(string $text): string
{
    return implode("\u{2009}", mb_str_split($text));
}

function color(GdImage $image, string $hex): int
{
    [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

    return imagecolorallocate($image, $red, $green, $blue);
}
