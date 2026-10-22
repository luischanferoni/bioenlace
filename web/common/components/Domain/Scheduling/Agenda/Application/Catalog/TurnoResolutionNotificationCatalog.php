<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class TurnoResolutionNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function requiresRelocationTitle(): string
    {
        $title = self::load()['messages']['requires_relocation']['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new \RuntimeException('Título de notificación de reubicación incompleto.');
        }

        return trim($title);
    }

    /**
     * @param array{reason: string, date: string, time: string} $variables
     */
    public function requiresRelocationBody(array $variables): string
    {
        $template = self::load()['messages']['requires_relocation']['body_template'] ?? null;
        if (!is_string($template) || trim($template) === '') {
            throw new \RuntimeException('Plantilla de notificación de reubicación incompleta.');
        }

        return trim(str_replace(
            ['{reason}', '{date}', '{time}'],
            [$variables['reason'], $variables['date'], $variables['time']],
            $template
        ));
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-resolution-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de resolución de turno: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-resolution-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
