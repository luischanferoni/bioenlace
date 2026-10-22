<?php

namespace common\components\Domain\Clinical\HistoryExchange\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de alertas operativas de jobs de integración.
 */
final class IntegrationRetryNotificationCatalog
{
    /** @var array{title: string, body_template: string}|null */
    private static ?array $opsAlert = null;

    /**
     * @return array{title: string, body_template: string}
     */
    public function opsAlert(): array
    {
        if (self::$opsAlert !== null) {
            return self::$opsAlert;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'HistoryExchange'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'integration-retry-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de reintentos de integración: ' . $path);
        }

        $data = Yaml::parseFile($path);
        $alert = is_array($data) && ($data['version'] ?? null) === 1
            ? ($data['ops_alert'] ?? null)
            : null;
        if (
            !is_array($alert)
            || !is_string($alert['title'] ?? null)
            || trim($alert['title']) === ''
            || !is_string($alert['body_template'] ?? null)
            || trim($alert['body_template']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de alerta operativa de integración.');
        }

        self::$opsAlert = [
            'title' => trim($alert['title']),
            'body_template' => trim($alert['body_template']),
        ];

        return self::$opsAlert;
    }

    public static function resetCacheForTests(): void
    {
        self::$opsAlert = null;
    }
}
