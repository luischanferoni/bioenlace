<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones del cierre de resolución de turnos.
 */
final class TurnoResolucionLoopCloseNotificationCatalog
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $messages = null;

    /**
     * @return array<string, string>
     */
    public function forMessage(string $messageId): array
    {
        $message = self::load()[$messageId] ?? null;
        if (!is_array($message)) {
            throw new \RuntimeException('Mensaje desconocido para cierre de resolución de turno: ' . $messageId . '.');
        }

        $expectedFields = $messageId === 'staff_escalation'
            ? ['title', 'body_template']
            : ['title', 'body'];
        foreach ($expectedFields as $field) {
            if (!is_string($message[$field] ?? null) || trim($message[$field]) === '') {
                throw new \RuntimeException(
                    sprintf('Definición incompleta de mensaje para cierre de resolución: %s/%s.', $messageId, $field)
                );
            }
        }

        return array_map('trim', array_intersect_key($message, array_flip($expectedFields)));
    }

    public static function resetCacheForTests(): void
    {
        self::$messages = null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function load(): array
    {
        if (self::$messages !== null) {
            return self::$messages;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-resolucion-loop-close-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de cierre de resolución: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-resolucion-loop-close-notifications inválido.');
        }
        self::$messages = $data['messages'];

        return self::$messages;
    }
}
