<?php

namespace common\components\Domain\Clinical\Laboratory\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones del agente de clasificación de laboratorio.
 */
final class PostLabClassificationNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string, body_template: string}
     */
    public function forSeverity(string $severity, string $audience): array
    {
        $severity = strtolower(trim($severity));
        if (!in_array($audience, ['staff', 'patient'], true)) {
            throw new \InvalidArgumentException('Audiencia inválida para notificación de laboratorio.');
        }

        $message = self::load()['severities'][$severity][$audience] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
        ) {
            throw new \RuntimeException(
                sprintf('Falta el título de notificación de laboratorio: %s/%s.', $severity, $audience)
            );
        }

        $body = $message['body'] ?? null;
        $bodyTemplate = $message['body_template'] ?? null;
        if (
            ($body !== null && (!is_string($body) || trim($body) === ''))
            || ($bodyTemplate !== null && (!is_string($bodyTemplate) || trim($bodyTemplate) === ''))
            || (($body === null) === ($bodyTemplate === null))
        ) {
            throw new \RuntimeException(
                sprintf('Definición de cuerpo inválida para notificación de laboratorio: %s/%s.', $severity, $audience)
            );
        }

        return [
            'title' => trim($message['title']),
            'body' => is_string($body) ? trim($body) : '',
            'body_template' => is_string($bodyTemplate) ? trim($bodyTemplate) : '',
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
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'Laboratory'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'post-lab-classification-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de laboratorio: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['severities'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo post-lab-classification-notifications inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
