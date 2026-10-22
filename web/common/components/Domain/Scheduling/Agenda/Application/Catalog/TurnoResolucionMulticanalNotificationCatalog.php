<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de mensajes email/SMS del flujo multicanal de reubicación de turnos.
 */
final class TurnoResolucionMulticanalNotificationCatalog
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $templates = null;

    /**
     * @return array<string, string>
     */
    public function forChannel(string $channel): array
    {
        if (!in_array($channel, ['email', 'sms'], true)) {
            throw new \InvalidArgumentException('Canal inválido para notificación multicanal de turno: ' . $channel . '.');
        }

        $template = self::load()[$channel] ?? null;
        $fields = $channel === 'email' ? ['subject', 'body'] : ['body'];
        if (!is_array($template)) {
            throw new \RuntimeException('No hay plantilla para canal multicanal de turno: ' . $channel . '.');
        }

        foreach ($fields as $field) {
            if (!is_string($template[$field] ?? null) || trim($template[$field]) === '') {
                throw new \RuntimeException(
                    sprintf('Plantilla multicanal incompleta: %s/%s.', $channel, $field)
                );
            }
        }

        return array_map('trim', array_intersect_key($template, array_flip($fields)));
    }

    public static function resetCacheForTests(): void
    {
        self::$templates = null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function load(): array
    {
        if (self::$templates !== null) {
            return self::$templates;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-resolucion-multicanal-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones multicanal: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['channels'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-resolucion-multicanal-notifications inválido.');
        }

        self::$templates = $data['channels'];

        return self::$templates;
    }
}
