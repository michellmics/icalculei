<?php

declare(strict_types=1);

/**
 * Cria a versão WebP (mais leve, ajuda na velocidade e no SEO) de cada foto de notícia
 * em public/assets/img/news/. O site mostra o WebP e usa o JPG como reserva.
 * Rode depois de adicionar uma foto nova: php bin/news-webp.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado pelo terminal.');
}
if (!function_exists('imagewebp')) {
    exit('O PHP precisa da extensão GD com suporte a WebP.' . PHP_EOL);
}

const WEBP_QUALITY = 78;
$photoFolder = dirname(__DIR__) . '/public/assets/img/news';

foreach (glob($photoFolder . '/*.jpg') ?: [] as $jpgPath) {
    $webpPath = substr($jpgPath, 0, -4) . '.webp';
    if (is_file($webpPath) && filemtime($webpPath) >= filemtime($jpgPath)) {
        continue; // já está em dia
    }
    $image = imagecreatefromjpeg($jpgPath);
    imagewebp($image, $webpPath, WEBP_QUALITY);
    printf("%s: %d KB (JPG) → %d KB (WebP)\n", basename($jpgPath), filesize($jpgPath) / 1024, filesize($webpPath) / 1024);
}
echo 'Pronto.' . PHP_EOL;
