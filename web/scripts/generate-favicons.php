<?php

/**
 * Genera favicon.svg / PNG / ICO para frontend y admin desde docs/logo/logo_icono_2.*
 *
 * Uso: php web/scripts/generate-favicons.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceSvg = $root . '/docs/logo/logo_icono_2.svg';
$sourcePng = $root . '/docs/logo/logo_icono_2.png';
$targets = [
    $root . '/frontend/web',
    $root . '/admin/web',
    dirname($root) . '/institucional/images',
];

if (!is_file($sourceSvg) && !is_file($sourcePng)) {
    fwrite(STDERR, "No se encontró {$sourceSvg} ni {$sourcePng}\n");
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
if (is_file($sourceSvg)) {
    $base->readImage($sourceSvg);
} else {
    $base->readImage($sourcePng);
}
$base->setImageFormat('png32');

foreach ($targets as $dir) {
    $isInstitucionalImages = substr($dir, -7) === '/images';
    $svgDest = $dir . ($isInstitucionalImages ? '/logo-icon.svg' : '/favicon.svg');
    if (is_file($sourceSvg)) {
        copy($sourceSvg, $svgDest);
    }

    if (!$isInstitucionalImages) {
        writePng($base, $dir . '/favicon-32x32.png', 32);
        writePng($base, $dir . '/favicon-16x16.png', 16);
        writePng($base, $dir . '/apple-touch-icon.png', 180);
        writeIco($base, $dir . '/favicon.ico');
    } else {
        writePng($base, $dir . '/logo-icon.png', 192);
        writePng($base, $dir . '/logo-icon-32.png', 32);
    }

    echo "OK {$dir}\n";
}

$base->destroy();
echo "Favicons generados.\n";
