<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de push para opciones sugeridas de reubicación de turno.
 */
final class TurnoResolucionShortlistNotificationCatalog
{
    private static ?string $pushBodySuffixTemplate = null;

    public function pushBodySuffixTemplate(): string
    {
        if (self::$pushBodySuffixTemplate !== null) {
            return self::$pushBodySuffixTemplate;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-resolucion-shortlist-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones shortlist: ' . $path);
        }

        $data = Yaml::parseFile($path);
        $template = is_array($data) && ($data['version'] ?? null) === 1
            ? ($data['push_body_suffix_template'] ?? null)
            : null;
        if (!is_string($template) || trim($template) === '' || strpos($template, '{{options_summary}}') === false) {
            throw new \RuntimeException('Plantilla de sufijo push inválida para shortlist de reubicación.');
        }

        self::$pushBodySuffixTemplate = $template;

        return self::$pushBodySuffixTemplate;
    }

    public static function resetCacheForTests(): void
    {
        self::$pushBodySuffixTemplate = null;
    }
}
