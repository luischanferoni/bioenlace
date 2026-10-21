<?php

namespace common\components\Domain\Clinical\CareCohort\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones del agente de seguimiento de cohortes.
 */
final class CareFollowupNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string, body_template: string}
     */
    public function forRule(string $ruleId, string $audience): array
    {
        if (!in_array($audience, ['staff', 'patient'], true)) {
            throw new \InvalidArgumentException('Audiencia inválida para notificación care follow-up.');
        }

        $message = self::load()['rules'][$ruleId][$audience] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
        ) {
            throw new \RuntimeException(
                sprintf('Falta el título de notificación care follow-up: %s/%s.', $ruleId, $audience)
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
                sprintf('Definición de cuerpo inválida para notificación care follow-up: %s/%s.', $ruleId, $audience)
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
            . DIRECTORY_SEPARATOR . 'CareCohort'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'care-followup-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones care follow-up: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['rules'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo care-followup-notifications inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
