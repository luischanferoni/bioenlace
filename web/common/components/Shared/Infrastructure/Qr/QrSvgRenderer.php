<?php

namespace common\components\Shared\Infrastructure\Qr;

use Yii;

/**
 * Render de un código QR como SVG inline, sin servicios externos.
 *
 * Usa el encoder que ya viene con TCPDF (reportico). Ese encoder emite deprecaciones
 * de PHP 8.3 que, si se imprimen, corrompen una respuesta JSON: se silencian acá, en
 * un único lugar, para que cualquier consumidor quede a salvo.
 */
final class QrSvgRenderer
{
    public const CORRECTION_LEVEL = 'M';
    private const QUIET_ZONE_MODULES = 4;

    public static function render(string $payload, string $ariaLabel = 'Código QR'): string
    {
        require_once dirname(Yii::getAlias('@common'))
            . '/vendor/reportico/yii2-reportico/components/tcpdf/include/barcodes/qrcode.php';

        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);
        try {
            $barcode = new \QRcode($payload, self::CORRECTION_LEVEL);
            $matrix = $barcode->getBarcodeArray();
        } finally {
            error_reporting($errorReporting);
        }

        $path = '';
        foreach ($matrix['bcode'] as $rowIndex => $row) {
            foreach ($row as $columnIndex => $module) {
                if ($module) {
                    $x = $columnIndex + self::QUIET_ZONE_MODULES;
                    $y = $rowIndex + self::QUIET_ZONE_MODULES;
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        $viewSize = (int) $matrix['num_cols'] + 2 * self::QUIET_ZONE_MODULES;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="%3$s">'
            . '<rect width="%1$d" height="%1$d" fill="#fff"/><path d="%2$s" fill="#000"/></svg>',
            $viewSize,
            $path,
            htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
