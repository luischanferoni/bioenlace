<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class EncounterAsyncPushNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @param array<string, string> $replace
     * @return array{title: string, body: string}
     */
    public function event(string $eventKey, array $replace = []): array
    {
        $event = self::load()['events'][$eventKey] ?? null;
        if (
            !is_array($event)
            || !is_string($event['title'] ?? null)
            || trim($event['title']) === ''
            || !is_string($event['body'] ?? null)
            || trim($event['body']) === ''
        ) {
            throw new \RuntimeException('Definición de notificación async incompleta: ' . $eventKey);
        }

        return [
            'title' => str_replace(array_keys($replace), array_values($replace), $event['title']),
            'body' => str_replace(array_keys($replace), array_values($replace), $event['body']),
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
            . DIRECTORY_SEPARATOR . 'encounter-async-push-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones async: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['events'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo encounter-async-push-notifications inválido.');
        }

        self::$cache = $data;

        return self::$cache;
    }
}
