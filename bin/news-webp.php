<?php

declare(strict_types=1);

/**
 * Cria as versões WebP (mais leves, ajudam na velocidade e no SEO) de cada foto de notícia
 * em public/assets/img/news/:
 *   {nome}.webp       960 px (telas grandes e página da notícia)
 *   {nome}-480.webp   480 px (cartões e celular)
 * O site escolhe a melhor para cada tela (srcset) e usa o JPG só para redes sociais e Google.
 * Rode depois de adicionar uma foto nova: php bin/news-webp.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado pelo terminal.');
}
if (!function_exists('imagewebp')) {
    exit('O PHP precisa da extensão GD com suporte a WebP.' . PHP_EOL);
}

const WEBP_QUALITY = 78;
const SMALL_WIDTH = 480;
$photoFolder = dirname(__DIR__) . '/public/assets/img/news';

foreach (glob($photoFolder . '/*.jpg') ?: [] as $jpgPath) {
    $baseName = substr($jpgPath, 0, -4);
    $bigPath = $baseName . '.webp';
    $smallPath = $baseName . '-' . SMALL_WIDTH . '.webp';
    $isUpToDate = is_file($bigPath) && is_file($smallPath) && filemtime($bigPath) >= filemtime($jpgPath) && filemtime($smallPath) >= filemtime($jpgPath);
    if ($isUpToDate) {
        continue;
    }

    $image = imagecreatefromjpeg($jpgPath);
    imagewebp($image, $bigPath, WEBP_QUALITY);

    $smallHeight = (int) round(imagesy($image) * SMALL_WIDTH / imagesx($image));
    $small = imagecreatetruecolor(SMALL_WIDTH, $smallHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, SMALL_WIDTH, $smallHeight, imagesx($image), imagesy($image));
    imagewebp($small, $smallPath, WEBP_QUALITY);

    printf("%s: JPG %d KB → WebP %d KB + %d px %d KB\n", basename($jpgPath), filesize($jpgPath) / 1024, filesize($bigPath) / 1024, SMALL_WIDTH, filesize($smallPath) / 1024);
}
echo 'Pronto.' . PHP_EOL;
