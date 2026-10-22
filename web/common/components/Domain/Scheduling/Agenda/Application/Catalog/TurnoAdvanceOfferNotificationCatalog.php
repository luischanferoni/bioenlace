<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones del agente de ofertas de adelanto de turnos.
 */
final class TurnoAdvanceOfferNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body_template: string, action_label: string}
     */
    public function offer(): array
    {
        $message = self::load()['offer'] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body_template'] ?? null)
            || trim($message['body_template']) === ''
            || !is_string($message['action_label'] ?? null)
            || trim($message['action_label']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de notificación para adelanto de turno.');
        }

        return [
            'title' => trim($message['title']),
            'body_template' => trim($message['body_template']),
            'action_label' => trim($message['action_label']),
        ];
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
            . DIRECTORY_SEPARATOR . 'turno-advance-offer-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de adelanto de turno: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['offer'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-advance-offer-notifications inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
