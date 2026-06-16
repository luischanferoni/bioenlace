<?php

/**
 * Genera favicon.svg / PNG / ICO para frontend y admin desde docs/logo/logo_icono.*
 *
 * Uso: php web/scripts/generate-favicons.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceSvg = $root . '/docs/logo/logo_icono.svg';
$sourcePng = $root . '/docs/logo/logo_icono.png';
$targets = [
    $root . '/frontend/web',
    $root . '/admin/web',
];

if (!is_file($sourceSvg)) {
    fwrite(STDERR, "No se encontró {$sourceSvg}\n");
    exit(1);
}

foreach ($targets as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "Directorio inexistente: {$dir}\n");
        exit(1);
    }
}

if (!extension_loaded('imagick')) {
    fwrite(STDERR, "Se requiere extensión imagick.\n");
    exit(1);
}

/** @param Imagick $image */
function writePng(Imagick $image, string $path, int $size): void
{
    $frame = clone $image;
    $frame->setImageBackgroundColor(new ImagickPixel('transparent'));
    $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
    $frame->setImageFormat('png32');
    $frame->writeImage($path);
    $frame->destroy();
}

/** @param Imagick $image */
function writeIco(Imagick $image, string $path): void
{
    $ico = new Imagick();
    $ico->setFormat('ico');
    foreach ([16, 32, 48] as $size) {
        $frame = clone $image;
        $frame->setImageBackgroundColor(new ImagickPixel('transparent'));
        $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
        $ico->addImage($frame);
        $frame->destroy();
    }
    $ico->writeImages($path, true);
    $ico->destroy();
}

$base = new Imagick();
$base->setBackgroundColor(new ImagickPixel('transparent'));
$base->readImage($sourceSvg);
$base->setImageFormat('png32');

foreach ($targets as $dir) {
    copy($sourceSvg, $dir . '/favicon.svg');

    writePng($base, $dir . '/favicon-32x32.png', 32);
    writePng($base, $dir . '/favicon-16x16.png', 16);
    writePng($base, $dir . '/apple-touch-icon.png', 180);
    writeIco($base, $dir . '/favicon.ico');

    echo "OK {$dir}\n";
}

$base->destroy();
echo "Favicons generados.\n";
