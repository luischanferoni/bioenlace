<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones del agente de auto-reserva en resolución de turnos.
 */
final class TurnoResolucionAutoReservaNotificationCatalog
{
    /** @var array{title: string, body: string}|null */
    private static ?array $message = null;

    /**
     * @return array{title: string, body: string}
     */
    public function autoRebooked(): array
    {
        if (self::$message !== null) {
            return self::$message;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-resolucion-auto-reserva-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de auto-reserva: ' . $path);
        }

        $data = Yaml::parseFile($path);
        $message = is_array($data) && ($data['version'] ?? null) === 1
            ? ($data['auto_rebooked'] ?? null)
            : null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body'] ?? null)
            || trim($message['body']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de notificación para auto-reserva de turno.');
        }

        self::$message = [
            'title' => trim($message['title']),
            'body' => trim($message['body']),
        ];

        return self::$message;
    }

    public static function resetCacheForTests(): void
    {
        self::$message = null;
    }
}
