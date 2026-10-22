<?php

namespace common\components\Domain\Person\Representation\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class PersonRepresentationDelegatedActionNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body_template: string}
     */
    public function forAction(string $action): array
    {
        $messages = self::load()['actions'] ?? null;
        $message = is_array($messages) ? ($messages[$action] ?? $messages['default'] ?? null) : null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body_template'] ?? null)
            || trim($message['body_template']) === ''
        ) {
            throw new \RuntimeException('Copy de acción delegada inválido: ' . $action);
        }

        return [
            'title' => trim($message['title']),
            'body_template' => trim($message['body_template']),
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
            . DIRECTORY_SEPARATOR . 'Person'
            . DIRECTORY_SEPARATOR . 'Representation'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'delegated-action-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de acciones delegadas: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['actions'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo delegated-action-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
